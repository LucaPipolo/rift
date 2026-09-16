<?php

declare(strict_types=1);

use App\Docker\ComposeFile;

describe('allIn()', function (): void {
    it('lists the files in the order Compose itself prefers', function (): void {
        $root = scratch();
        touch($root . '/docker-compose.yml');
        touch($root . '/compose.yaml');

        expect(array_map(fn (ComposeFile $file): string => $file->name(), ComposeFile::allIn($root)))
            ->toBe(['compose.yaml', 'docker-compose.yml']);
    });

    it('finds every name Compose recognises', function (): void {
        $root = scratch();

        foreach (ComposeFile::NAMES as $name) {
            touch($root . '/' . $name);
        }

        expect(array_map(fn (ComposeFile $file): string => $file->name(), ComposeFile::allIn($root)))
            ->toBe(ComposeFile::NAMES);
    });

    it('returns full paths inside the searched directory', function (): void {
        $root = scratch();
        touch($root . '/compose.yaml');

        expect(ComposeFile::allIn($root)[0]->path)->toBe($root . '/compose.yaml');
    });

    it('skips a directory that carries a Compose file name', function (): void {
        $root = scratch();
        mkdir($root . '/compose.yaml');

        expect(ComposeFile::allIn($root))->toBe([]);
    });

    it('returns an empty list when the directory has no Compose file', function (): void {
        expect(ComposeFile::allIn(scratch()))->toBe([]);
    });
});

describe('name()', function (): void {
    it('returns the filename without its directory', function (): void {
        expect((new ComposeFile('/Sites/myapp/docker-compose.yml'))->name())->toBe('docker-compose.yml');
    });
});

describe('joinsRouterNetwork()', function (): void {
    it('returns true when the file references ROUTER_NAME', function (): void {
        $file = composeFileContaining("networks:\n  router:\n    name: \"\${ROUTER_NAME}\"\n");

        expect($file->joinsRouterNetwork())->toBeTrue();
    });

    it('returns false when the file does not reference ROUTER_NAME', function (): void {
        $file = composeFileContaining("services:\n  app:\n    image: nginx\n");

        expect($file->joinsRouterNetwork())->toBeFalse();
    });

    it('returns false when the file does not exist', function (): void {
        expect((new ComposeFile(scratch() . '/compose.yaml'))->joinsRouterNetwork())->toBeFalse();
    });

    it('returns false when the file cannot be read', function (): void {
        $file = composeFileContaining("networks:\n  router:\n    name: \"\${ROUTER_NAME}\"\n");
        chmod($file->path, 0o000);

        expect($file->joinsRouterNetwork())->toBeFalse();
    });
});

describe('publishedPorts()', function (): void {
    it('finds the app port Sail publishes but never writes to .env', function (): void {
        // Sail publishes ${APP_PORT:-80}:80 and ships no APP_PORT in .env.example,
        // so reading .env alone would leave the app claiming the router's port 80.
        $file = composeFileContaining(<<<'YAML'
            services:
                laravel.test:
                    ports:
                        - '${APP_PORT:-80}:80'
                        - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
                pgsql:
                    ports:
                        - '${FORWARD_DB_PORT:-5432}:5432'
            YAML);

        expect($file->publishedPorts())->toBe([
            'APP_PORT' => 80,
            'VITE_PORT' => 5173,
            'FORWARD_DB_PORT' => 5432,
        ]);
    });

    it('takes the host side of a mapping, not the container side', function (): void {
        $file = composeFileContaining(<<<'YAML'
            services:
                app:
                    ports:
                        - '${APP_PORT:-8080}:${CONTAINER_PORT:-3000}'
            YAML);

        expect($file->publishedPorts())->toBe(['APP_PORT' => 8080]);
    });

    it('skips a port that is pinned rather than configurable', function (): void {
        // Nothing rift can do about a hardcoded host port; better to leave it out
        // than to invent a key the compose file does not read.
        $file = composeFileContaining(<<<'YAML'
            services:
                app:
                    ports:
                        - '9000:9000'
            YAML);

        expect($file->publishedPorts())->toBe([]);
    });

    it('skips a port written in the long mapping syntax', function (): void {
        $file = composeFileContaining(<<<'YAML'
            services:
                app:
                    ports:
                        - target: 80
                          published: '${APP_PORT:-8080}'
            YAML);

        expect($file->publishedPorts())->toBe([]);
    });

    it('skips a service that publishes no ports', function (): void {
        $file = composeFileContaining(<<<'YAML'
            services:
                app:
                    image: nginx:alpine
                    networks: [default, router]
            YAML);

        expect($file->publishedPorts())->toBe([]);
    });

    it('skips a service that is not a mapping', function (): void {
        $file = composeFileContaining(<<<'YAML'
            services:
                empty: ~
                app:
                    ports:
                        - '${APP_PORT:-80}:80'
            YAML);

        expect($file->publishedPorts())->toBe(['APP_PORT' => 80]);
    });

    it('returns an empty list for a file it cannot parse', function (): void {
        $file = composeFileContaining("services:\n  app:\n    ports: [ unterminated");

        expect($file->publishedPorts())->toBe([]);
    });

    it('returns an empty list for a file that does not exist', function (): void {
        expect((new ComposeFile(scratch() . '/compose.yaml'))->publishedPorts())->toBe([]);
    });

    it('returns an empty list when the file has no services mapping', function (string $yaml): void {
        expect(composeFileContaining($yaml)->publishedPorts())->toBe([]);
    })->with([
        'an empty file' => [''],
        'a plain string' => ['just text'],
        'no services key' => ["name: myapp\n"],
        'a scalar services value' => ["services: none\n"],
    ]);
});
