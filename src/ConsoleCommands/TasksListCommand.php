<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\ConsoleCommands;

use Tempcord\Plugins\Tasks\Registry;
use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;

final readonly class TasksListCommand
{
    public function __construct(
        private Registry $tasksRegistry,
        private Console  $console
    ) {}

    #[ConsoleCommand(name: 'tasks:list', description: 'List all registered scheduled tasks')]
    public function __invoke(): void
    {
        $tasks = $this->tasksRegistry;

        if ($tasks->count() === 0) {
            $this->console->writeln("<style='fg-gray'>No tasks registered</style>");
            return;
        }

        $this->console->info('Registered Tasks:');

        foreach ($tasks->getAllTasks() as $index => $task) {
            $status = $task->enabled ? '<style="fg-blue">✓</style>' : '<style="fg-red">✗</style>';
            $name = $task->getName();
            $schedule = $task->getScheduleDescription();
            $runOnBoot = $task->runOnBoot ? " <style='fg-gray'>(runs on boot)</style>" : '';
            $class = $task->reflector?->getDeclaringClass()->getShortName() ?? 'Unknown';

            $this->console->writeln(sprintf(
                '  %s <style="fg-cyan">%s</style> - %s%s',
                $status,
                $name,
                $schedule,
                $runOnBoot
            ));

            $this->console->writeln(sprintf(
                "     <style='fg-gray'>%s::%s()</style>",
                $class,
                $task->reflector?->getName() ?? 'unknown'
            ));

            if ($index < $tasks->count() - 1) {
                $this->console->writeln('');
            }
        }

        $this->console->writeln('');
        $this->console->writeln(sprintf(
            '<info>Total: %d task(s)</info>',
            $tasks->count()
        ));
    }
}
