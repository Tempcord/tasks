# Tempcord Tasks Plugin

Scheduled tasks plugin for the Tempcord Discord bot framework. Provides cron-based and interval-based task scheduling for your Discord bot.

## Features

- **Interval-based tasks** - Run tasks at regular intervals (every X seconds)
- **Cron-based tasks** - Schedule tasks using standard cron expressions
- **Task statistics** - Track execution times, success rates, and failures
- **Automatic discovery** - Tasks are auto-discovered via PHP attributes
- **Run on boot** - Optionally run tasks immediately when the bot starts

## Installation

```bash
composer require tempcord/tasks-plugin
```

## Usage

### Basic Interval Task

```php
use Tempcord\Plugins\Tasks\Attributes\Task;

class MyTasks
{
    #[Task(interval: 60)] // Run every 60 seconds
    public function myTask(): void
    {
        // Your task logic here
    }
}
```

### Cron-based Task

```php
use Tempcord\Plugins\Tasks\Attributes\Task;

class MyTasks
{
    #[Task(cron: '0 * * * *')] // Run every hour at minute 0
    public function hourlyTask(): void
    {
        // Your task logic here
    }

    #[Task(cron: '@daily')] // Run once per day at midnight
    public function dailyTask(): void
    {
        // Your task logic here
    }
}
```

### Task Options

```php
#[Task(
    interval: 300,           // Run every 5 minutes
    runOnBoot: true,        // Run immediately when bot starts
    name: 'custom-name',    // Custom task name (optional)
    enabled: true           // Enable/disable the task
)]
public function myTask(): void
{
    // Task logic
}
```

### Supported Cron Expressions

Standard 5-field cron format:
```
* * * * *
│ │ │ │ │
│ │ │ │ └─ Day of week (0-6, Sunday = 0)
│ │ │ └─── Month (1-12)
│ │ └───── Day of month (1-31)
│ └─────── Hour (0-23)
└───────── Minute (0-59)
```

Aliases:
- `@yearly` or `@annually` - Run once a year (0 0 1 1 *)
- `@monthly` - Run once a month (0 0 1 * *)
- `@weekly` - Run once a week (0 0 * * 0)
- `@daily` or `@midnight` - Run once a day (0 0 * * *)
- `@hourly` - Run once an hour (0 * * * *)

## Console Commands

List all registered tasks:
```bash
php tempcord tasks:list
```

## Requirements

- PHP 8.5 or higher
- Tempcord Framework ^0.6
- Ragnarok Fenrir ^1
- Tempest Console ^2
- Tempest Core ^2

## License

MIT License
