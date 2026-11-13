<?php

declare(strict_types=1);

namespace App\Commands;

use App\Checkout\Instance;
use App\Commands\Concerns\ManagesRouter;
use App\Enums\HookStage;
use App\Env\EnvProjector;
use App\Support\Outcome;
use App\Support\Ui;

/**
 * Starts the current checkout and serves it behind the shared router.
 */
final class UpCommand extends RiftCommand
{
    use ManagesRouter;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start this checkout and serve it at its own HTTPS domain';

    /**
     * Execute the console command.
     *
     * @param  EnvProjector  $projector  The env projector.
     *
     * @return int The exit code.
     */
    public function handle(EnvProjector $projector): int
    {
        if (! $this->requireDocker()) {
            return self::FAILURE;
        }

        $instance = $this->instance();
        $router = $this->router();
        $runner = $this->runner();

        $missing = $projector->missingKeys($instance);

        if ($missing !== []) {
            return $this->bail(sprintf(
                '.env has no %s. Run `rift setup` to update it.',
                implode(', ', $missing),
            ));
        }

        if (! $runner->isBootstrapped($instance)) {
            return $this->bail('This checkout is not set up yet. Run `rift setup` first.');
        }

        if ($this->refuseHostCollision($instance)) {
            return self::FAILURE;
        }

        $this->header($instance->config->name);

        // Stop before asking for a password if the router cannot start at all.
        if (($blocker = $router->blocker()) !== null) {
            $this->reportPortConflict($blocker, $router->config->binding);

            return self::FAILURE;
        }

        if (! $this->primeTrust() || ! $this->startRouter($router)) {
            return self::FAILURE;
        }

        $this->step("Certifying {$instance->config->baseDomain}", fn () => Outcome::changed(
            $router->admin->ensureCertificateFor($instance->config->baseDomain),
            skipped: 'already covered',
        ));

        if ($instance->needsOwnWildcard()) {
            $this->step("Certifying *.{$instance->domain()}", fn () => Outcome::changed(
                $router->admin->ensureWildcardFor($instance->domain()),
                skipped: 'already covered',
            ));
        }

        $this->step('Trusting CA certificate', fn () => $this->trust());

        if ($this->hasFailed() || ! $this->runHooks($runner, $instance, HookStage::BeforeUp)) {
            return self::FAILURE;
        }

        $started = $this->step('Starting containers', fn () => Outcome::succeeded(
            $runner->up($instance),
            failed: 'could not start',
        ));

        if ($started->fatal) {
            return self::FAILURE;
        }

        if (! in_array($instance->container(), $router->docker->networkMembers($router->config->network()), true)) {
            $this->reportUnwiredCompose($instance, $router->config->network());

            return self::FAILURE;
        }

        // Register only once the container is reachable, so a failed start never
        // leaves a route pointing at nothing.
        $registered = $this->step('Registering ' . implode(', ', $instance->hosts()), fn () => Outcome::changed(
            $router->register($instance),
            skipped: 'already registered',
        ));

        if ($registered->fatal || ! $this->runHooks($runner, $instance, HookStage::AfterUp)) {
            return self::FAILURE;
        }

        foreach ($instance->urls() as $url) {
            Ui::done($url);
        }

        Ui::blank();

        return self::SUCCESS;
    }

    /**
     * Render the compose change needed for the router to reach the app container.
     *
     * @param  Instance  $instance  The current checkout.
     * @param  string  $network  The router network.
     *
     * @return void
     */
    private function reportUnwiredCompose(Instance $instance, string $network): void
    {
        $this->notice([
            "<span class=\"font-bold\">{$instance->container()}</span> is running but is not on the",
            "<span class=\"font-bold\">{$network}</span> network, so the router cannot reach it.",
            '',
            'Add the router network to this service in your compose file:',
            '',
            '  services:',
            '    <span class="font-bold">' . $instance->config->appService . '</span>:',
            '      networks: [default, router]',
            '',
            '  networks:',
            '    default:',
            '    router:',
            '      external: true',
            '      name: "${ROUTER_NAME:?run rift setup first}"',
            '',
            'Then run rift up again.',
        ], 'compose');
    }

    /**
     * Ask for the sudo password up front when the CA still needs trusting.
     *
     * @return bool Whether the run may continue.
     */
    private function primeTrust(): bool
    {
        $ca = $this->router()->ca;

        if (! $ca->onMac()) {
            return true;
        }

        // Refresh the local copy if the router is up, else use the one from a
        // previous run, so a cold start does not ask for a password it does not need.
        $ca->extract();

        if ($ca->isTrusted()) {
            return true;
        }

        Ui::notice('notice', [
            'Adding and trusting the SSL certificate to your System Keychain',
            'requires your macOS password.',
        ]);

        $granted = $ca->primeSudo('  Password: ');

        Ui::blank();

        return $granted;
    }

    /**
     * Trust the router's CA unless it is already trusted.
     *
     * @return Outcome How the step ended.
     */
    private function trust(): Outcome
    {
        $ca = $this->router()->ca;

        if (! $ca->waitUntilIssued()) {
            return Outcome::skipped('not yet ready');
        }

        if (! $ca->extract()) {
            return Outcome::failed('could not read the CA');
        }

        if ($ca->isTrusted()) {
            return Outcome::skipped('already trusted');
        }

        return Outcome::succeeded($ca->trust(), failed: 'could not trust');
    }
}
