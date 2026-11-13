<?php

declare(strict_types=1);

use App\Dashboard\Rift;

describe('command()', function (): void {
    it('runs the native binary on its own', function (): void {
        expect(new Rift('micro', '/opt/homebrew/bin/rift', 'rift')->command('up'))
            ->toBe(['/opt/homebrew/bin/rift', 'up', '--no-interaction', '--no-ansi']);
    });

    it('runs the script through PHP from source or a PHAR', function (): void {
        expect(new Rift('cli', '/usr/bin/php', '/app/rift')->command('router:restart'))
            ->toBe(['/usr/bin/php', '/app/rift', 'router:restart', '--no-interaction', '--no-ansi']);
    });
});

describe('interactive()', function (): void {
    it('leaves prompts and colours on', function (): void {
        expect(new Rift('micro', 'rift', 'rift')->interactive('trust'))->toBe(['rift', 'trust']);
    });
});

describe('directories()', function (): void {
    it('looks beside the native binary', function (): void {
        expect(new Rift('micro', '/opt/homebrew/bin/rift')->directories())->toBe(['/opt/homebrew/bin']);
    });

    it('looks in builds/ and beside the script from source or a PHAR', function (): void {
        expect(new Rift('cli', '/usr/bin/php', '/app/rift')->directories())->toBe(['/app/builds', '/app']);
    });
});
