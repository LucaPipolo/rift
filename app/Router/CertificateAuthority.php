<?php

declare(strict_types=1);

namespace App\Router;

use App\Config\RouterConfig;
use App\Docker\Docker;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * The router's local certificate authority and the system trust store.
 */
final readonly class CertificateAuthority
{
    private const string IN_CONTAINER = '/data/caddy/pki/authorities/local/root.crt';

    /**
     * Create a new certificate authority instance.
     *
     * @param  RouterConfig  $router  The router whose CA this is.
     * @param  Docker  $docker  The Docker client.
     * @param  string  $os  The operating system family, as PHP_OS_FAMILY reports it.
     * @param  string  $linuxTrustDirectory  Where Linux keeps trusted CA certificates.
     */
    public function __construct(
        private RouterConfig $router,
        private Docker $docker,
        private string $os = PHP_OS_FAMILY,
        private string $linuxTrustDirectory = '/usr/local/share/ca-certificates',
    ) {}

    /**
     * Get the path of rift's local copy of the root certificate.
     *
     * The copy sits beside the config, not in a temp directory, so trust can be
     * checked while the router is stopped.
     *
     * @return string The certificate path.
     */
    public function path(): string
    {
        $directory = dirname(RouterConfig::path());

        if (! is_dir($directory)) {
            @mkdir($directory, 0o755, true);
        }

        return $directory . '/' . $this->router->caCertFilename();
    }

    /**
     * Wait for Caddy to issue its CA, which it does lazily on first start.
     *
     * @param  int  $seconds  How long to wait.
     *
     * @return bool Whether the CA appeared in time.
     */
    public function waitUntilIssued(int $seconds = 15): bool
    {
        for ($attempt = 0; $attempt < $seconds; $attempt++) {
            if ($this->docker->fileExistsIn($this->router->container(), self::IN_CONTAINER)) {
                return true;
            }

            Sleep::sleep(1);
        }

        return false;
    }

    /**
     * Copy the root certificate out of the router.
     *
     * @return bool Whether the copy succeeded.
     */
    public function extract(): bool
    {
        return $this->docker->copyFrom($this->router->container(), self::IN_CONTAINER, $this->path());
    }

    /**
     * Get the SHA-256 fingerprint of the local copy of the root certificate.
     *
     * @return string|null The fingerprint without colons, or null when unreadable.
     */
    public function fingerprint(): ?string
    {
        if (! is_file($this->path())) {
            return null;
        }

        $result = Process::run([
            'openssl', 'x509', '-in', $this->path(), '-noout', '-fingerprint', '-sha256',
        ]);

        if (! $result->successful()) {
            return null;
        }

        $parts = explode('=', trim($result->output()), 2);

        return isset($parts[1]) ? str_replace(':', '', $parts[1]) : null;
    }

    /**
     * Determine whether this exact CA is already trusted.
     *
     * Matched by fingerprint, because every Caddy CA shares the name
     * "Caddy Local Authority".
     *
     * @return bool Whether the CA is trusted.
     */
    public function isTrusted(): bool
    {
        $fingerprint = $this->fingerprint();

        if ($fingerprint === null) {
            return false;
        }

        if ($this->onMac()) {
            $result = Process::run([
                'security', 'find-certificate', '-a', '-Z', '/Library/Keychains/System.keychain',
            ]);

            return $result->successful()
                && str_contains(strtoupper($result->output()), 'SHA-256 HASH: ' . strtoupper($fingerprint));
        }

        $installed = $this->linuxTrustPath();

        return is_file($installed)
            && file_get_contents($installed) === file_get_contents($this->path());
    }

    /**
     * Add the CA to the system trust store, which requires sudo.
     *
     * @return bool Whether the CA was trusted.
     */
    public function trust(): bool
    {
        if ($this->onMac()) {
            return Process::run([
                'sudo', 'security', 'add-trusted-cert', '-d', '-r', 'trustRoot',
                '-k', '/Library/Keychains/System.keychain', $this->path(),
            ])->successful();
        }

        return Process::run(['sudo', 'cp', $this->path(), $this->linuxTrustPath()])->successful()
            && Process::run(['sudo', 'update-ca-certificates'])->successful();
    }

    /**
     * Cache the sudo credential up front so no later step prompts for it.
     *
     * @param  string  $prompt  The password prompt.
     *
     * @return bool Whether sudo was granted.
     */
    public function primeSudo(string $prompt): bool
    {
        // Without a terminal sudo cannot prompt and fails on its own, which is
        // reported as a refusal rather than as a crash.
        return Process::forever()
            ->tty(SymfonyProcess::isTtySupported())
            ->run(['sudo', '-v', '-p', $prompt])
            ->successful();
    }

    /**
     * Determine whether rift is running on macOS.
     *
     * @return bool Whether the OS is macOS.
     */
    public function onMac(): bool
    {
        return $this->os === 'Darwin';
    }

    /**
     * Get the path the CA is installed at in a Linux trust store.
     *
     * @return string The certificate path.
     */
    private function linuxTrustPath(): string
    {
        return $this->linuxTrustDirectory . '/' . $this->router->caCertFilename();
    }
}
