<?php

declare(strict_types=1);

use App\Env\EnvProjection;

describe('__construct()', function (): void {
    it('holds whether the .env was seeded, the values written and the ports resolved', function (): void {
        $projection = new EnvProjection(true, ['APP_PORT' => '4321'], ['APP_PORT' => 4321]);

        expect([$projection->created, $projection->values, $projection->ports])
            ->toBe([true, ['APP_PORT' => '4321'], ['APP_PORT' => 4321]]);
    });
});
