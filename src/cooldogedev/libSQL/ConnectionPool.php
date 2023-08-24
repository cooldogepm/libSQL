<?php

/**
 *  Copyright (c) 2021-2023 cooldogedev
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

use Closure;
use cooldogedev\libSQL\exception\SQLException;
use cooldogedev\libSQL\query\SQLQuery;
use cooldogedev\libSQL\thread\MySQLThread;
use cooldogedev\libSQL\thread\SQLiteThread;
use cooldogedev\libSQL\thread\SQLThread;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use function json_decode;
use function spl_object_id;
use function usort;

final class ConnectionPool
{
    use SingletonTrait {
        reset as protected;
        setInstance as protected;
    }

    /**
     * @var array<int, array{Closure, Closure}>
     */
    protected array $completionHandlers = [];

    /**
     * @var array<int, SQLThread>
     */
    protected array $threads = [];

    public function __construct(protected PluginBase $plugin, array $configuration)
    {
        self::setInstance($this);

        $isMySQL = $configuration["provider"] === "mysql";

        for ($i = 0; $i < $configuration["threads"]; $i++) {
            $thread = $isMySQL ?
                new MySQLThread(... $configuration["mysql"]) :
                new SQLiteThread($plugin->getDataFolder() . $configuration["sqlite"]["path"])
            ;

            $sleeperHandlerEntry = $this->plugin->getServer()->getTickSleeper()->addNotifier(
                function () use ($thread): void {
                    /**
                     * @var SQLQuery|null $query
                     */
                    $query = $thread->getCompleteQueries()->shift();

                    if ($query === null) {
                        return;
                    }

                    $error = $query->getError() !== null ? json_decode($query->getError(), true) : null;
                    $exception = $error !== null ? SQLException::fromArray($error) : null;

                    $identifier = spl_object_id($query);

                    [$successHandler, $errorHandler] = $this->completionHandlers[$identifier];

                    match (true) {
                        $exception === null && $successHandler !== null => $successHandler($query->getResult()),

                        $exception !== null && $errorHandler !== null => $errorHandler($exception),
                        $exception !== null => $this->plugin->getLogger()->logException($exception),

                        default => null,
                    };

                    unset($this->completionHandlers[$identifier]);
                }
            );

            $thread->setSleeperHandlerEntry($sleeperHandlerEntry);
            $thread->start();

            $this->threads[] = $thread;
        }
    }

    public function submit(SQLQuery $query, ?Closure $onSuccess = null, ?Closure $onFail = null): void
    {
        $this->completionHandlers[spl_object_id($query)] = [$onSuccess, $onFail];

        $this->getLeastBusyThread()->addQuery($query);
    }

    protected function getLeastBusyThread(): SQLThread
    {
        $threads = $this->threads;

        usort($threads, static fn (SQLThread $a, SQLThread $b) => $a->getQueries()->count() <=> $b->getQueries()->count());

        return $threads[0];
    }
}
