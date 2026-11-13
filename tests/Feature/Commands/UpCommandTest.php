<?php

declare(strict_types=1);

use App\Config\ProjectRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

describe('when the checkout is set up and the machine is healthy', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nhosts: [api]\n"));
    });

    it('exits with success', function (): void {
        artisan('up')->assertExitCode(0);
    });

    it('opens with the project banner', function (): void {
        artisan('up')->expectsOutputToContain('dev environment');
    });

    it('hints at -v in the banner when not verbose', function (): void {
        artisan('up')->expectsOutputToContain('-v for details');
    });

    it('starts the containers', function (): void {
        artisan('up')->run();

        Process::assertRan(lifecycleRan('docker compose up --build -d'));
    });

    it('certifies the base domain', function (): void {
        artisan('up')->expectsOutputToContain('Certifying myapp.test');
    });

    it('does not certify a wildcard of its own on the main checkout', function (): void {
        artisan('up')->doesntExpectOutputToContain('Certifying *.');
    });

    it('trusts the CA certificate on Linux', function (): void {
        artisan('up')->run();

        Process::assertRan(lifecycleRan('sudo update-ca-certificates'));
    });

    it('registers every host of the checkout', function (): void {
        artisan('up')->expectsOutputToContain('Registering myapp.test, api.myapp.test');
    });

    it('prints the URL of every host', function (string $url): void {
        artisan('up')->expectsOutputToContain($url);
    })->with([
        'own domain' => 'https://myapp.test',
        'alias' => 'https://api.myapp.test',
    ]);

    it('remembers the project for rift dash', function (): void {
        artisan('up')->run();

        expect((new ProjectRegistry((string) getcwd() . '/.config/rift/projects.yaml'))->roots())->toBe([(string) getcwd()]);
    });

    it('does not point to the log file', function (): void {
        artisan('up')->doesntExpectOutputToContain('Log:');
    });
});

describe('when run with -v', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject());
    });

    it('drops the -v hint from the banner', function (): void {
        artisan('up', ['-v' => true])->doesntExpectOutputToContain('-v for details');
    });

    it('announces each step before it runs', function (): void {
        artisan('up', ['-v' => true])->expectsOutputToContain('▸ Starting containers');
    });
});

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), ['*docker*info*' => Process::result(exitCode: 1)]);
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('explains that it could not reach a Docker daemon', function (): void {
        artisan('up')->expectsOutputToContain('could not reach one');
    });

    it('does not start the containers', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('docker compose up'));
    });
});

describe('when .env is missing keys rift derives', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(['.env' => "COMPOSE_PROJECT_NAME=myapp\n"]));
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('names the missing keys', function (): void {
        artisan('up')->expectsOutputToContain('.env has no GIT_COMMON_DIR, BASE_NAME');
    });

    it('tells the user to run rift setup', function (): void {
        artisan('up')->expectsOutputToContain('Run `rift setup` to update it.');
    });
});

describe('when the preset was never bootstrapped', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(config: "name: myapp\ndomain: myapp.test\npreset: sail\napp:\n  port: 80\n"));
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('tells the user to run rift setup first', function (): void {
        artisan('up')->expectsOutputToContain('This checkout is not set up yet.');
    });
});

describe('when the worktree name collides with a host alias', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        lifecycleWorktree('myapp-api', "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nhosts: [api]\n");
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('names the colliding alias', function (): void {
        artisan('up')->expectsOutputToContain('This worktree is named `api`');
    });

    it('does not open with the banner', function (): void {
        artisan('up')->doesntExpectOutputToContain('dev environment');
    });
});

describe('when a worktree has host aliases', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        lifecycleWorktree('feat-x', "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nhosts: [api]\n");
    });

    it('certifies a wildcard of the worktree domain', function (): void {
        artisan('up')->expectsOutputToContain('Certifying *.feat-x.myapp.test');
    });

    it('prints the URL of the worktree alias', function (): void {
        artisan('up')->expectsOutputToContain('https://api.feat-x.myapp.test');
    });
});

describe('when another container holds ports 80 and 443', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), [
            '*Ports*' => "nginx\t0.0.0.0:80->80/tcp",
            '*docker*ps*' => '',
        ]);
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('tells the user to stop the container holding the ports', function (): void {
        artisan('up')->expectsOutputToContain('docker stop nginx');
    });

    it('does not start the router', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('docker run'));
    });
});

describe('when the router cannot start', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), [
            "*docker*'run'*" => Process::result(exitCode: 1),
            '*docker*ps*' => '',
        ]);
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('does not start the containers', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('docker compose up'));
    });

    it('points to the log file', function (): void {
        artisan('up')->expectsOutputToContain('Log: ' . lifecycleLog('up'));
    });

    it('writes the reason to the log file', function (): void {
        $log = lifecycleLog('up');

        artisan('up')->run();

        expect(file_get_contents($log))->toBe("Could not start the router.\n");
    });

    it('prints the reason instead of writing it to the log with -v', function (): void {
        artisan('up', ['-v' => true])->expectsOutputToContain('Could not start the router.');
    });

    it('does not point to the log file with -v', function (): void {
        artisan('up', ['-v' => true])->doesntExpectOutputToContain('Log:');
    });
});

describe('when the router has not issued its CA yet', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        Sleep::fake();
        host(lifecycleProject(), ["*'exec' 'rift-router'*" => Process::result(exitCode: 1)]);
    });

    it('skips trusting the CA', function (): void {
        artisan('up')->expectsOutputToContain('not yet ready');
    });

    it('still exits with success', function (): void {
        artisan('up')->assertExitCode(0);
    });
});

describe('when the CA cannot be copied out of the router', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), ["*docker*'cp'*" => Process::result(exitCode: 1)]);
    });

    it('says it could not read the CA', function (): void {
        artisan('up')->expectsOutputToContain('could not read the CA');
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('does not start the containers', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('docker compose up'));
    });
});

describe('when the CA is already trusted on Linux', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        $root = lifecycleProject();
        host($root, ['*openssl*' => 'sha256 Fingerprint=AB:CD']);
        lifecycleCaCopy($root);
        file_put_contents(lifecycleHostOs('Linux') . '/rift-router-ca.crt', 'CERTIFICATE');
    });

    it('skips trusting the CA', function (): void {
        artisan('up')->expectsOutputToContain('already trusted');
    });

    it('does not ask sudo to trust it again', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('sudo'));
    });
});

describe('when the CA cannot be trusted', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), ['*update-ca-certificates*' => Process::result(exitCode: 1)]);
    });

    it('says it could not trust the CA', function (): void {
        artisan('up')->expectsOutputToContain('could not trust');
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });
});

describe('on macOS when the CA is already trusted', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        $root = lifecycleProject();
        host($root, [
            '*openssl*' => 'sha256 Fingerprint=AB:CD',
            '*find-certificate*' => 'SHA-256 hash: ABCD',
        ]);
        lifecycleCaCopy($root);
        lifecycleHostOs('Darwin');
    });

    it('does not ask for the macOS password', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('sudo -v'));
    });

    it('exits with success', function (): void {
        artisan('up')->assertExitCode(0);
    });
});

describe('on macOS when the CA is not trusted yet', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        lifecycleHostOs('Darwin');
    });

    it('explains why it needs the macOS password', function (): void {
        host(lifecycleProject(), ['*sudo*-v*' => Process::result(exitCode: 1)]);

        artisan('up')->expectsOutputToContain('requires your macOS password.');
    });

    it('exits with failure when the password is refused', function (): void {
        host(lifecycleProject(), ['*sudo*-v*' => Process::result(exitCode: 1)]);

        artisan('up')->assertExitCode(1);
    });

    it('does not start the router when the password is refused', function (): void {
        host(lifecycleProject(), ['*sudo*-v*' => Process::result(exitCode: 1)]);

        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('docker network create'));
    });

    it('trusts the CA once the password is given', function (): void {
        host(lifecycleProject());

        artisan('up')->run();

        Process::assertRan(lifecycleRan('sudo security add-trusted-cert'));
    });
});

describe('when a before_up hook fails', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(
            lifecycleProject(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nhooks:\n  before_up:\n    run: prepare-things\n    on: host\n"),
            ['*prepare-things*' => Process::result(exitCode: 1)],
        );
    });

    it('labels the hook with the stage default', function (): void {
        artisan('up')->expectsOutputToContain('Preparing');
    });

    it('says the hook failed', function (): void {
        artisan('up')->expectsOutputToContain('hook failed');
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('does not start the containers', function (): void {
        artisan('up')->run();

        Process::assertNotRan(lifecycleRan('docker compose up'));
    });
});

describe('when the containers fail to start', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), ["*'compose' 'up'*" => Process::result(exitCode: 1)]);
    });

    it('says the containers could not start', function (): void {
        artisan('up')->expectsOutputToContain('could not start');
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('does not register the route', function (): void {
        artisan('up')->run();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/routes'));
    });

    it('does not remember the project', function (): void {
        artisan('up')->run();

        expect((string) getcwd() . '/.config/rift/projects.yaml')->not->toBeFile();
    });
});

describe('when the app container is not on the router network', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(lifecycleProject(), ['*docker*network*inspect*' => "other-app-1\n"]);
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('names the container the router cannot reach', function (): void {
        artisan('up')->expectsOutputToContain('myapp-app-1 is running but is not on the');
    });

    it('shows the compose stanza that joins the router network', function (): void {
        artisan('up')->expectsOutputToContain('name: "${ROUTER_NAME:?run rift setup first}"');
    });

    it('does not register the route', function (): void {
        artisan('up')->run();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/routes'));
    });
});

describe('when the route cannot be registered', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject());
        lifecycleCaddy([
            '*/id/*' => Http::response('', 404),
            '*/routes' => Http::response('', 500),
        ]);
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('does not print the URLs', function (): void {
        artisan('up')->doesntExpectOutputToContain('https://myapp.test');
    });
});

describe('when the route is already registered with the same hosts', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject());
        lifecycleCaddy(['*/id/myapp/match/0/host' => Http::response(['myapp.test'], 200)]);
    });

    it('says the route is already registered', function (): void {
        artisan('up')->expectsOutputToContain('already registered');
    });
});

describe('when an after_up hook fails', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        host(
            lifecycleProject(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nhooks:\n  after_up:\n    run: seed-things\n    label: Seeding\n"),
            ['*seed-things*' => Process::result(exitCode: 1)],
        );
    });

    it('labels the hook with its own label', function (): void {
        artisan('up')->expectsOutputToContain('Seeding');
    });

    it('runs the hook inside the app container', function (): void {
        artisan('up')->run();

        Process::assertRan(lifecycleRan('docker compose exec -T app sh -c seed-things'));
    });

    it('exits with failure', function (): void {
        artisan('up')->assertExitCode(1);
    });

    it('does not print the URLs', function (): void {
        artisan('up')->doesntExpectOutputToContain('https://myapp.test');
    });
});

describe('when the project registry cannot be read', function (): void {
    beforeEach(function (): void {
        lifecycleCaddy();
        $root = lifecycleProject();
        mkdir($root . '/.config/rift', 0o777, true);
        file_put_contents($root . '/.config/rift/projects.yaml', "projects: nope\n");
        host($root);
    });

    it('still exits with success', function (): void {
        artisan('up')->assertExitCode(0);
    });

    it('says what is wrong with the registry', function (): void {
        artisan('up')->expectsOutputToContain('`projects` must be a list of directories.');
    });
});
