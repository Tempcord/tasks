<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Compiler;

use RuntimeException;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Definitions\TaskDefinition;
use Tempcord\Plugins\Tasks\Support\CronExpression;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Throwable;

final readonly class TaskCompiler
{
    /**
     * A task declared on the class itself, which is run through __invoke the
     * way every other Tempcord handler is.
     */
    public function compileClass(ClassReflector $class, Task $task): TaskDefinition
    {
        if (!$class->getReflection()->hasMethod('__invoke')) {
            throw new RuntimeException(
                'Class [' . $class->getName() . '] should declare an __invoke method',
            );
        }

        return $this->build($task, $class, $class->getMethod('__invoke'), $class->getShortName());
    }

    /**
     * A task declared on one method of a class that holds several.
     */
    public function compileMethod(ClassReflector $class, MethodReflector $method, Task $task): TaskDefinition
    {
        return $this->build(
            $task,
            $class,
            $method,
            $class->getShortName() . '::' . $method->getName(),
        );
    }

    private function build(
        Task $task,
        ClassReflector $class,
        MethodReflector $method,
        string $defaultName,
    ): TaskDefinition {
        /*
         * Parsed here rather than when the timer is armed, so an expression
         * nobody can read fails while the bot is starting and says which task
         * it came from — not four hours later inside a timer callback.
         */
        if ($task->cron !== null) {
            try {
                new CronExpression($task->cron);
            } catch (Throwable $throwable) {
                throw new RuntimeException(
                    'Task [' . $defaultName . '] has an unreadable cron expression: ' . $throwable->getMessage(),
                    previous: $throwable,
                );
            }
        }

        return new TaskDefinition(
            name: $task->name ?? $defaultName,
            handler: $class->getName(),
            method: $method,
            interval: $task->interval,
            cron: $task->cron,
            runOnBoot: $task->runOnBoot,
            enabled: $task->enabled,
        );
    }
}
