<?php

declare(strict_types=1);

use App\Config\PortBinding;
use App\Docker\Container;
use App\Docker\Docker;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('available()', function (): void {
    it('returns true when docker info succeeds', function (): void {
        Process::fake(['*docker*info*' => 'Server Version: 27.0.0']);

        expect((new Docker())->available())->toBeTrue();
    });

    it('returns false when docker info fails', function (): void {
        Process::fake(['*docker*info*' => Process::result(exitCode: 1)]);

        expect((new Docker())->available())->toBeFalse();
    });
});

describe('containerRunning()', function (): void {
    it('returns true when the container is in the running list', function (): void {
        Process::fake(['*docker*ps*' => "myapp-app-1\nrift-router\n"]);

        expect((new Docker())->containerRunning('rift-router'))->toBeTrue();
    });

    it('returns false when the container is not in the running list', function (): void {
        Process::fake(['*docker*ps*' => "myapp-app-1\n"]);

        expect((new Docker())->containerRunning('rift-router'))->toBeFalse();
    });

    it('returns false when docker ps fails', function (): void {
        Process::fake(['*docker*ps*' => Process::result(output: "rift-router\n", exitCode: 1)]);

        expect((new Docker())->containerRunning('rift-router'))->toBeFalse();
    });
});

describe('runningNames()', function (): void {
    it('returns the trimmed name of every running container', function (): void {
        Process::fake(['*docker*ps*' => "  myapp-app-1  \n\nrift-router\n"]);

        expect((new Docker())->runningNames())->toBe(['myapp-app-1', 'rift-router']);
    });

    it('lists only running containers', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->runningNames();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'ps', '--format', '{{.Names}}']);
    });

    it('returns an empty list when docker ps fails', function (): void {
        Process::fake(['*docker*ps*' => Process::result(output: "rift-router\n", exitCode: 1)]);

        expect((new Docker())->runningNames())->toBe([]);
    });

    it('asks Docker only once while no container is started or stopped', function (): void {
        Process::fake(['*' => "rift-router\n"]);
        $docker = new Docker();

        $docker->runningNames();
        $docker->runningNames();

        Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command === ['docker', 'ps', '--format', '{{.Names}}'], 1);
    });

    it('asks Docker again after a container changes', function (Closure $change): void {
        Process::fake(['*' => "rift-router\n"]);
        $docker = new Docker();

        $docker->runningNames();
        $change($docker);
        $docker->runningNames();

        Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command === ['docker', 'ps', '--format', '{{.Names}}'], 2);
    })->with([
        'runContainer()' => [fn (Docker $docker): bool => $docker->runContainer(['caddy:latest'])],
        'startContainer()' => [fn (Docker $docker): bool => $docker->startContainer('rift-router')],
        'stopContainer()' => [fn (Docker $docker): bool => $docker->stopContainer('rift-router')],
        'removeContainer()' => [fn (Docker $docker): bool => $docker->removeContainer('rift-router')],
    ]);
});

describe('containerExists()', function (): void {
    it('returns true when a container of that name exists, running or not', function (): void {
        Process::fake(["'docker' 'ps' '-a' '--format' '{{.Names}}'" => "rift-router\n"]);

        expect((new Docker())->containerExists('rift-router'))->toBeTrue();
    });

    it('returns false when no container has that name', function (): void {
        Process::fake(['*docker*ps*' => "myapp-app-1\n"]);

        expect((new Docker())->containerExists('rift-router'))->toBeFalse();
    });
});

describe('runContainer()', function (): void {
    it('runs the container detached with the given arguments', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->runContainer(['--name', 'rift-router', 'caddy:latest']);

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'run', '-d', '--name', 'rift-router', 'caddy:latest']);
    });

    it('returns true when the container starts', function (): void {
        Process::fake(['*' => '']);

        expect((new Docker())->runContainer(['caddy:latest']))->toBeTrue();
    });

    it('returns false when docker run fails', function (): void {
        Process::fake(['*docker*run*' => Process::result(exitCode: 125)]);

        expect((new Docker())->runContainer(['caddy:latest']))->toBeFalse();
    });
});

describe('startContainer()', function (): void {
    it('starts the named container', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->startContainer('rift-router');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'start', 'rift-router']);
    });

    it('returns false when docker start fails', function (): void {
        Process::fake(['*docker*start*' => Process::result(exitCode: 1)]);

        expect((new Docker())->startContainer('rift-router'))->toBeFalse();
    });
});

describe('stopContainer()', function (): void {
    it('stops the named container', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->stopContainer('rift-router');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'stop', 'rift-router']);
    });

    it('returns false when docker stop fails', function (): void {
        Process::fake(['*docker*stop*' => Process::result(exitCode: 1)]);

        expect((new Docker())->stopContainer('rift-router'))->toBeFalse();
    });
});

describe('removeContainer()', function (): void {
    it('force-removes the named container', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->removeContainer('rift-router');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'rm', '-f', 'rift-router']);
    });

    it('returns false when docker rm fails', function (): void {
        Process::fake(['*docker*rm*' => Process::result(exitCode: 1)]);

        expect((new Docker())->removeContainer('rift-router'))->toBeFalse();
    });
});

describe('fileExistsIn()', function (): void {
    it('tests for the file inside the container', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->fileExistsIn('rift-router', '/data/root.crt');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'exec', 'rift-router', 'test', '-f', '/data/root.crt']);
    });

    it('returns true when the file exists', function (): void {
        Process::fake(['*' => '']);

        expect((new Docker())->fileExistsIn('rift-router', '/data/root.crt'))->toBeTrue();
    });

    it('returns false when the file does not exist', function (): void {
        Process::fake(['*docker*exec*' => Process::result(exitCode: 1)]);

        expect((new Docker())->fileExistsIn('rift-router', '/data/root.crt'))->toBeFalse();
    });
});

describe('copyFrom()', function (): void {
    it('copies the container path to the host path', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->copyFrom('rift-router', '/data/root.crt', '/tmp/root.crt');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'cp', 'rift-router:/data/root.crt', '/tmp/root.crt']);
    });

    it('returns false when docker cp fails', function (): void {
        Process::fake(['*docker*cp*' => Process::result(exitCode: 1)]);

        expect((new Docker())->copyFrom('rift-router', '/data/root.crt', '/tmp/root.crt'))->toBeFalse();
    });
});

describe('networkExists()', function (): void {
    it('returns true when the network is listed', function (): void {
        Process::fake(['*docker*network*ls*' => "bridge\nrift-router\n"]);

        expect((new Docker())->networkExists('rift-router'))->toBeTrue();
    });

    it('returns false when the network is not listed', function (): void {
        Process::fake(['*docker*network*ls*' => "bridge\n"]);

        expect((new Docker())->networkExists('rift-router'))->toBeFalse();
    });
});

describe('createNetwork()', function (): void {
    it('creates the named network', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->createNetwork('rift-router');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'network', 'create', 'rift-router']);
    });

    it('returns false when docker network create fails', function (): void {
        Process::fake(['*docker*network*create*' => Process::result(exitCode: 1)]);

        expect((new Docker())->createNetwork('rift-router'))->toBeFalse();
    });
});

describe('networkMembers()', function (): void {
    it('returns the trimmed name of every attached container', function (): void {
        Process::fake(['*docker*network*inspect*' => "rift-router\n  myapp-app-1 \n\n"]);

        expect((new Docker())->networkMembers('rift-router'))->toBe(['rift-router', 'myapp-app-1']);
    });

    it('inspects the named network', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->networkMembers('rift-router');

        Process::assertRan(fn (PendingProcess $process): bool => array_slice((array) $process->command, 0, 4) === ['docker', 'network', 'inspect', 'rift-router']);
    });

    it('returns an empty list when no container is attached', function (): void {
        Process::fake(['*docker*network*inspect*' => "\n"]);

        expect((new Docker())->networkMembers('rift-router'))->toBe([]);
    });

    it('returns an empty list when the network cannot be inspected', function (): void {
        Process::fake(['*docker*network*inspect*' => Process::result(output: "rift-router\n", exitCode: 1)]);

        expect((new Docker())->networkMembers('rift-router'))->toBe([]);
    });
});

describe('portHolder()', function (): void {
    it('returns the container publishing a web port', function (string $ports): void {
        Process::fake(['*docker*ps*' => "db\t0.0.0.0:5432->5432/tcp\nweb\t{$ports}\n"]);

        expect((new Docker())->portHolder(new PortBinding()))->toBe('web');
    })->with([
        'port 80' => ['0.0.0.0:80->80/tcp'],
        'port 443' => ['0.0.0.0:443->443/tcp'],
    ]);

    it('returns the first container when several publish web ports', function (): void {
        Process::fake(['*docker*ps*' => "first\t0.0.0.0:80->80/tcp\nsecond\t0.0.0.0:443->443/tcp\n"]);

        expect((new Docker())->portHolder(new PortBinding()))->toBe('first');
    });

    it('returns null when no container publishes port 80 or 443', function (): void {
        Process::fake(['*docker*ps*' => "db\t0.0.0.0:5432->5432/tcp\nvite\t0.0.0.0:8080->5173/tcp\n"]);

        expect((new Docker())->portHolder(new PortBinding()))->toBeNull();
    });

    it('returns null when no container is running', function (): void {
        Process::fake(['*docker*ps*' => '']);

        expect((new Docker())->portHolder(new PortBinding()))->toBeNull();
    });

    it('returns null when docker ps fails', function (): void {
        Process::fake(['*docker*ps*' => Process::result(output: "web\t0.0.0.0:80->80/tcp\n", exitCode: 1)]);

        expect((new Docker())->portHolder(new PortBinding()))->toBeNull();
    });

    it('returns the container publishing a configured web port', function (): void {
        Process::fake(['*docker*ps*' => "web\t0.0.0.0:80->80/tcp\nalt\t0.0.0.0:8443->443/tcp\n"]);

        expect((new Docker())->portHolder(new PortBinding(http: 8080, https: 8443)))->toBe('alt');
    });

    it('returns a container publishing a web port on the router bind address or every address', function (string $ports): void {
        Process::fake(['*docker*ps*' => "web\t{$ports}\n"]);

        expect((new Docker())->portHolder(new PortBinding('127.0.0.2')))->toBe('web');
    })->with([
        'the bind address' => '127.0.0.2:443->443/tcp',
        'every IPv4 address' => '0.0.0.0:443->443/tcp',
        'every IPv6 address' => '[::]:443->443/tcp',
        'every address in the old format' => ':::443->443/tcp',
    ]);

    it('returns null when the web ports are published on another address than the bind address', function (): void {
        Process::fake(['*docker*ps*' => "web\t127.0.0.1:443->443/tcp, 127.0.0.1:80->80/tcp\n"]);

        expect((new Docker())->portHolder(new PortBinding('127.0.0.2')))->toBeNull();
    });
});

describe('containerLabel()', function (): void {
    it('returns the value of the label', function (): void {
        Process::fake(['*docker*inspect*' => "127.0.0.2:8080:8443\n"]);

        expect((new Docker())->containerLabel('rift-router', 'dev.rift.binding'))->toBe('127.0.0.2:8080:8443');
    });

    it('asks docker inspect for the label', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->containerLabel('rift-router', 'dev.rift.binding');

        Process::assertRan(['docker', 'inspect', '--format', '{{index .Config.Labels "dev.rift.binding"}}', 'rift-router']);
    });

    it('returns null when the container has no such label', function (string $output): void {
        Process::fake(['*docker*inspect*' => $output]);

        expect((new Docker())->containerLabel('rift-router', 'dev.rift.binding'))->toBeNull();
    })->with([
        'an empty value' => "\n",
        'a missing key' => "<no value>\n",
    ]);

    it('returns null when the container does not exist', function (): void {
        Process::fake(['*docker*inspect*' => Process::result(output: 'x', exitCode: 1)]);

        expect((new Docker())->containerLabel('rift-router', 'dev.rift.binding'))->toBeNull();
    });
});

describe('containersFromImage()', function (): void {
    it('returns the name of every container built from the image', function (): void {
        Process::fake(['*ancestor=caddy:latest*' => "rift-router\nother-router\n"]);

        expect((new Docker())->containersFromImage('caddy:latest'))->toBe(['rift-router', 'other-router']);
    });

    it('includes stopped containers', function (): void {
        Process::fake(['*' => '']);

        (new Docker())->containersFromImage('caddy:latest');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'ps', '-a', '--filter', 'ancestor=caddy:latest', '--format', '{{.Names}}']);
    });

    it('returns an empty list when docker ps fails', function (): void {
        Process::fake(['*docker*ps*' => Process::result(output: "rift-router\n", exitCode: 1)]);

        expect((new Docker())->containersFromImage('caddy:latest'))->toBe([]);
    });
});

describe('containers()', function (): void {
    it('returns every Compose container docker ps lists', function (): void {
        Process::fake(['*docker*ps*' => composeContainerJson('myapp-app-1', 'myapp', 'app') . "\n" . composeContainerJson('shop-app-1', 'shop', 'app') . "\n"]);

        expect(array_map(fn (Container $container) => $container->name, (new Docker())->containers()))->toBe(['myapp-app-1', 'shop-app-1']);
    });

    it('leaves out containers Compose did not create', function (): void {
        Process::fake(['*docker*ps*' => json_encode(['Names' => 'rift-router', 'Labels' => '']) . "\n"]);

        expect((new Docker())->containers())->toBe([]);
    });

    it('lists stopped containers too', function (): void {
        Process::fake(['*docker*ps*' => '']);

        (new Docker())->containers();

        Process::assertRan(fn (PendingProcess $process) => $process->command === ['docker', 'ps', '-a', '--no-trunc', '--format', '{{json .}}']);
    });

    it('returns nothing when docker ps fails', function (): void {
        Process::fake(['*docker*ps*' => Process::result(output: composeContainerJson('myapp-app-1', 'myapp', 'app'), exitCode: 1)]);

        expect((new Docker())->containers())->toBe([]);
    });
});

/**
 * Build the line docker ps prints for a Compose container.
 *
 * @param  string  $name  The container name.
 * @param  string  $project  The Compose project.
 * @param  string  $service  The Compose service.
 *
 * @return string The JSON line.
 */
function composeContainerJson(string $name, string $project, string $service): string
{
    return (string) json_encode([
        'Names' => $name,
        'Labels' => "com.docker.compose.project={$project},com.docker.compose.service={$service}",
        'State' => 'running',
        'Status' => 'Up 2 hours',
        'Image' => 'nginx',
    ]);
}
