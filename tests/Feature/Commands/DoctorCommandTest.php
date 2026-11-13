<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('when every check passes', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine();
        routerServing([statusRoute('myapp', ['myapp.test'], 'myapp-app-1:80')]);
    });

    it('confirms Docker is running', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ Docker is running');
    });

    it('confirms the project config is valid', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ Project config is valid');
    });

    it('confirms the .env carries every interpolated key', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ .env carries everything compose interpolates');
    });

    it('confirms the app container is on the router network', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ App container is on the router network');
    });

    it('lists the URLs the checkout serves', function (): void {
        artisan('doctor')->expectsOutputToContain('· myapp serves https://myapp.test');
    });

    it('confirms the router is running', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ rift-router is running');
    });

    it('confirms the admin API is answering', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ Router admin API is answering');
    });

    it('confirms the CA certificate is trusted', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ CA certificate is trusted');
    });

    it('confirms no other router runs the router image', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ No other routers running the router image');
    });

    it('says everything checks out', function (): void {
        artisan('doctor')->expectsOutputToContain('everything checks out');
    });

    it('exits with success', function (): void {
        artisan('doctor')->assertExitCode(0);
    });
});

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*docker*info*' => Process::result(exitCode: 1)]);
    });

    it('reports Docker as not running', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ Docker is running');
    });

    it('explains how to start Docker', function (): void {
        artisan('doctor')
            ->expectsOutputToContain('Start Docker Desktop, OrbStack or Colima, then try again.');
    });

    it('skips the project and router checks', function (): void {
        artisan('doctor')->doesntExpectOutputToContain('PROJECT');
    });

    it('counts a single problem', function (): void {
        artisan('doctor')->expectsOutputToContain('1 problem found');
    });

    it('exits with failure', function (): void {
        artisan('doctor')->assertExitCode(1);
    });
});

describe('when run outside a git checkout', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*rev-parse*--show-toplevel*' => Process::result(exitCode: 128)]);
        routerServing([]);
    });

    it('skips the project checks', function (): void {
        artisan('doctor')
            ->expectsOutputToContain('· Not inside a git checkout — skipping project checks');
    });

    it('still checks the router', function (): void {
        artisan('doctor')->expectsOutputToContain('✓ rift-router is running');
    });
});

describe('when the checkout has no rift.yaml', function (): void {
    beforeEach(function (): void {
        unlink(doctorPassingMachine() . '/rift.yaml');
        routerServing([]);
    });

    it('skips the project checks', function (): void {
        artisan('doctor')->expectsOutputToContain('· No rift.yaml here — skipping project checks');
    });

    it('does not count a missing rift.yaml as a problem', function (): void {
        artisan('doctor')->assertExitCode(0);
    });
});

describe('when rift.yaml is invalid', function (): void {
    beforeEach(function (): void {
        file_put_contents(doctorPassingMachine() . '/rift.yaml', "just a string\n");
        routerServing([]);
    });

    it('reports the project config as invalid', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ Project config is valid');
    });

    it('shows why the project config is invalid', function (): void {
        artisan('doctor')->expectsOutputToContain('rift.yaml must contain a YAML mapping.');
    });

    it('skips the .env check', function (): void {
        artisan('doctor')->doesntExpectOutputToContain('.env carries everything');
    });
});

describe('when the .env is missing keys', function (): void {
    beforeEach(function (): void {
        unlink(doctorPassingMachine() . '/.env');
        routerServing([]);
    });

    it('reports the .env as incomplete', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ .env carries everything compose interpolates');
    });

    it('lists the missing keys and how to add them', function (): void {
        artisan('doctor')->expectsOutputToContain(
            'Missing: COMPOSE_PROJECT_NAME, GIT_COMMON_DIR, BASE_NAME, BASE_DOMAIN, ROUTER_NAME, APP_SERVICE, CONTAINER_PORT. Run `rift setup`.',
        );
    });
});

describe('when the app container is not running', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*docker*ps*' => "rift-router\n"]);
        routerServing([]);
    });

    it('skips the network check', function (): void {
        artisan('doctor')
            ->expectsOutputToContain('· myapp-app-1 is not running — cannot check its network');
    });

    it('does not count a stopped app container as a problem', function (): void {
        artisan('doctor')->assertExitCode(0);
    });
});

describe('when the app container is off the router network', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*network*inspect*' => "rift-router\n"]);
        routerServing([]);
    });

    it('reports the app container as off the router network', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ App container is on the router network');
    });

    it('explains how to join the router network', function (): void {
        artisan('doctor')->expectsOutputToContain(
            'Add `networks: [default, router]` to app and an external `router` network named "${ROUTER_NAME}" to your compose file.',
        );
    });

    it('exits with failure', function (): void {
        artisan('doctor')->assertExitCode(1);
    });
});

describe('when the router is not running', function (): void {
    it('reports the router as not running', function (): void {
        doctorPassingMachine(['*docker*ps*' => '']);

        artisan('doctor')->expectsOutputToContain('✗ rift-router is running');
    });

    it('suggests starting the router when nothing holds its ports', function (): void {
        doctorPassingMachine(['*docker*ps*' => '']);

        artisan('doctor')->expectsOutputToContain('Run `rift router:start`.');
    });

    it('names the container holding ports 80 and 443', function (): void {
        doctorPassingMachine([
            "*docker*'ps'*Ports*" => "traefik\t0.0.0.0:80->80/tcp\n",
            '*docker*ps*' => '',
        ]);

        artisan('doctor')->expectsOutputToContain(
            'traefik is holding ports 80 and 443. Stop it, then run `rift router:start`.',
        );
    });

    it('names a host process holding ports 80 and 443', function (): void {
        doctorPassingMachine(['*lsof*' => "p123\ncnginx\n", '*docker*ps*' => '']);

        artisan('doctor')->expectsOutputToContain(
            'nginx is holding ports 80 and 443. Stop it, then run `rift router:start`.',
        );
    });

    it('skips the admin API check', function (): void {
        doctorPassingMachine(['*docker*ps*' => '']);

        artisan('doctor')->doesntExpectOutputToContain('Router admin API is answering');
    });
});

describe('when the admin API is not answering', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine();
        caddy([]);
    });

    it('reports the admin API as not answering', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ Router admin API is answering');
    });

    it('suggests restarting the router', function (): void {
        artisan('doctor')
            ->expectsOutputToContain('The container is up but :2019 is not answering. Try `rift router:restart`.');
    });

    it('skips the CA check', function (): void {
        artisan('doctor')->doesntExpectOutputToContain('CA certificate is trusted');
    });
});

describe('when the admin API is answering', function (): void {
    it('counts the registered routes', function (array $routes, string $count): void {
        doctorPassingMachine();
        routerServing($routes);

        artisan('doctor')->expectsOutputToContain("· {$count} registered");
    })->with([
        'no route' => [[], '0 routes'],
        'one route' => [[statusRoute('myapp', ['myapp.test'])], '1 route'],
        'two routes' => [[statusRoute('myapp', ['myapp.test']), statusRoute('shop', ['shop.test'])], '2 routes'],
    ]);
});

describe('when the CA cannot be read', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*docker*cp*' => Process::result(exitCode: 1)]);
        routerServing([]);
    });

    it('reports the CA as not trusted', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ CA certificate is trusted');
    });

    it('suggests restarting the router', function (): void {
        artisan('doctor')->expectsOutputToContain('Could not read the CA. Try `rift router:restart`.');
    });
});

describe('when the CA is not trusted', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine();
        routerWithScratchTrustStore();
        routerServing([]);
    });

    it('reports the CA as not trusted', function (): void {
        artisan('doctor')->expectsOutputToContain('✗ CA certificate is trusted');
    });

    it('suggests trusting the CA', function (): void {
        artisan('doctor')->expectsOutputToContain('Run `rift trust`.');
    });
});

describe('when another router runs the router image', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*ancestor*' => "rift-router\nold-router\n"]);
        routerServing([]);
        answerPrompts();
    });

    it('reports the competing routers', function (): void {
        artisan('doctor')
            ->expectsConfirmation('Remove them?')
            ->expectsOutputToContain('✗ No other routers running the router image');
    });

    it('names the competing routers', function (): void {
        artisan('doctor')
            ->expectsConfirmation('Remove them?')
            ->expectsOutputToContain('Found: old-router. They will compete with the shared router for ports 80 and 443.');
    });

    it('leaves the competing routers when the user declines', function (): void {
        artisan('doctor')->expectsConfirmation('Remove them?')->run();

        Process::assertNotRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'rm', '-f', 'old-router']);
    });

    it('removes every competing router when the user agrees', function (): void {
        artisan('doctor')->expectsConfirmation('Remove them?', 'yes')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'rm', '-f', 'old-router']);
    });

    it('says which competing router it removed', function (): void {
        artisan('doctor')
            ->expectsConfirmation('Remove them?', 'yes')
            ->expectsOutputToContain('removed old-router');
    });
});

describe('when a competing router cannot be removed', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine([
            '*ancestor*' => "rift-router\nold-router\n",
            '*docker*rm*' => Process::result(exitCode: 1),
        ]);
        routerServing([]);
        answerPrompts();
    });

    it('says which competing router it could not remove', function (): void {
        artisan('doctor')
            ->expectsConfirmation('Remove them?', 'yes')
            ->expectsOutputToContain('could not remove old-router');
    });
});

describe('when several checks fail', function (): void {
    beforeEach(function (): void {
        doctorPassingMachine(['*network*inspect*' => "rift-router\n", '*docker*cp*' => Process::result(exitCode: 1)]);
        routerServing([]);
    });

    it('counts every problem in the summary', function (): void {
        artisan('doctor')->expectsOutputToContain('2 problems found');
    });
});
