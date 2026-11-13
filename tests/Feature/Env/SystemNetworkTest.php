<?php

declare(strict_types=1);

use App\Env\SystemNetwork;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process as SymfonyProcess;

describe('addresses()', function (): void {
    it('returns every IPv4 address dscacheutil lists on macOS', function (): void {
        Process::fake(['*dscacheutil*' => "name: myapp.test\nipv6_address: ::1\n\nname: myapp.test\nip_address: 127.0.0.1\n\nname: myapp.test\nip_address: 127.0.0.2\n"]);

        expect(new SystemNetwork('Darwin')->addresses('myapp.test'))->toBe(['127.0.0.1', '127.0.0.2']);
    });

    it('returns every IPv4 address getent lists on Linux, once each', function (): void {
        Process::fake(['*getent*' => "127.0.0.1       STREAM myapp.test\n127.0.0.1       DGRAM\n10.0.0.8        STREAM\n"]);

        expect(new SystemNetwork('Linux')->addresses('myapp.test'))->toBe(['127.0.0.1', '10.0.0.8']);
    });

    it('asks the resolver the browser uses', function (string $os, array $command): void {
        Process::fake(['*' => '']);

        new SystemNetwork($os)->addresses('myapp.test');

        Process::assertRan($command);
    })->with([
        'macOS' => ['Darwin', ['dscacheutil', '-q', 'host', '-a', 'name', 'myapp.test']],
        'Linux' => ['Linux', ['getent', 'ahostsv4', 'myapp.test']],
    ]);

    it('bounds the lookup with a timeout', function (): void {
        Process::fake(['*' => '']);

        new SystemNetwork('Darwin')->addresses('myapp.test');

        Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === 3);
    });

    it('returns no addresses when the name has none', function (string $os, string $output, int $exitCode): void {
        Process::fake(['*' => Process::result($output, '', $exitCode)]);

        expect(new SystemNetwork($os)->addresses('myapp.test'))->toBe([]);
    })->with([
        'macOS with no answer' => ['Darwin', '', 0],
        'macOS with only an IPv6 address' => ['Darwin', "name: myapp.test\nipv6_address: ::1\n", 0],
        'Linux with an unknown name' => ['Linux', '', 2],
    ]);

    it('returns no addresses when the resolver does not answer in time', function (): void {
        $timeout = new ProcessTimedOutException(
            new SymfonyTimeoutException(new SymfonyProcess(['dscacheutil']), SymfonyTimeoutException::TYPE_GENERAL),
            new FakeProcessResult(),
        );

        Process::fake(['*' => fn () => $timeout]);

        expect(new SystemNetwork('Darwin')->addresses('myapp.test'))->toBe([]);
    });
});

describe('hasAddress()', function (): void {
    it('treats every 127.0.0.0/8 address as assigned on Linux', function (): void {
        Process::fake();

        expect(new SystemNetwork('Linux')->hasAddress('127.0.0.2'))->toBeTrue();

        Process::assertNothingRan();
    });

    it('finds an address in the interface listing', function (string $os, string $address, string $output, bool $assigned): void {
        Process::fake(['*' => $output]);

        expect(new SystemNetwork($os)->hasAddress($address))->toBe($assigned);
    })->with([
        'a loopback alias on macOS' => ['Darwin', '127.0.0.2', "lo0: flags=8049<UP,LOOPBACK>\n\tinet 127.0.0.1 netmask 0xff000000\n\tinet6 ::1 prefixlen 128\n\tinet 127.0.0.2 netmask 0xff000000\n", true],
        'a missing loopback alias on macOS' => ['Darwin', '127.0.0.2', "\tinet 127.0.0.1 netmask 0xff000000\n\tinet 127.0.0.20 netmask 0xff000000\n\tinet6 ::1 prefixlen 128\n", false],
        'a network address on Linux' => ['Linux', '192.168.1.50', "2: eth0    inet 192.168.1.50/24 brd 192.168.1.255 scope global eth0\n", true],
        'a missing network address on Linux' => ['Linux', '192.168.1.50', "2: eth0    inet 192.168.1.5/24 brd 192.168.1.255 scope global eth0\n", false],
    ]);

    it('lists the interfaces with the platform tool', function (string $os, array $command): void {
        Process::fake(['*' => '']);

        new SystemNetwork($os)->hasAddress('192.168.1.50');

        Process::assertRan($command);
    })->with([
        'macOS' => ['Darwin', ['ifconfig']],
        'Linux' => ['Linux', ['ip', '-4', '-o', 'addr', 'show']],
    ]);

    it('returns false when the interface listing fails', function (string $os): void {
        Process::fake(['*' => Process::result('inet 192.168.1.50/24 netmask', '', 1)]);

        expect(new SystemNetwork($os)->hasAddress('192.168.1.50'))->toBeFalse();
    })->with(['Darwin', 'Linux']);
});

describe('howToAssign()', function (): void {
    it('gives the loopback alias command on macOS', function (): void {
        expect(new SystemNetwork('Darwin')->howToAssign('127.0.0.2'))
            ->toBe('Add it as a loopback alias: `sudo ifconfig lo0 alias 127.0.0.2`.');
    });

    it('asks to assign an address outside loopback, or change the setting', function (string $os): void {
        expect(new SystemNetwork($os)->howToAssign('192.168.1.50'))
            ->toBe('Assign 192.168.1.50 to a network interface, or change `router.bind`.');
    })->with(['Darwin', 'Linux']);
});
