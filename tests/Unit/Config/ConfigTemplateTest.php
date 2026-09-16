<?php

declare(strict_types=1);

use App\Config\ConfigTemplate;
use App\Enums\HookStage;
use App\Enums\Preset;

describe('render()', function (): void {
    $compose = fn (): ConfigTemplate => new ConfigTemplate(
        name: 'lcp-web',
        domain: 'lcp-web.test',
        preset: Preset::Compose,
        service: 'app',
        port: 4321,
        ports: ['APP_PORT' => 4321],
    );

    $sail = fn (): ConfigTemplate => new ConfigTemplate(
        name: 'myapp',
        domain: 'myapp.test',
        preset: Preset::Sail,
        service: 'laravel.test',
        port: 80,
        ports: [
            'VITE_PORT' => 5173,
            'FORWARD_DB_PORT' => 5432,
            'FORWARD_REDIS_PORT' => 6379,
            'FORWARD_MAILPIT_PORT' => 1025,
            'FORWARD_MAILPIT_DASHBOARD_PORT' => 8025,
            'FORWARD_MEILISEARCH_PORT' => 7700,
        ],
        env: ['APP_URL' => 'https://{domain}'],
        hooks: ['after_up' => [['run' => 'composer run setup', 'label' => 'Running project setup']]],
    );

    it('reproduces the name, domain, preset, service and port of a Compose project', function () use ($compose): void {
        $config = templateRoundTrip($compose());

        expect([$config->name, $config->baseDomain, $config->preset, $config->appService, $config->appPort])
            ->toBe(['lcp-web', 'lcp-web.test', Preset::Compose, 'app', 4321]);
    });

    it('reproduces the forwarded port of a Compose project', function () use ($compose): void {
        $config = templateRoundTrip($compose());

        expect(array_map(fn ($port) => [$port->key, $port->default], $config->ports))->toBe([['APP_PORT', 4321]]);
    });

    it('reproduces a project with no hooks as having none', function () use ($compose): void {
        expect(templateRoundTrip($compose())->hooks)->toBe([]);
    });

    it('reproduces the preset and service of a Sail project', function () use ($sail): void {
        $config = templateRoundTrip($sail());

        expect([$config->preset, $config->appService])->toBe([Preset::Sail, 'laravel.test']);
    });

    it('reproduces every forwarded port of a Sail project in order', function () use ($sail): void {
        expect(array_map(fn ($port) => $port->key, templateRoundTrip($sail())->ports))->toBe([
            'VITE_PORT', 'FORWARD_DB_PORT', 'FORWARD_REDIS_PORT',
            'FORWARD_MAILPIT_PORT', 'FORWARD_MAILPIT_DASHBOARD_PORT', 'FORWARD_MEILISEARCH_PORT',
        ]);
    });

    it('reproduces the env templates with their placeholders intact', function () use ($sail): void {
        expect(templateRoundTrip($sail())->env)->toBe(['APP_URL' => 'https://{domain}']);
    });

    it('reproduces a hook as one that runs in the app container', function () use ($sail): void {
        $hooks = templateRoundTrip($sail())->hooks(HookStage::AfterUp);

        expect(array_map(fn ($hook) => [$hook->run, $hook->label, $hook->onHost], $hooks))
            ->toBe([['composer run setup', 'Running project setup', false]]);
    });

    it('reproduces where a hook runs and the path that skips it', function (): void {
        $config = templateRoundTrip(new ConfigTemplate(
            name: 'myapp',
            domain: 'myapp.test',
            preset: Preset::Compose,
            service: 'app',
            port: 80,
            hooks: ['before_up' => [['run' => 'cp .env.example .env', 'on' => 'host', 'unless_exists' => '.env']]],
        ));

        $hook = $config->hooks(HookStage::BeforeUp)[0];

        expect([$hook->onHost, $hook->unlessExists, $hook->label])->toBe([true, '.env', null]);
    });

    it('reproduces the host aliases a project declares', function (): void {
        $config = templateRoundTrip(new ConfigTemplate(
            name: 'lcp-backend',
            domain: 'lcp-backend.test',
            preset: Preset::Sail,
            service: 'laravel.test',
            port: 80,
            hosts: ['api', 'admin'],
        ));

        expect($config->hosts)->toBe(['api', 'admin']);
    });

    it('reproduces env templates that hand the host aliases to the app', function (): void {
        $config = templateRoundTrip(new ConfigTemplate(
            name: 'lcp-backend',
            domain: 'lcp-backend.test',
            preset: Preset::Sail,
            service: 'laravel.test',
            port: 80,
            env: ['API_DOMAIN' => 'api.{domain}', 'ADMIN_DOMAIN' => 'admin.{domain}'],
            hosts: ['api', 'admin'],
        ));

        expect($config->env)->toBe(['API_DOMAIN' => 'api.{domain}', 'ADMIN_DOMAIN' => 'admin.{domain}']);
    });

    it('omits every optional section when the template has none', function (string $section): void {
        $yaml = new ConfigTemplate('myapp', 'myapp.test', Preset::Compose, 'app', 80)->render();

        expect($yaml)->not->toContain("\n{$section}:");
    })->with(['hosts', 'ports', 'env', 'hooks']);
});
