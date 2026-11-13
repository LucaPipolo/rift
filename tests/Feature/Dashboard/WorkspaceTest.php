<?php

declare(strict_types=1);

use App\Checkout\CheckoutLocator;
use App\Config\ProjectRegistry;
use App\Dashboard\Workspace;
use App\Exceptions\ConfigException;
use Illuminate\Support\Facades\Process;

/**
 * Create a workspace over a registry in the scratch config home.
 *
 * @return Workspace The workspace.
 */
function workspace(): Workspace
{
    return new Workspace(new ProjectRegistry(ProjectRegistry::path()), new CheckoutLocator());
}

describe('here()', function (): void {
    it('returns the checkout of a Rift project it runs in', function (): void {
        host($root = project());

        expect(workspace()->here()?->root)->toBe($root);
    });

    it('returns null in a checkout that is not a Rift project', function (): void {
        $root = scratch();
        chdir($root);
        host($root);

        expect(workspace()->here())->toBeNull();
    });

    it('returns null outside a git checkout', function (): void {
        Process::fake(['*' => Process::result('', 'fatal', 128)]);

        expect(workspace()->here('/tmp'))->toBeNull();
    });
});

describe('roots()', function (): void {
    it('lists every registered project, then the one it runs in, each once', function (): void {
        $other = project();
        $root = project();
        (new ProjectRegistry(ProjectRegistry::path()))->remember($other);
        (new ProjectRegistry(ProjectRegistry::path()))->remember($root);
        host($root);

        $here = workspace()->here();

        expect(workspace()->roots($here))->toEqualCanonicalizing([$other, $root])->toHaveCount(2);
    });

    it('lists only the registered projects outside one', function (): void {
        scratchConfigHome();

        expect(workspace()->roots(null))->toBe([]);
    });

    it('passes on a registry it cannot read', function (): void {
        $home = scratchConfigHome();
        mkdir($home . '/rift', 0o777, true);
        file_put_contents($home . '/rift/projects.yaml', "projects: nope\n");

        workspace()->roots(null);
    })->throws(ConfigException::class, '`projects` must be a list of directories.');
});
