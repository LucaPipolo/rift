<?php

declare(strict_types=1);

use App\Env\EnvFile;
use App\Exceptions\ConfigException;

describe('at()', function (): void {
    it('reads an existing file', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_ENV=local\n");

        expect(EnvFile::at($path)->get('APP_ENV'))->toBe('local');
    });

    it('opens a missing file as empty', function (): void {
        expect(EnvFile::at(scratch() . '/.env')->get('APP_ENV'))->toBeNull();
    });
});

describe('exists()', function (): void {
    it('returns true when the file is on disk', function (): void {
        $path = scratch() . '/.env';
        touch($path);

        expect(EnvFile::at($path)->exists())->toBeTrue();
    });

    it('returns false when the file is not on disk', function (): void {
        expect(EnvFile::at(scratch() . '/.env')->exists())->toBeFalse();
    });
});

describe('get()', function (): void {
    it('returns the value of a key', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_PORT=4321\n");

        expect(EnvFile::at($path)->get('APP_PORT'))->toBe('4321');
    });

    it('returns a value that contains an equals sign in full', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_KEY=base64:aa==\n");

        expect(EnvFile::at($path)->get('APP_KEY'))->toBe('base64:aa==');
    });

    it('returns null for a key with an empty value', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "GIT_COMMON_DIR=\n");

        expect(EnvFile::at($path)->get('GIT_COMMON_DIR'))->toBeNull();
    });

    it('returns null for a key that is not in the file', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_ENV=local\n");

        expect(EnvFile::at($path)->get('APP_PORT'))->toBeNull();
    });

    it('does not match a key that only starts with the requested key', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_PORT_EXTRA=1\n");

        expect(EnvFile::at($path)->get('APP_PORT'))->toBeNull();
    });
});

describe('set()', function (): void {
    it('replaces an existing key on its own line', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_ENV=local\nBASE_DOMAIN=myapp.test\nAPP_DEBUG=true\n");

        EnvFile::at($path)->set('BASE_DOMAIN', 'other.test')->write();

        expect(file_get_contents($path))->toBe("APP_ENV=local\nBASE_DOMAIN=other.test\nAPP_DEBUG=true\n");
    });

    it('keeps comments and blank lines when replacing a key', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "# Docker\nBASE_DOMAIN=myapp.test\n\n# Sentry\nSENTRY_DSN=secret\n");

        EnvFile::at($path)->set('BASE_DOMAIN', 'other.test')->write();

        expect(file_get_contents($path))->toBe("# Docker\nBASE_DOMAIN=other.test\n\n# Sentry\nSENTRY_DSN=secret\n");
    });

    it('replaces only the exact key, not a key it prefixes', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_PORT_EXTRA=1\nAPP_PORT=2\n");

        EnvFile::at($path)->set('APP_PORT', '3')->write();

        expect(file_get_contents($path))->toBe("APP_PORT_EXTRA=1\nAPP_PORT=3\n");
    });

    it('appends a new key at the end of the file', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_ENV=local\n");

        EnvFile::at($path)->set('APP_PORT', '4321')->write();

        expect(file_get_contents($path))->toBe("APP_ENV=local\nAPP_PORT=4321\n");
    });

    it('does not add blank lines when appending on every run', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, "APP_ENV=local\n");

        EnvFile::at($path)->set('APP_PORT', '4321')->write();
        EnvFile::at($path)->set('VITE_PORT', '5173')->write();

        expect(file_get_contents($path))->toBe("APP_ENV=local\nAPP_PORT=4321\nVITE_PORT=5173\n");
    });

    it('appends a key to a file that does not exist yet', function (): void {
        $path = scratch() . '/.env';

        EnvFile::at($path)->set('APP_PORT', '4321')->write();

        expect(file_get_contents($path))->toBe("APP_PORT=4321\n");
    });
});

describe('seedFrom()', function (): void {
    it('copies the first candidate that exists', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/main.env', "APP_KEY=real\n");
        file_put_contents($dir . '/example.env', "APP_KEY=\n");

        EnvFile::at($dir . '/.env')->seedFrom([$dir . '/main.env', $dir . '/example.env']);

        expect(file_get_contents($dir . '/.env'))->toBe("APP_KEY=real\n");
    });

    it('skips candidates that do not exist', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/example.env', "APP_KEY=example\n");

        EnvFile::at($dir . '/.env')->seedFrom([$dir . '/main.env', $dir . '/example.env']);

        expect(file_get_contents($dir . '/.env'))->toBe("APP_KEY=example\n");
    });

    it('skips candidates that cannot be read', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/main.env', "APP_KEY=real\n");
        chmod($dir . '/main.env', 0o000);
        file_put_contents($dir . '/example.env', "APP_KEY=example\n");

        EnvFile::at($dir . '/.env')->seedFrom([$dir . '/main.env', $dir . '/example.env']);

        expect(file_get_contents($dir . '/.env'))->toBe("APP_KEY=example\n");
    });

    it('returns true when it seeds the file', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/example.env', "APP_KEY=\n");

        expect(EnvFile::at($dir . '/.env')->seedFrom([$dir . '/example.env']))->toBeTrue();
    });

    it('returns false when the file already exists', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/.env', "APP_KEY=mine\n");
        file_put_contents($dir . '/example.env', "APP_KEY=\n");

        expect(EnvFile::at($dir . '/.env')->seedFrom([$dir . '/example.env']))->toBeFalse();
    });

    it('leaves an existing file untouched', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/.env', "APP_KEY=mine\n");
        file_put_contents($dir . '/example.env', "APP_KEY=\n");

        EnvFile::at($dir . '/.env')->seedFrom([$dir . '/example.env']);

        expect(file_get_contents($dir . '/.env'))->toBe("APP_KEY=mine\n");
    });

    it('normalises Windows line endings in the copied file', function (): void {
        $dir = scratch();
        file_put_contents($dir . '/example.env', "APP_ENV=local\r\nAPP_DEBUG=true\r\n");

        EnvFile::at($dir . '/.env')->seedFrom([$dir . '/example.env']);

        expect(file_get_contents($dir . '/.env'))->toBe("APP_ENV=local\nAPP_DEBUG=true\n");
    });

    it('throws a ConfigException when no candidate exists', function (): void {
        $dir = scratch();

        EnvFile::at($dir . '/.env')->seedFrom([$dir . '/main.env', $dir . '/example.env']);
    })->throws(ConfigException::class, 'No .env to start from.');
});

describe('write()', function (): void {
    it('ends the file with a newline', function (): void {
        $path = scratch() . '/.env';
        file_put_contents($path, 'APP_ENV=local');

        EnvFile::at($path)->write();

        expect(file_get_contents($path))->toBe("APP_ENV=local\n");
    });

    it('leaves no temporary file behind', function (): void {
        $path = scratch() . '/.env';

        EnvFile::at($path)->set('APP_ENV', 'local')->write();

        expect($path . '.rift-tmp')->not->toBeFile();
    });

    it('throws a ConfigException when the directory does not exist', function (): void {
        EnvFile::at(scratch() . '/missing/.env')->set('APP_ENV', 'local')->write();
    })->throws(ConfigException::class, 'Could not write');

    it('throws a ConfigException when the path is a directory', function (): void {
        $path = scratch() . '/.env';
        mkdir($path);

        EnvFile::at($path)->set('APP_ENV', 'local')->write();
    })->throws(ConfigException::class, 'Could not write');

    it('does not create a temporary file when it cannot write the target', function (): void {
        $path = scratch() . '/.env';
        mkdir($path);

        try {
            EnvFile::at($path)->set('APP_ENV', 'local')->write();
        } catch (ConfigException) {
            // Expected: the target is a directory.
        }

        expect($path . '.rift-tmp')->not->toBeFile();
    });
});
