<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ragnarok\Fenrir\Discord;
use Tempcord\Plugins\Plugin;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\TasksPlugin;
use Tempcord\Plugins\Tasks\Tests\Doubles\FakeDiscord;
use Tempcord\Tempcord;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;

#[CoversClass(TasksPlugin::class)]
final class TasksPluginTest extends TestCase
{
    public function test_it_is_a_tempcord_plugin(): void
    {
        $this->assertInstanceOf(
            Plugin::class,
            new TasksPlugin(new Registry(new GenericContainer(), $this->createStub(Logger::class))),
        );
    }

    /**
     * The registry is a Fenrir extension: registering it is what gets its
     * timers started once the gateway is up.
     */
    public function test_booting_registers_the_registry_as_a_discord_extension(): void
    {
        $registry = new Registry(new GenericContainer(), $this->createStub(Logger::class));
        $discord = new FakeDiscord();

        new TasksPlugin($registry)->boot($this->tempcord($discord));

        $this->assertTrue($discord->hasExtension(Registry::class));
        $this->assertSame($registry, $discord->getExtension(Registry::class));
    }

    private function tempcord(Discord $discord): Tempcord
    {
        /*
         * Only the discord property is touched during boot, so the rest is
         * built without a container.
         */
        $tempcord = new \ReflectionClass(Tempcord::class)->newInstanceWithoutConstructor();

        new \ReflectionProperty(Tempcord::class, 'discord')->setValue($tempcord, $discord);

        return $tempcord;
    }
}
