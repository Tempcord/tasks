<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use React\EventLoop\Loop;
use Tempcord\Plugins\Plugin;
use Tempcord\Tempcord;
use Tempest\Log\Logger;

/**
 * Puts every discovered task on the event loop.
 *
 * A plugin boots after commands and events are bound and before the gateway
 * opens, which is exactly when timers want arming: nothing fires until the loop
 * itself runs, so no task can take a turn against a half-built bot.
 */
final readonly class TasksPlugin implements Plugin
{
    public function __construct(
        private Registry $registry,
        private Logger $logger,
    ) {}

    public function boot(Tempcord $tempcord): void
    {
        $scheduled = $this->registry->start(Loop::get());

        if ($scheduled === []) {
            return;
        }

        $this->logger->info(
            'Scheduled ' . count($scheduled) . ' task(s): ' . implode(', ', $scheduled),
        );
    }
}
