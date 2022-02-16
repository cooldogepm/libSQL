<?php

/**
 *  Copyright (c) 2021 cooldogedev
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

declare(strict_types=1);

namespace cooldogedev\libSQL;

use cooldogedev\libSQL\context\ClosureContext;
use cooldogedev\libSQL\query\SQLQuery;
use cooldogedev\libSQL\thread\MySQLThread;
use cooldogedev\libSQL\thread\SQLiteThread;
use cooldogedev\libSQL\thread\SQLThread;
use InvalidArgumentException;
use pocketmine\plugin\PluginBase;
use pocketmine\snooze\SleeperNotifier;

final class ConnectionPool
{
    public const DATA_PROVIDER_MYSQL = "mysql";
    public const DATA_PROVIDER_SQLITE = "sqlite";

    /**
     * @var SQLThread[]
     */
    protected array $threads;
    /**
     * @var SQLQuery[][]
     */
    protected array $refs;

    public function __construct(protected PluginBase $plugin, array $databaseConfig)
    {
        $this->threads = [];
        $this->refs = [];

        switch ($databaseConfig["provider"]) {
            case ConnectionPool::DATA_PROVIDER_MYSQL:
                for ($i = 0; $i < $databaseConfig["threads"]; $i++) {
                    $sleeperNotifier = new SleeperNotifier();
                    $this->registerThread(new MySQLThread($sleeperNotifier, $i, $databaseConfig["mysql"]), $sleeperNotifier);
                }
                break;
            case ConnectionPool::DATA_PROVIDER_SQLITE:
                for ($i = 0; $i < $databaseConfig["threads"]; $i++) {
                    $sleeperNotifier = new SleeperNotifier();
                    $this->registerThread(new SQLiteThread($sleeperNotifier, $i, $databaseConfig["sqlite"]["file"], $this->getPlugin()->getDataFolder()), $sleeperNotifier);
                }
                break;
            default:
                throw new InvalidArgumentException("Invalid database provider");
        }
    }

    protected function registerThread(SQLThread $thread, SleeperNotifier $sleeperNotifier): void
    {
        $this->getPlugin()->getServer()->getTickSleeper()->addNotifier($sleeperNotifier, function () use ($thread): void {

            foreach ($this->getRefs($thread->getIndex()) as $key => $ref) {

                if (!$ref->isFinished()) {
                    continue;
                }

                $context = $ref->getClosureContext();

                $thread->removeQuery($key);

                $context?->invoke($ref->getResult());

                $this->removeRef($thread->getIndex(), $key);
            }
        });

        $this->threads[$thread->getIndex()] = $thread;
        $thread->start();
    }

    public function getPlugin(): PluginBase
    {
        return $this->plugin;
    }

    /**
     * @return SQLQuery[]
     */
    public function getRefs(int $index): array
    {
        return $this->refs[$index];
    }

    public function removeRef(int $thread, int $index): bool
    {
        if (!$this->isRef($thread, $index)) {
            return false;
        }
        unset($this->refs[$thread][$index]);
        return true;
    }

    public function isRef(int $thread, int $index): bool
    {
        return isset($this->refs[$thread][$index]);
    }

    public function getRef(int $thread, int $index): ?SQLQuery
    {
        return $this->refs[$thread][$index] ?? null;
    }

    public function submit(SQLQuery $query, ?string $table = null, ?ClosureContext $context = null): ?SQLQuery
    {
        $table && $query->setTable($table);
        $context && $query->setClosureContext($context);

        $thread = $this->getFreeThread();

        $this->addRef($thread->getIndex(), $query);
        $thread->submitQuery($query);

        return $query;
    }

    public function getFreeThread(): SQLThread
    {
        if (count($this->getThreads()) < 2) {
            return $this->getThreads()[array_key_first($this->getThreads())];
        }

        $freeThread = null;

        foreach ($this->getThreads() as $thread) {
            if (!$thread->isBusy()) {
                $freeThread = $thread;
                break;
            }

            if (!$freeThread) {
                $freeThread = $thread;
                continue;
            }

            if (count($freeThread->getQueries()) > count($thread->getQueries())) {
                $freeThread = $thread;
            }
        }

        return $freeThread;
    }

    /**
     * @return SQLThread[]
     */
    public function getThreads(): array
    {
        return $this->threads;
    }

    public function addRef(int $thread, SQLQuery $query): void
    {
        $this->refs[$thread][] = $query;
    }
}
