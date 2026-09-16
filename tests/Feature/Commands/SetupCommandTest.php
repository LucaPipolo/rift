<?php

declare(strict_types=1);

use App\Contracts\PortProbe;
use App\Env\EnvFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Output\BufferedOutput;

describe('when the checkout has no .env yet', function (): void {
    beforeEach(function (): void {
        $this->root = project();
        host($this->root);
        lifecycleCaddy();
    });

    it('exits with success', function (): void {
        artisan('setup')->assertExitCode(0);
    });

    it('writes the derived values into .env', function (): void {
        artisan('setup')->run();

        expect(EnvFile::at($this->root . '/.env')->get('BASE_DOMAIN'))->toBe('myapp.test');
    });

    it('says it created .env', function (): void {
        artisan('setup')->expectsOutputToContain('created');
    });

    it('notes the compose project and its domain', function (): void {
        artisan('setup')->expectsOutputToContain('myapp (myapp.test)');
    });

    it('does not note any ports when the project forwards none', function (): void {
        artisan('setup')->doesntExpectOutputToContain('Ports');
    });

    it('brings the checkout up', function (): void {
        artisan('setup')->run();

        Process::assertRan(lifecycleRan('docker compose up --build -d'));
    });

    it('opens with a single banner although it hands over to up', function (): void {
        Artisan::call('setup', outputBuffer: $output = new BufferedOutput());

        expect(substr_count($output->fetch(), 'dev environment'))->toBe(1);
    });
});

describe('when the checkout already has an .env', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject());
        lifecycleCaddy();
    });

    it('says it updated .env', function (): void {
        artisan('setup')->expectsOutputToContain('updated');
    });
});

describe('when the project forwards host ports', function (): void {
    beforeEach(function (): void {
        host(project(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nports:\n"
            . "  - { key: FORWARD_DB_PORT, default: 3306 }\n"
            . "  - { key: VITE_PORT, default: 5173 }\n"
            . "  - { key: FORWARD_MEILISEARCH_DASHBOARD_PORT, default: 7700 }\n"));
        lifecycleCaddy();
        app()->instance(PortProbe::class, probe());
    });

    it('notes each port under a short label', function (string $label): void {
        artisan('setup')->expectsOutputToContain($label);
    })->with([
        'forwarded port' => 'Db 3306',
        'plain port' => 'Vite 5173',
        'dashboard port' => 'Meilisearch 7700',
    ]);
});

describe('when the worktree name collides with a host alias', function (): void {
    beforeEach(function (): void {
        $this->root = lifecycleWorktree('myapp-api', "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nhosts: [api]\n");
        unlink($this->root . '/.env');
    });

    it('exits with failure', function (): void {
        artisan('setup')->assertExitCode(1);
    });

    it('does not write .env', function (): void {
        artisan('setup')->run();

        expect($this->root . '/.env')->not->toBeFile();
    });
});

describe('when .env cannot be written', function (): void {
    beforeEach(function (): void {
        host(project(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nenv:\n  APP_URL: \"{nope}\"\n"));
        lifecycleCaddy();
    });

    it('exits with failure', function (): void {
        artisan('setup')->assertExitCode(1);
    });

    it('does not bring the checkout up', function (): void {
        artisan('setup')->run();

        Process::assertNotRan(lifecycleRan('docker compose up'));
    });
});

describe('when up fails', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*info*' => Process::result(exitCode: 1)]);
        lifecycleCaddy();
    });

    it('exits with the failure up returns', function (): void {
        artisan('setup')->assertExitCode(1);
    });
});
