<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Doubles;

use Psr\Log\NullLogger;
use Ragnarok\Fenrir\DataMapper;
use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\EventHandler;
use Ragnarok\Fenrir\Gateway\Connection;
use ReflectionClass;

/**
 * A Discord that can hold extensions and emit events, without a socket or a
 * network behind it.
 */
final class FakeDiscord extends Discord
{
    public function __construct()
    {
        $mapper = new DataMapper(new NullLogger());

        /*
         * Connection's constructor builds a websocket shard, which a unit test
         * has no use for. Only its event emitter matters here.
         */
        $gateway = new ReflectionClass(Connection::class)->newInstanceWithoutConstructor();
        $gateway->events = new EventHandler($mapper);

        $this->gateway = $gateway;
    }
}
