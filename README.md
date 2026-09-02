# Tempcord Tasks

Scheduled tasks for the [Tempcord](https://github.com/Tempcord/framework) Discord bot framework:
work the bot does on a timer rather than in reply to anything — sweeping rows that have run
their course, lifting blocks that have expired, polling a service with no gateway event of
its own.

## Installation

```bash
composer require tempcord/tasks
```

## Declaring a task

On the class, the way every other Tempcord handler is declared:

```php
use Tempcord\Plugins\Tasks\Attributes\Task;

#[Task(interval: 10)]
final readonly class SweepTemporaryMessages
{
    public function __construct(private TempMessages $messages) {}

    public function __invoke(): void
    {
        $this->messages->sweep();
    }
}
```

Or on a method, when several chores belong together and would only be split across classes
to satisfy the attribute:

```php
final readonly class Housekeeping
{
    #[Task(interval: 10)]
    public function sweepMessages(): void {}

    #[Task(cron: '@daily')]
    public function pruneStatistics(): void {}
}
```

That is the whole registration. Tasks are discovered like commands and listeners, are built
by the container so they may take whatever dependencies they need, and are put on the event
loop before the gateway opens.

## What the plugin guarantees

A timer is less forgiving than an event listener: it fires again whether or not the last
turn finished or threw, forever. Three things are handled so you do not write them into
every task.

- **A task that throws is logged and keeps its place.** Without that the exception travels
  into the event loop, and the usual result is a cancelled timer and nothing ever swept
  again — silently, because the bot carries on answering commands and looks healthy.
- **A task is never started alongside itself.** A turn still running when the next is due is
  skipped, and the skip logged. Otherwise a task slower than its own interval makes every
  following turn slower until nothing else gets a look in.
- **Each turn runs in a fiber**, so a task may `await` the REST API exactly as a command
  handler does.

An ordinary turn is logged at debug rather than info: a task running every ten seconds would
otherwise write eight thousand lines a day saying nothing happened.

## Options

```php
#[Task(
    interval: 300,          // run every this many seconds
    cron: '0 * * * *',      // or on a cron schedule; the two are mutually exclusive
    runOnBoot: true,        // also take a turn as the bot starts, rather than waiting out
                            // the first interval
    name: 'custom-name',    // what to call it in the logs and in tasks:list
    enabled: true,          // false leaves it out of the schedule entirely
)]
```

A task with neither an interval nor a cron expression, with both, or with an interval under
a second is refused while the bot is starting rather than at the moment it would have run.
So is a cron expression that cannot be read.

Left unnamed, a task is called after its class — `SweepTemporaryMessages` — or after the
class and method for a method-level task — `Housekeeping::sweepMessages`. The class is part
of it because two classes may well have a `sweep()`, and tasks sharing a name would share
their statistics and could not be cancelled apart.

### The first turn

The first turn comes after the interval unless `runOnBoot` says otherwise. A task is a
repeating chore; one-off startup work — reconciling against whatever changed while the bot
was down — usually belongs in a plugin's `boot()`, where its ordering against everything
else is visible.

## Cron expressions

Standard five-field format:

```
* * * * *
│ │ │ │ │
│ │ │ │ └─ day of week (0-6, Sunday = 0)
│ │ │ └─── month (1-12)
│ │ └───── day of month (1-31)
│ └─────── hour (0-23)
└───────── minute (0-59)
```

Wildcards (`*`), lists (`1,15,30`), ranges (`9-17`) and steps (`*/5`, `1-10/2`) are
supported. A step over a range counts from where the range starts, so `1-10/2` is
1,3,5,7,9.

Aliases: `@yearly` / `@annually`, `@monthly`, `@weekly`, `@daily` / `@midnight`, `@hourly`.

**Both day fields restricted means either.** `0 0 1 * 1` runs on the first of the month
*and* on every Monday, which is what cron does and what anyone writing it expects. When only
one of the two names particular days, it simply narrows.

A cron task is armed one turn at a time, for the exact wait until the next matching minute,
and re-armed from the turn just taken. Waking every minute to ask whether it is time yet
drifts, and once the drift crosses a minute boundary a matching minute is stepped over and
the task silently does not run that hour.

## Console

```bash
php tempcord tasks:list
```

Lists every registered task with its schedule and where it is declared.

## Reaching the schedule at runtime

```php
use function Tempcord\Plugins\Tasks\tasks;
use function Tempcord\Plugins\Tasks\cancelTask;
use function Tempcord\Plugins\Tasks\taskStats;

taskStats()['SweepTemporaryMessages']->getSuccessRate();
cancelTask('SweepTemporaryMessages');
```

`Registry` is injectable, and is the better way in anything the container builds.

## Requirements

- PHP 8.5
- Tempcord Framework >= 0.10

## License

MIT
