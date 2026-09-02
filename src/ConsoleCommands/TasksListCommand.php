<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\ConsoleCommands;

use Tempcord\Plugins\Tasks\Registry;
use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;

final readonly class TasksListCommand
{
    public function __construct(
        private Registry $registry,
        private Console $console,
    ) {}

    #[ConsoleCommand(name: 'tasks:list', description: 'List all registered scheduled tasks')]
    public function __invoke(): void
    {
        if ($this->registry->count() === 0) {
            $this->console->writeln("<style='fg-gray'>No tasks registered</style>");

            return;
        }

        $this->console->info('Registered Tasks:');

        foreach ($this->registry->all() as $task) {
            $status = $task->enabled ? '<style="fg-blue">✓</style>' : '<style="fg-red">✗</style>';
            $runOnBoot = $task->runOnBoot ? " <style='fg-gray'>(runs on boot)</style>" : '';

            $this->console->writeln(sprintf(
                '  %s <style="fg-cyan">%s</style> - %s%s',
                $status,
                $task->name,
                $task->schedule(),
                $runOnBoot,
            ));

            $this->console->writeln(sprintf(
                "     <style='fg-gray'>%s::%s()</style>",
                $task->handler,
                $task->method->getName(),
            ));
        }

        $this->console->writeln('');
        $this->console->writeln(sprintf('<info>Total: %d task(s)</info>', $this->registry->count()));
    }
}
