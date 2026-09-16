<?php

declare(strict_types=1);

use App\Config\RouterConfig;
use App\Docker\Docker;
use App\Router\CertificateAuthority;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process as SymfonyProcess;

describe('path()', function (): void {
    it('places the local copy beside the global config', function (): void {
        $home = scratchConfigHome();

        expect((new CertificateAuthority(new RouterConfig(), new Docker()))->path())->toBe($home . '/rift/rift-router-ca.crt');
    });

    it('names the local copy after the router', function (): void {
        $home = scratchConfigHome();

        expect((new CertificateAuthority(new RouterConfig(name: 'other'), new Docker()))->path())->toBe($home . '/rift/other-ca.crt');
    });

    it('creates the config directory when it is missing', function (): void {
        $home = scratchConfigHome();

        (new CertificateAuthority(new RouterConfig(), new Docker()))->path();

        expect($home . '/rift')->toBeDirectory();
    });
});

describe('waitUntilIssued()', function (): void {
    it('looks for the root certificate inside the router container', function (): void {
        Sleep::fake();
        Process::fake(['*' => '']);

        certificateAuthorityOn('Linux')->waitUntilIssued();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'docker', 'exec', 'rift-router', 'test', '-f', '/data/caddy/pki/authorities/local/root.crt',
        ]);
    });

    it('returns true without sleeping when the CA is already issued', function (): void {
        Sleep::fake();
        Process::fake(['*' => '']);

        certificateAuthorityOn('Linux')->waitUntilIssued();

        Sleep::assertNeverSlept();
    });

    it('returns true once Caddy issues the CA', function (): void {
        Sleep::fake();
        Process::fake(['*' => Process::sequence()->push(Process::result(exitCode: 1))->push(Process::result())]);

        expect(certificateAuthorityOn('Linux')->waitUntilIssued())->toBeTrue();
    });

    it('sleeps one second between attempts', function (): void {
        Sleep::fake();
        Process::fake(['*' => Process::sequence()->push(Process::result(exitCode: 1))->push(Process::result())]);

        certificateAuthorityOn('Linux')->waitUntilIssued();

        Sleep::assertSequence([Sleep::for(1)->second()]);
    });

    it('returns false when the CA never appears in time', function (): void {
        Sleep::fake();
        Process::fake(['*' => Process::result(exitCode: 1)]);

        expect(certificateAuthorityOn('Linux')->waitUntilIssued(3))->toBeFalse();
    });

    it('tries once per second it may wait', function (): void {
        Sleep::fake();
        Process::fake(['*' => Process::result(exitCode: 1)]);

        certificateAuthorityOn('Linux')->waitUntilIssued(3);

        Sleep::assertSleptTimes(3);
    });
});

describe('extract()', function (): void {
    it('copies the root certificate out of the router to the local copy', function (): void {
        Process::fake(['*' => '']);
        $ca = certificateAuthorityOn('Linux');

        $ca->extract();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'docker', 'cp', 'rift-router:/data/caddy/pki/authorities/local/root.crt', $ca->path(),
        ]);
    });

    it('returns true when the copy succeeds', function (): void {
        Process::fake(['*' => '']);

        expect(certificateAuthorityOn('Linux')->extract())->toBeTrue();
    });

    it('returns false when the copy fails', function (): void {
        Process::fake(['*docker*cp*' => Process::result(exitCode: 1)]);

        expect(certificateAuthorityOn('Linux')->extract())->toBeFalse();
    });
});

describe('fingerprint()', function (): void {
    it('returns the SHA-256 fingerprint without colons', function (): void {
        Process::fake(['*openssl*' => "sha256 Fingerprint=AB:CD:EF:01\n"]);
        $ca = certificateAuthorityOn('Linux');
        file_put_contents($ca->path(), 'CERT');

        expect($ca->fingerprint())->toBe('ABCDEF01');
    });

    it('reads the fingerprint of the local copy', function (): void {
        Process::fake(['*' => '']);
        $ca = certificateAuthorityOn('Linux');
        file_put_contents($ca->path(), 'CERT');

        $ca->fingerprint();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'openssl', 'x509', '-in', $ca->path(), '-noout', '-fingerprint', '-sha256',
        ]);
    });

    it('returns null without running openssl when there is no local copy', function (): void {
        Process::fake(['*' => '']);

        certificateAuthorityOn('Linux')->fingerprint();

        Process::assertNothingRan();
    });

    it('returns null when there is no local copy', function (): void {
        expect(certificateAuthorityOn('Linux')->fingerprint())->toBeNull();
    });

    it('returns null when openssl cannot read the local copy', function (): void {
        Process::fake(['*openssl*' => Process::result(output: 'sha256 Fingerprint=AB', exitCode: 1)]);
        $ca = certificateAuthorityOn('Linux');
        file_put_contents($ca->path(), 'not a certificate');

        expect($ca->fingerprint())->toBeNull();
    });

    it('returns null when openssl prints no fingerprint', function (): void {
        Process::fake(['*openssl*' => "unexpected output\n"]);
        $ca = certificateAuthorityOn('Linux');
        file_put_contents($ca->path(), 'CERT');

        expect($ca->fingerprint())->toBeNull();
    });
});

describe('isTrusted()', function (): void {
    it('returns false when the local copy has no fingerprint', function (string $os): void {
        Process::fake(['*' => '']);

        expect(certificateAuthorityOn($os)->isTrusted())->toBeFalse();
    })->with([
        'macOS' => ['Darwin'],
        'Linux' => ['Linux'],
    ]);

    it('returns true on macOS when the System keychain holds the fingerprint', function (string $listing): void {
        Process::fake([
            '*openssl*' => "sha256 Fingerprint=AB:CD:EF\n",
            '*find-certificate*' => $listing,
        ]);
        $ca = certificateAuthorityOn('Darwin');
        file_put_contents($ca->path(), 'CERT');

        expect($ca->isTrusted())->toBeTrue();
    })->with([
        'as security prints it' => ["SHA-256 hash: ABCDEF\n"],
        'in another case' => ["sha-256 hash: abcdef\n"],
    ]);

    it('searches the System keychain on macOS', function (): void {
        Process::fake(['*openssl*' => "sha256 Fingerprint=AB:CD:EF\n", '*' => '']);
        $ca = certificateAuthorityOn('Darwin');
        file_put_contents($ca->path(), 'CERT');

        $ca->isTrusted();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'security', 'find-certificate', '-a', '-Z', '/Library/Keychains/System.keychain',
        ]);
    });

    it('returns false on macOS when the System keychain lacks the fingerprint', function (): void {
        Process::fake([
            '*openssl*' => "sha256 Fingerprint=AB:CD:EF\n",
            '*find-certificate*' => "SHA-256 hash: 012345\n",
        ]);
        $ca = certificateAuthorityOn('Darwin');
        file_put_contents($ca->path(), 'CERT');

        expect($ca->isTrusted())->toBeFalse();
    });

    it('returns false on macOS when the System keychain cannot be searched', function (): void {
        Process::fake([
            '*openssl*' => "sha256 Fingerprint=AB:CD:EF\n",
            '*find-certificate*' => Process::result(output: "SHA-256 hash: ABCDEF\n", exitCode: 1),
        ]);
        $ca = certificateAuthorityOn('Darwin');
        file_put_contents($ca->path(), 'CERT');

        expect($ca->isTrusted())->toBeFalse();
    });

    it('returns true on Linux when the trust store holds the same certificate', function (): void {
        Process::fake(['*openssl*' => "sha256 Fingerprint=AB:CD:EF\n"]);
        $store = scratch();
        $ca = certificateAuthorityOn('Linux', $store);
        file_put_contents($ca->path(), 'CERT');
        file_put_contents($store . '/rift-router-ca.crt', 'CERT');

        expect($ca->isTrusted())->toBeTrue();
    });

    it('returns false on Linux when the trust store holds a different certificate', function (): void {
        Process::fake(['*openssl*' => "sha256 Fingerprint=AB:CD:EF\n"]);
        $store = scratch();
        $ca = certificateAuthorityOn('Linux', $store);
        file_put_contents($ca->path(), 'CERT');
        file_put_contents($store . '/rift-router-ca.crt', 'OLD CERT');

        expect($ca->isTrusted())->toBeFalse();
    });

    it('returns false on Linux when the trust store lacks the certificate', function (): void {
        Process::fake(['*openssl*' => "sha256 Fingerprint=AB:CD:EF\n"]);
        $ca = certificateAuthorityOn('Linux');
        file_put_contents($ca->path(), 'CERT');

        expect($ca->isTrusted())->toBeFalse();
    });
});

describe('trust()', function (): void {
    it('adds the local copy to the System keychain as a trusted root on macOS', function (): void {
        Process::fake(['*' => '']);
        $ca = certificateAuthorityOn('Darwin');

        $ca->trust();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'sudo', 'security', 'add-trusted-cert', '-d', '-r', 'trustRoot',
            '-k', '/Library/Keychains/System.keychain', $ca->path(),
        ]);
    });

    it('returns true on macOS when the keychain accepts the certificate', function (): void {
        Process::fake(['*' => '']);

        expect(certificateAuthorityOn('Darwin')->trust())->toBeTrue();
    });

    it('returns false on macOS when the keychain rejects the certificate', function (): void {
        Process::fake(['*add-trusted-cert*' => Process::result(exitCode: 1)]);

        expect(certificateAuthorityOn('Darwin')->trust())->toBeFalse();
    });

    it('copies the local copy into the trust store and refreshes it on Linux', function (): void {
        Process::fake(['*' => '']);
        $store = scratch();
        $ca = certificateAuthorityOn('Linux', $store);

        $ca->trust();

        Process::assertRanInOrder([
            fn (PendingProcess $process): bool => $process->command === ['sudo', 'cp', $ca->path(), $store . '/rift-router-ca.crt'],
            fn (PendingProcess $process): bool => $process->command === ['sudo', 'update-ca-certificates'],
        ]);
    });

    it('returns true on Linux when the trust store is refreshed', function (): void {
        Process::fake(['*' => '']);

        expect(certificateAuthorityOn('Linux')->trust())->toBeTrue();
    });

    it('does not refresh the Linux trust store when the copy fails', function (): void {
        Process::fake(['*sudo*cp*' => Process::result(exitCode: 1), '*' => '']);

        certificateAuthorityOn('Linux')->trust();

        Process::assertNotRan(fn (PendingProcess $process): bool => $process->command === ['sudo', 'update-ca-certificates']);
    });

    it('returns false on Linux when the copy fails', function (): void {
        Process::fake(['*sudo*cp*' => Process::result(exitCode: 1), '*' => '']);

        expect(certificateAuthorityOn('Linux')->trust())->toBeFalse();
    });

    it('returns false on Linux when the trust store cannot be refreshed', function (): void {
        Process::fake(['*update-ca-certificates*' => Process::result(exitCode: 1), '*' => '']);

        expect(certificateAuthorityOn('Linux')->trust())->toBeFalse();
    });
});

describe('primeSudo()', function (): void {
    it('validates sudo with the given prompt', function (): void {
        Process::fake(['*' => '']);

        certificateAuthorityOn('Darwin')->primeSudo('  Password: ');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sudo', '-v', '-p', '  Password: ']);
    });

    it('waits for the password without a timeout', function (): void {
        Process::fake(['*' => '']);

        certificateAuthorityOn('Darwin')->primeSudo('  Password: ');

        Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === null);
    });

    it('attaches the terminal only when one is available', function (): void {
        Process::fake(['*' => '']);

        certificateAuthorityOn('Darwin')->primeSudo('  Password: ');

        Process::assertRan(fn (PendingProcess $process): bool => $process->tty === SymfonyProcess::isTtySupported());
    });

    it('returns true when sudo is granted', function (): void {
        Process::fake(['*' => '']);

        expect(certificateAuthorityOn('Darwin')->primeSudo('  Password: '))->toBeTrue();
    });

    it('returns false when sudo is denied', function (): void {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        expect(certificateAuthorityOn('Darwin')->primeSudo('  Password: '))->toBeFalse();
    });
});

describe('onMac()', function (): void {
    it('returns true on macOS', function (): void {
        expect(certificateAuthorityOn('Darwin')->onMac())->toBeTrue();
    });

    it('returns false on any other system', function (string $os): void {
        expect(certificateAuthorityOn($os)->onMac())->toBeFalse();
    })->with([
        'Linux' => ['Linux'],
        'Windows' => ['Windows'],
        'BSD' => ['BSD'],
    ]);

    it('defaults to the system PHP runs on', function (): void {
        expect((new CertificateAuthority(new RouterConfig(), new Docker()))->onMac())->toBe(PHP_OS_FAMILY === 'Darwin');
    });
});
