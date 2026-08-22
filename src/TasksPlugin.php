<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use Tempcord\Plugins\Plugin;
use Tempcord\Tempcord;

/**
 * Interval and cron scheduling for Tempcord bots.
 *
 * The #[Task] attribute is found by TasksDiscovery wherever it appears, in the
 * bot's own code or in another package. This only has to hand the registry to
 * Discord, so its timers start with the gateway.
 */
final readonly class TasksPlugin implements Plugin
{
    public function __construct(
        private Registry $registry,
    ) {}

    public function boot(Tempcord $tempcord): void
    {
        $tempcord->discord->registerExtension($this->registry);
    }
}
