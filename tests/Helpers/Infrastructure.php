<?php

declare(strict_types=1);

use App\Checkout\Checkout;
use App\Checkout\Instance;
use App\Config\ProjectConfig;
use App\Config\RouterConfig;
use App\Contracts\Network;
use App\Docker\ComposeFile;
use App\Docker\Docker;
use App\Env\SystemPortProbe;
use App\Router\CaddyAdmin;
use App\Router\CertificateAuthority;
use App\Router\Router;

/**
 * Write a Compose file to a scratch directory.
 *
 * @param  string  $yaml  The file contents.
 * @param  string  $name  The filename.
 *
 * @return ComposeFile The compose file.
 */
function composeFileContaining(string $yaml, string $name = 'compose.yaml'): ComposeFile
{
    $path = scratch() . '/' . $name;
    file_put_contents($path, $yaml);

    return new ComposeFile($path);
}

/**
 * Create a main checkout of the `myapp` project rooted at a real directory.
 *
 * @param  string  $root  The checkout root.
 * @param  array<string, mixed>  $config  Overrides for the rift.yaml data.
 *
 * @return Instance The instance.
 */
function myappRootedAt(string $root, array $config = []): Instance
{
    return Instance::make(
        new Checkout(root: $root, commonDir: $root . '/.git', mainRoot: $root, slug: null),
        ProjectConfig::fromArray([
            'name' => 'myapp',
            'domain' => 'myapp.test',
            'app' => ['port' => 80],
            ...$config,
        ]),
        new RouterConfig(),
    );
}

/**
 * Create a router whose admin API is the given client.
 *
 * @param  CaddyAdmin  $admin  The admin API client, usually faked by caddy().
 * @param  RouterConfig  $config  The router's settings.
 * @param  Network|null  $network  This machine's addresses, or null for one holding 127.0.0.2.
 *
 * @return Router The router.
 */
function routerUsingAdmin(CaddyAdmin $admin, RouterConfig $config = new RouterConfig(), ?Network $network = null): Router
{
    $docker = new Docker();

    return new Router(
        $config,
        $admin,
        $docker,
        new CertificateAuthority($config, $docker),
        new SystemPortProbe(),
        $network ?? fakeNetwork(assigned: ['127.0.0.2']),
    );
}

/**
 * Point the global rift config at a fresh scratch directory.
 *
 * @return string The directory XDG_CONFIG_HOME now names.
 */
function scratchConfigHome(): string
{
    $home = scratch() . '/config';

    putenv('XDG_CONFIG_HOME=' . $home);

    return $home;
}

/**
 * Create the default router's certificate authority for an operating system.
 *
 * The global config is moved to a scratch directory first, so the local copy
 * of the certificate never touches the real ~/.config/rift.
 *
 * @param  string  $os  The operating system family.
 * @param  string|null  $linuxTrustDirectory  The Linux trust store, or null for a scratch directory.
 *
 * @return CertificateAuthority The certificate authority.
 */
function certificateAuthorityOn(string $os, ?string $linuxTrustDirectory = null): CertificateAuthority
{
    scratchConfigHome();

    return new CertificateAuthority(new RouterConfig(), new Docker(), $os, $linuxTrustDirectory ?? scratch());
}
