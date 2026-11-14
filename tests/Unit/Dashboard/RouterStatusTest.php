<?php

declare(strict_types=1);

use App\Config\RouterConfig;
use App\Dashboard\RouterStatus;

describe('__construct()', function (): void {
    it('keeps null routes apart from no routes, for a router that is not answering', function (): void {
        expect(new RouterStatus(new RouterConfig(), running: true, routes: null, members: [], trusted: false)->routes)->toBeNull();
    });
});
