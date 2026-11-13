<?php

declare(strict_types=1);

use App\Checkout\CheckoutLocator;
use App\Config\RouterConfig;
use App\Dashboard\SnapshotCollector;
use App\Enums\CheckoutState;
use App\Env\EnvProjector;
use App\Env\PortResolver;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;

describe('collect()', function (): void {
    beforeEach(function (): void {
        $this->root = project(['.env' => "VITE_PORT=5173\n"], "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nports:\n  - { key: VITE_PORT, default: 5173 }\n");
        $this->tree = scratch() . '/myapp-search';
        mkdir($this->tree);
        file_put_contents($this->tree . '/.env', "VITE_PORT=5174\n");
        file_put_contents($this->tree . '/a.php', '<?php');
        touch($this->tree . '/a.php', 1_700_000_000);

        caddy(['/config/apps/http/servers/main/routes' => [['@id' => 'myapp']]]);
        collectorHost($this->root, $this->tree);
    });

    it('lists every project with its checkouts, main first', function (): void {
        $snapshot = collector()->collect([$this->root]);

        expect(array_map(fn ($checkout) => $checkout->label(), $snapshot->projects[0]->checkouts))->toBe(['main', 'search']);
    });

    it('reads each checkout\'s branch', function (): void {
        expect(collector()->collect([$this->root])->projects[0]->checkouts[1]->branch)->toBe('feat/search');
    });

    it('gives each checkout its own containers', function (): void {
        $checkouts = collector()->collect([$this->root])->projects[0]->checkouts;

        expect([count($checkouts[0]->containers), count($checkouts[1]->containers)])->toBe([2, 1]);
    });

    it('works out each checkout\'s state', function (): void {
        $checkouts = collector()->collect([$this->root])->projects[0]->checkouts;

        expect([$checkouts[0]->state, $checkouts[1]->state])->toBe([CheckoutState::Serving, CheckoutState::Failed]);
    });

    it('reads the .env values rift manages', function (): void {
        expect(collector()->collect([$this->root])->projects[0]->checkouts[1]->env)
            ->toHaveKey('VITE_PORT', '5174')
            ->toHaveKey('COMPOSE_PROJECT_NAME', null);
    });

    it('reads a worktree\'s uncommitted changes and when they last changed', function (): void {
        $git = collector()->collect([$this->root])->projects[0]->checkouts[1]->git;

        expect([$git?->changes, $git?->changedAt])->toBe([['a.php', 'gone.txt', 'b.php', 'odd name.txt'], 1_700_000_000]);
    });

    it('counts how far a worktree\'s branch is from the main branch', function (): void {
        $git = collector()->collect([$this->root])->projects[0]->checkouts[1]->git;

        expect([$git?->behind, $git?->ahead])->toBe([1, 3]);
    });

    it('compares the main checkout with nothing', function (): void {
        collector()->collect([$this->root]);

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('rev-list', $process->command, true), 1);
    });

    it('reads the router', function (): void {
        $router = collector()->collect([$this->root])->router;

        expect([$router->running, $router->routes, $router->members, $router->trusted])->toBe([true, ['myapp'], ['myapp-app-1'], false]);
    });

    it('reads each project once, however often it is listed', function (): void {
        expect(collector()->collect([$this->root, $this->root])->projects)->toHaveCount(1);
    });

    it('reuses a recent git status', function (): void {
        $collector = collector();
        $collector->collect([$this->root]);

        $collector->collect([$this->root]);

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('status', $process->command, true), 2);
    });

    it('reads git again once its status is ten seconds old', function (): void {
        Carbon::setTestNow('2026-11-13 10:00:00');
        $collector = collector();
        $collector->collect([$this->root]);

        Carbon::setTestNow('2026-11-13 10:00:10');
        $collector->collect([$this->root]);

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('status', $process->command, true), 4);
    });

    it('reads everything again after forget()', function (): void {
        $collector = collector();
        $collector->collect([$this->root]);

        $collector->forget();
        $collector->collect([$this->root]);

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('status', $process->command, true), 4);
    });
});

describe('collect() for a worktree with its own rift.yaml', function (): void {
    beforeEach(function (): void {
        $this->root = project();
        $this->tree = scratch() . '/myapp-search';
        mkdir($this->tree);
        caddy([]);
        collectorHost($this->root, $this->tree);
    });

    it('uses the worktree\'s own config', function (): void {
        file_put_contents($this->tree . '/rift.yaml', "name: myapp\ndomain: myapp.test\napp:\n  port: 8080\n");

        expect(collector()->collect([$this->root])->projects[0]->checkouts[1]->instance->config->appPort)->toBe(8080);
    });

    it('falls back to the main checkout\'s config when its own cannot be read', function (): void {
        file_put_contents($this->tree . '/rift.yaml', "name: [broken\n");

        expect(collector()->collect([$this->root])->projects[0]->checkouts[1]->instance->config->appPort)->toBe(80);
    });
});

describe('collect() when the router is stopped', function (): void {
    it('reads no routes', function (): void {
        $root = project();
        caddy([]);
        Process::fake(['*' => '']);

        expect(collector()->collect([$root])->router)->running->toBeFalse()->routes->toBeNull();
    });
});

describe('collect() for a project that cannot be read', function (): void {
    beforeEach(function (): void {
        caddy([]);
        Process::fake(['*' => '']);
    });

    it('explains a rift.yaml that cannot be parsed', function (): void {
        $root = project(config: "name: [broken\n");

        expect(collector()->collect([$root])->projects[0]->error)->toContain('rift.yaml');
    });

    it('explains a checkout git cannot read', function (): void {
        expect(collector()->collect([project()])->projects[0]->error)->toBe('Git cannot read this checkout.');
    });
});

describe('collect() for several projects', function (): void {
    it('sorts them by name', function (): void {
        $zeta = project(config: "name: zeta\ndomain: zeta.test\napp:\n  port: 80\n");
        $alpha = project(config: "name: alpha\ndomain: alpha.test\napp:\n  port: 80\n");
        caddy([]);
        Process::fake(['*' => '']);

        $names = array_map(fn ($project) => $project->name(), collector()->collect([$zeta, $alpha])->projects);

        expect($names)->toBe(['alpha', 'zeta']);
    });
});

describe('collect() when git fails', function (): void {
    beforeEach(function (): void {
        $this->root = project();
        $this->tree = scratch() . '/myapp-search';
        mkdir($this->tree);
        caddy([]);
    });

    it('reads no changes when git status fails', function (): void {
        collectorHost($this->root, $this->tree, ['*status*' => Process::result('', 'fatal', 128)]);

        expect(collector()->collect([$this->root])->projects[0]->checkouts[1]->git?->changes)->toBe([]);
    });

    // The dashboard's own `x` removes a worktree, and the next read can race the removal.
    it('reports a project whose worktree vanished while it was read', function (): void {
        collectorHost($this->root, $this->tree, ['*status*' => fn () => throw new RuntimeException('The provided cwd does not exist.')]);

        expect(collector()->collect([$this->root])->projects[0]->error)->toBe('Rift could not read it: The provided cwd does not exist.');
    });

    it('counts no divergence when git rev-list fails', function (): void {
        collectorHost($this->root, $this->tree, ['*rev-list*' => Process::result('', 'fatal', 128)]);

        expect(collector()->collect([$this->root])->projects[0]->checkouts[1]->git)->ahead->toBe(0)->behind->toBe(0);
    });

    it('counts no divergence for a detached worktree', function (): void {
        collectorHost($this->root, $this->tree, ['*worktree*list*' => "worktree {$this->root}\nbranch refs/heads/main\n\nworktree {$this->tree}\ndetached\n"]);

        collector()->collect([$this->root]);

        Process::assertNotRan(fn (PendingProcess $process) => is_array($process->command) && in_array('rev-list', $process->command, true));
    });
});

/**
 * Create a snapshot collector for the default router.
 *
 * @return SnapshotCollector The collector.
 */
function collector(): SnapshotCollector
{
    return new SnapshotCollector(new RouterConfig(), new CheckoutLocator(), new EnvProjector(new PortResolver(probe())));
}

describe('collect() for the route table', function (): void {
    it('reads each route\'s hosts and upstream', function (): void {
        $root = project();
        collectorHost($root, scratch() . '/myapp-search');
        caddy(['/config/apps/http/servers/main/routes' => [
            statusRoute('myapp', ['myapp.test', 'api.myapp.test'], 'myapp-app-1:80'),
            statusRoute('bare', ['bare.test']),
            statusRoute('', ['nameless.test']),
            ['@id' => 'odd', 'match' => [['host' => 'not-a-list']]],
        ]]);

        expect(collector()->collect([$root])->router->table)->toBe([
            ['id' => 'myapp', 'hosts' => ['myapp.test', 'api.myapp.test'], 'upstream' => 'myapp-app-1:80'],
            ['id' => 'bare', 'hosts' => ['bare.test'], 'upstream' => ''],
            ['id' => 'odd', 'hosts' => [], 'upstream' => ''],
        ]);
    });
});

describe('collect() for a project that pins its own router', function (): void {
    beforeEach(function (): void {
        $this->root = project(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nrouter: own-router\n");
        caddy(['/config/apps/http/servers/main/routes' => [['@id' => 'myapp']]]);
        collectorHost($this->root, scratch() . '/myapp-search', [
            "*'network' 'inspect' 'own-router'*" => "myapp-app-1\n",
            '*network*inspect*' => "someone-else-1\n",
            "*'docker' 'ps' '--format'*" => "own-router\nmyapp-app-1\n",
        ]);
    });

    it('works out its state from the router it pins', function (): void {
        expect(collector()->collect([$this->root])->projects[0]->checkouts[0]->state)->toBe(CheckoutState::Serving);
    });

    it('reads that router only once', function (): void {
        collector()->collect([$this->root]);

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('own-router', $process->command, true) && in_array('inspect', $process->command, true), 1);
    });
});
