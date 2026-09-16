<?php

declare(strict_types=1);

namespace App\Commands;

use App\Checkout\CheckoutLocator;
use App\Config\ConfigTemplate;
use App\Config\ProjectConfig;
use App\Docker\ComposeFile;
use App\Enums\HookStage;
use App\Enums\Preset;
use App\Env\EnvFile;
use App\Support\Ui;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Writes a rift.yaml, defaulting each answer to what the project already declares.
 */
final class InitCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'init {--force : Overwrite an existing rift.yaml}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a rift.yaml for this project';

    /**
     * Execute the console command.
     *
     * @param  CheckoutLocator  $locator  The checkout locator.
     *
     * @return int The exit code.
     */
    public function handle(CheckoutLocator $locator): int
    {
        $checkout = $locator->locate();
        $path = ProjectConfig::pathIn($checkout->root);

        if (is_file($path) && ! $this->option('force')) {
            return $this->bail(ProjectConfig::FILENAME . ' already exists. Pass --force to replace it.');
        }

        $env = EnvFile::at($checkout->root . '/.env');
        $example = EnvFile::at($checkout->root . '/.env.example');
        $read = fn (string $key) => $env->get($key) ?? $example->get($key);
        $composeFiles = ComposeFile::allIn($checkout->root);

        Ui::banner('rift', 'init', $checkout->isPrimary() ? null : 'worktree');
        Ui::blank();

        $preset = Preset::from(select(
            label: 'How are this project\'s containers driven?',
            options: [
                Preset::Compose->value => 'docker compose',
                Preset::Sail->value => 'Laravel Sail',
            ],
            default: $this->detectPreset($checkout->root)->value,
        ));

        $name = text(
            label: 'Project name',
            default: $read('BASE_NAME') ?? $read('COMPOSE_PROJECT_NAME') ?? basename($checkout->root),
            hint: 'Used for the compose project and as the base of every worktree name.',
        );

        $domain = text(
            label: 'Base domain',
            default: $read('BASE_DOMAIN') ?? $name . '.test',
            hint: 'The main checkout gets this; every worktree gets a subdomain of it.',
        );

        $service = text(
            label: 'Service the router should proxy to',
            default: $read('APP_SERVICE') ?? $preset->defaultService(),
        );

        $port = (int) text(
            label: 'Port that service listens on inside its container',
            default: $read('CONTAINER_PORT') ?? ($preset === Preset::Sail ? '80' : '3000'),
            validate: fn (string $value) => is_numeric($value) && (int) $value > 0 && (int) $value < 65536
                ? null
                : 'Enter a port between 1 and 65535.',
        );

        $ports = $this->detectPorts($composeFiles, $env, $example);

        $writeUrl = $preset === Preset::Sail && $read('APP_URL') !== null;

        if ($ports !== []) {
            Ui::heading('host ports kept clear of other worktrees');

            foreach ($ports as $key => $default) {
                Ui::row('', $key, (string) $default);
            }
        }

        Ui::blank();

        if (! confirm('Write ' . ProjectConfig::FILENAME . '?')) {
            return self::SUCCESS;
        }

        file_put_contents($path, (new ConfigTemplate(
            name: $name,
            domain: $domain,
            preset: $preset,
            service: $service,
            port: $port,
            ports: $ports,
            env: $writeUrl ? ['APP_URL' => 'https://{domain}'] : [],
            hooks: $preset === Preset::Sail
                ? [HookStage::AfterUp->value => [['run' => 'composer run setup', 'label' => 'Running project setup']]]
                : [],
        ))->render());

        // Validate now rather than on the first `rift up`.
        ProjectConfig::load($checkout->root);

        Ui::blank();
        Ui::rule(ProjectConfig::FILENAME, 'written');
        Ui::heading('will serve');
        Ui::row('', $domain, 'this checkout');
        Ui::row('', "&lt;worktree&gt;.{$domain}", 'every worktree beside it');

        $this->reportComposeWiring($composeFiles[0] ?? null);

        Ui::blank();
        Ui::endRule('rift setup to bring it up');
        Ui::blank();

        return self::SUCCESS;
    }

    /**
     * Render whether the compose file joins the router network, and the stanza to add if not.
     *
     * @param  ComposeFile|null  $file  The checkout's main compose file, if any.
     *
     * @return void
     */
    private function reportComposeWiring(?ComposeFile $file): void
    {
        Ui::heading('compose');

        if ($file === null) {
            Ui::item('!', 'yellow', 'No compose file found here.');

            return;
        }

        if ($file->joinsRouterNetwork()) {
            Ui::item('✓', 'green', "{$file->name()} already joins the router network");

            return;
        }

        Ui::item('!', 'yellow', "{$file->name()} does not join the router network yet");
        Ui::blank();

        foreach ([
            'services:',
            '  <options=bold>your-app-service</>:',
            '    networks: [default, router]',
            '    volumes:',
            '      - "${GIT_COMMON_DIR:?run rift setup first}:${GIT_COMMON_DIR}"',
            '',
            'networks:',
            '  default:',
            '  router:',
            '    external: true',
            '    name: "${ROUTER_NAME:?run rift setup first}"',
        ] as $line) {
            Ui::hint($line === '' ? ' ' : $line);
        }
    }

    /**
     * Guess the preset from what the checkout contains.
     *
     * @param  string  $root  The checkout's root directory.
     *
     * @return Preset The likely preset.
     */
    private function detectPreset(string $root): Preset
    {
        return is_file($root . '/artisan') || is_dir($root . '/vendor/laravel/sail')
            ? Preset::Sail
            : Preset::Compose;
    }

    /**
     * Collect the host ports the compose files publish, preferring values already in .env.
     *
     * The compose file decides which ports exist: Sail publishes `${APP_PORT:-80}`
     * without listing APP_PORT in .env.example, and left alone it would claim
     * port 80 from the router.
     *
     * @param  list<ComposeFile>  $files  The checkout's compose files.
     * @param  EnvFile  $env  The checkout's .env.
     * @param  EnvFile  $example  The checkout's .env.example.
     *
     * @return array<string, int> The default port, keyed by env key.
     */
    private function detectPorts(array $files, EnvFile $env, EnvFile $example): array
    {
        $ports = [];

        foreach ($files as $file) {
            $ports = [...$ports, ...$file->publishedPorts()];
        }

        foreach ($ports as $key => $default) {
            $current = $example->get($key) ?? $env->get($key);

            if (is_numeric($current)) {
                $ports[$key] = (int) $current;
            }
        }

        return $ports;
    }
}
