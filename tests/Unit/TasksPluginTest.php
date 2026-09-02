<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Plugin;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\TasksPlugin;
use Tempcord\Plugins\Tasks\Tests\Doubles\RecordingLogger;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;
use Tempcord\Tempcord;
use ReflectionClass;

#[CoversClass(TasksPlugin::class)]
final class TasksPluginTest extends TestCase
{
    /**
     * Tempest discovers plugins by the interface, so a plugin that does not
     * declare it is never booted and its tasks never run.
     */
    public function test_it_is_a_tempcord_plugin(): void
    {
        $container = new GenericContainer();
        $container->singleton(Logger::class, new RecordingLogger());

        $plugin = new TasksPlugin(new Registry($container), new RecordingLogger());

        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * A bot that installed the plugin but declared no tasks should say nothing
     * on the way up.
     */
    public function test_a_bot_with_no_tasks_is_not_told_about_it(): void
    {
        $container = new GenericContainer();
        $logger = new RecordingLogger();
        $container->singleton(Logger::class, $logger);

        // Tempcord is final, and boot() only needs something of that type.
        $tempcord = new ReflectionClass(Tempcord::class)->newInstanceWithoutConstructor();

        new TasksPlugin(new Registry($container), $logger)->boot($tempcord);

        $this->assertSame([], $logger->messages);
    }
}
