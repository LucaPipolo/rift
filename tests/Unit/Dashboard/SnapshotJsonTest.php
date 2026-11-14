<?php

declare(strict_types=1);

use App\Dashboard\ProjectStatus;
use App\Dashboard\Snapshot;
use App\Dashboard\SnapshotJson;
use App\Enums\CheckoutState;

afterEach(function (): void {
    putenv('XDG_CONFIG_HOME');
});

describe('toArray()', function (): void {
    it('matches the contract fixture rift-dash is tested against', function (): void {
        putenv('XDG_CONFIG_HOME=/home/you/.config');
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../../../dash/testdata/snapshot.json'), true);

        expect(SnapshotJson::toArray(contractSnapshot()))->toBe($fixture);
    });

    it('carries the contract version', function (): void {
        expect(SnapshotJson::toArray(dashboardSnapshot())['version'])->toBe(SnapshotJson::VERSION);
    });

    it('says the admin API is not answering when the router lists no routes', function (): void {
        expect(SnapshotJson::toArray(dashboardSnapshot(routerRunning: false))['router'])->toMatchArray(['running' => false, 'answering' => false]);
    });
});

describe('encode()', function (): void {
    it('replaces bytes that are not UTF-8 instead of failing', function (): void {
        $config = myappConfig();
        $snapshot = new Snapshot(dashboardSnapshot()->router, [new ProjectStatus('/Sites/myapp', $config, [dashboardCheckout($config, null, "feat/\xff", CheckoutState::Serving)])]);

        expect(SnapshotJson::encode($snapshot))->toContain("feat/\u{FFFD}");
    });

    it('writes one line of JSON without escaped slashes', function (): void {
        expect(SnapshotJson::encode(contractSnapshot()))->not->toContain("\n")->toContain('"root":"/Sites/myapp"');
    });
});
