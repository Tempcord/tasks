<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use Ragnarok\Fenrir\Discord;
use Tempcord\Plugins\IsPlugin;
use Tempcord\Plugins\Plugin;
use Tempest\Container\Container;
use function Tempest\get;

/**
 * Scheduled Tasks Plugin for Tempcord
 *
 * Provides interval-based and cron-based task scheduling for Discord bots.
 */
final class TasksPlugin implements Plugin
{
    use IsPlugin;

    /**
     * Register plugin services with the container.
     */
    public function register(): void
    {
        // The Registry is already a Singleton, so it will be auto-registered
        // when first accessed via the container
    }

    /**
     * Boot the plugin and register the tasks registry as a Discord extension.
     */
    public function boot(): void
    {
        $discord = get(Discord::class);
        $registry = get(Registry::class);

        // Register the tasks extension with Discord
        $discord->registerExtension($registry);
    }

    /**
     * Define discovery namespaces for this plugin.
     *
     * This tells Tempest's discovery system to scan for attributes
     * in the plugin's namespace.
     */
    public function discoveryNamespaces(): array
    {
        return [
            'Tempcord\\Plugins\\Tasks\\',
        ];
    }
}
