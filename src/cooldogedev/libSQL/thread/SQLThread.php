<?php

declare(strict_types=1);

namespace cooldogedev\libSQL\thread;

use cooldogedev\libSQL\query\SQLQuery;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\Thread;
use Volatile;

abstract class SQLThread extends Thread
{
    protected bool $running;
    protected Volatile $queries;

    public function __construct(protected SleeperNotifier $sleeperNotifier, protected int $index)
    {
        $this->queries = new Volatile();
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getSleeperNotifier(): SleeperNotifier
    {
        return $this->sleeperNotifier;
    }

    public function submitQuery(SQLQuery $query): bool
    {
        $this->synchronized(
            function () use ($query): void {
                $this->queries[] = $query;
                $this->notify();
            }
        );
        return true;
    }

    public function start(int $options = PTHREADS_INHERIT_NONE): bool
    {
        if (parent::start($options)) {
            $this->setRunning(true);
            return true;
        }
        return false;
    }

    public function removeQuery(int $index): bool
    {
        if (!isset($this->queries[$index])) {
            return false;
        }
        unset($this->queries[$index]);
        return true;
    }

    public function quit(): void
    {
        $this->synchronized(
            function (): void {
                $this->setRunning(false);
                $this->quit();
                $this->notify();
            }
        );
        parent::quit();
    }

    public function getQueries(): Volatile
    {
        return $this->queries;
    }

    protected function onRun(): void
    {
        while ($this->isRunning()) {
            $this->synchronized(
                function (): void {
                    while ($this->isRunning() && !$this->isBusy()) {
                        $this->wait();
                    }
                }
            );

            $pendingQueries = $this->getPendingQueries();
            while (count($pendingQueries) > 0) {
                /**
                 * @var SQLQuery $query
                 */
                $query = array_shift($pendingQueries);
                $this->executeQuery($query);
            }
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function setRunning(bool $running): void
    {
        $this->running = $running;
    }

    public function isBusy(): bool
    {
        return count($this->getPendingQueries()) > 0;
    }

    public function getPendingQueries(): array
    {
        $queries = (array)$this->queries;

        return array_filter(
            $queries,
            function (SQLQuery $query): bool {
                return !$query->isFinished();
            }
        );
    }

    abstract public function executeQuery(SQLQuery $query): void;
}
