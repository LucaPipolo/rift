<?php

declare(strict_types=1);

use App\Checkout\Checkout;
use App\Checkout\Instance;
use App\Config\ConfigTemplate;
use App\Config\ProjectConfig;
use App\Config\RouterConfig;
use App\Env\EnvFile;
use App\Env\EnvProjection;
use App\Env\EnvProjector;
use App\Env\PortResolver;
use Symfony\Component\Yaml\Yaml;

/**
 * Parse a myapp rift.yaml with the given extra keys.
 *
 * @param  array<array-key, mixed>  $extra  The keys to add or override.
 *
 * @return ProjectConfig The parsed config.
 */
function myappConfig(array $extra = []): ProjectConfig
{
    return ProjectConfig::fromArray([
        'name' => 'myapp',
        'domain' => 'myapp.test',
        'app' => ['port' => 80],
        ...$extra,
    ]);
}

/**
 * Write a rift.yaml into a fresh scratch checkout.
 *
 * @param  string  $contents  The rift.yaml contents.
 *
 * @return string The checkout root.
 */
function checkoutWithRiftYaml(string $contents): string
{
    $root = scratch();
    file_put_contents($root . '/rift.yaml', $contents);

    return $root;
}

/**
 * Write a global router config file into a fresh scratch directory.
 *
 * @param  string  $contents  The config.yaml contents.
 *
 * @return string The config file path.
 */
function routerConfigFile(string $contents): string
{
    $path = scratch() . '/config.yaml';
    file_put_contents($path, $contents);

    return $path;
}

/**
 * Parse a rendered template back the way `rift up` will.
 *
 * @param  ConfigTemplate  $template  The template to render.
 *
 * @return ProjectConfig The parsed config.
 */
function templateRoundTrip(ConfigTemplate $template): ProjectConfig
{
    $data = Yaml::parse($template->render());

    assert(is_array($data));

    return ProjectConfig::fromArray($data);
}

/**
 * Create a main checkout.
 *
 * @param  string  $root  The checkout's root directory.
 *
 * @return Checkout The main checkout.
 */
function primaryCheckout(string $root = '/Sites/myapp'): Checkout
{
    return new Checkout($root, $root . '/.git', $root, null);
}

/**
 * Create a worktree checkout beside a main checkout.
 *
 * @param  string  $name  The worktree directory name.
 * @param  string  $main  The main checkout's root directory.
 *
 * @return Checkout The worktree.
 */
function worktreeCheckout(string $name, string $main = '/Sites/myapp'): Checkout
{
    return new Checkout(
        root: '/Sites/trees/' . $name,
        commonDir: $main . '/.git',
        mainRoot: $main,
        slug: Checkout::slugify($name),
    );
}

/**
 * Create an instance in a scratch checkout seeded with an .env.example.
 *
 * @param  array<string, mixed>  $config  The rift.yaml data.
 * @param  string|null  $slug  The worktree slug, or null for the main checkout.
 * @param  string  $example  The .env.example contents.
 * @param  list<string>  $siblings  The roots of the project's other checkouts.
 *
 * @return Instance The instance.
 */
function scratchInstance(
    array $config,
    ?string $slug = null,
    string $example = "APP_ENV=local\n",
    array $siblings = [],
): Instance {
    $main = scratch();
    file_put_contents($main . '/.env.example', $example);

    $root = $main;

    if ($slug !== null) {
        $root = scratch();
        copy($main . '/.env.example', $root . '/.env.example');
    }

    return Instance::make(
        new Checkout(
            root: $root,
            commonDir: $main . '/.git',
            mainRoot: $main,
            slug: $slug,
            siblings: $siblings,
        ),
        ProjectConfig::fromArray($config),
        new RouterConfig(),
    );
}

/**
 * Project an instance's .env with no busy ports and reopen the result.
 *
 * @param  Instance  $instance  The checkout to project.
 *
 * @return array{0: EnvProjection, 1: EnvFile} The projection and the written .env.
 */
function projectEnv(Instance $instance): array
{
    $projection = new EnvProjector(new PortResolver(probe()))->project($instance);

    return [$projection, EnvFile::at($instance->envPath())];
}
