<?php

declare(strict_types=1);

use App\Dashboard\SystemTerminal;

describe('interactive()', function (): void {
    it('returns false when input and output are not a terminal', function (): void {
        $memory = fopen('php://memory', 'r+');
        assert(is_resource($memory));

        expect(new SystemTerminal($memory, $memory)->interactive())->toBeFalse();
    });
});
