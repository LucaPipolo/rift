<?php

declare(strict_types=1);

use App\Config\ProjectRegistry;
use App\Exceptions\ConfigException;

describe('path()', function (): void {
    afterEach(function (): void {
        putenv('XDG_CONFIG_HOME');
    });

    it('sits beside the global rift config', function (): void {
        putenv('XDG_CONFIG_HOME=/xdg');

        expect(ProjectRegistry::path())->toBe('/xdg/rift/projects.yaml');
    });
});

describe('roots()', function (): void {
    it('returns nothing when the file does not exist', function (): void {
        expect((new ProjectRegistry(scratch() . '/projects.yaml'))->roots())->toBe([]);
    });

    it('returns nothing for an empty file', function (): void {
        expect(registryContaining('')->roots())->toBe([]);
    });

    it('returns every listed project that still has a rift.yaml', function (): void {
        $kept = checkoutWithRiftYaml("name: myapp\n");

        expect(registryContaining("projects:\n  - {$kept}\n  - /gone\n")->roots())->toBe([$kept]);
    });

    it('rejects a file whose projects are not a list', function (): void {
        registryContaining("projects: nope\n")->roots();
    })->throws(ConfigException::class, '`projects` must be a list of directories.');

    it('rejects a file that is not a mapping', function (): void {
        registryContaining("just text\n")->roots();
    })->throws(ConfigException::class, '`projects` must be a list of directories.');

    // The file invites hand edits, so a typo must read as a fix to make, not a stack trace.
    it('rejects a file that is not valid YAML', function (): void {
        registryContaining("projects: [/a, /b\n")->roots();
    })->throws(ConfigException::class, 'is not valid YAML');

    it('rejects an entry that is not a path', function (): void {
        registryContaining("projects:\n  - ''\n")->roots();
    })->throws(ConfigException::class, 'every entry under `projects` must be a directory path.');
});

describe('remember()', function (): void {
    it('creates the file and its directory', function (): void {
        $registry = new ProjectRegistry(scratch() . '/rift/projects.yaml');
        $root = checkoutWithRiftYaml("name: myapp\n");

        $registry->remember($root);

        expect($registry->roots())->toBe([$root]);
    });

    it('reports that the file changed', function (): void {
        expect((new ProjectRegistry(scratch() . '/projects.yaml'))->remember(checkoutWithRiftYaml('')))->toBeTrue();
    });

    it('leaves the file alone for a project already recorded', function (): void {
        $root = checkoutWithRiftYaml('');
        $registry = new ProjectRegistry(scratch() . '/projects.yaml');
        $registry->remember($root);

        expect($registry->remember($root))->toBeFalse();
    });

    it('keeps the projects sorted', function (): void {
        $registry = new ProjectRegistry(scratch() . '/projects.yaml');
        $first = checkoutWithRiftYaml('');
        $second = checkoutWithRiftYaml('');
        [$low, $high] = $first < $second ? [$first, $second] : [$second, $first];

        $registry->remember($high);
        $registry->remember($low);

        expect($registry->roots())->toBe([$low, $high]);
    });

    it('drops projects whose rift.yaml is gone', function (): void {
        $root = checkoutWithRiftYaml('');
        $registry = registryContaining("projects:\n  - /gone\n");

        $registry->remember($root);

        expect(file_get_contents($registry->path))->not->toContain('/gone');
    });

    it('leaves no temporary file behind', function (): void {
        $registry = new ProjectRegistry(scratch() . '/projects.yaml');

        $registry->remember(checkoutWithRiftYaml(''));

        expect(glob(dirname($registry->path) . '/*.tmp'))->toBe([]);
    });

    it('refuses a directory it cannot write to', function (): void {
        $directory = scratch() . '/locked';
        mkdir($directory, 0o555);

        try {
            (new ProjectRegistry($directory . '/projects.yaml'))->remember(checkoutWithRiftYaml(''));
        } finally {
            chmod($directory, 0o755);
        }
    })->throws(ConfigException::class, 'Could not write');

    it('explains the file at its top', function (): void {
        $registry = new ProjectRegistry(scratch() . '/projects.yaml');

        $registry->remember(checkoutWithRiftYaml(''));

        expect(file_get_contents($registry->path))->toStartWith('# Projects rift has started on this machine');
    });
});

/**
 * Create a registry backed by a file with the given contents.
 *
 * @param  string  $contents  The projects.yaml contents.
 *
 * @return ProjectRegistry The registry.
 */
function registryContaining(string $contents): ProjectRegistry
{
    $path = scratch() . '/projects.yaml';
    file_put_contents($path, $contents);

    return new ProjectRegistry($path);
}
