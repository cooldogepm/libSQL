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

use Closure;
use cooldogedev\libPromise\PromisePool;
use cooldogedev\libPromise\thread\ThreadedPromise;
use cooldogedev\libSQL\constant\DataProviderConstants;
use cooldogedev\libSQL\provider\DataProvider;
use cooldogedev\libSQL\provider\implementation\BaseMySQLDataProvider;
use cooldogedev\libSQL\provider\implementation\BaseSQLiteDataProvider;
use cooldogedev\libSQL\query\SQLQuery;
use InvalidArgumentException;
use pocketmine\plugin\PluginBase;
use UnhandledMatchError;

class DatabaseConnector
{
    protected DataProvider $dataProvider;
    protected PromisePool $promisePool;

    public function __construct(protected PluginBase $plugin, array $databaseConfig, ?DataProvider $fallbackDataProvider = null)
    {
        $this->promisePool = new PromisePool($this->getPlugin());

        try {
            $this->dataProvider = match ($databaseConfig["provider"]) {
                DataProviderConstants::getLowercaseMySQL() => new BaseMySQLDataProvider($databaseConfig["mysql"]),
                DataProviderConstants::getLowercaseSQLite() => new BaseSQLiteDataProvider($this->getPlugin()->getDataFolder(), $databaseConfig["sqlite"]["data-file"]),
            };
        } catch (UnhandledMatchError) {
            $fallbackDataProvider ?
                $this->setDataProvider($fallbackDataProvider) :
                throw new InvalidArgumentException("An invalid provider was provided " . $databaseConfig["provider"]);
        }
    }

    public function getPlugin(): PluginBase
    {
        return $this->plugin;
    }

    public function submitQuery(
        SQLQuery $query,
        ?string $table = null,
        bool $appendToPool = true,
        ?Closure $onSuccess = null,
        ?Closure $onError = null
    ): ?ThreadedPromise
    {
        $promise = $this->generateQuery($query, $table, $onSuccess, $onError);

        $querySuccessfullyAppended = false;

        if ($appendToPool) {
            $querySuccessfullyAppended = $this->appendQueryToPool($promise);
        }

        return !$appendToPool || ($querySuccessfullyAppended) ? $promise : null;
    }

    public function generateQuery(SQLQuery $query, ?string $table = null, ?Closure $onSuccess = null, ?Closure $onError = null): ThreadedPromise
    {
        $query->setTable($table);
        $this->getDataProvider()->handleQuerySubmission($query);

        $promise = new ThreadedPromise(
            function () use ($query): mixed {
                $connection = $query->run();
                return $query->handleIncomingConnection($connection);
            },
            $onSuccess
        );

        $onError && $promise->catch($onError);

        return $promise;
    }

    public function getDataProvider(): DataProvider
    {
        return $this->dataProvider;
    }

    public function setDataProvider(DataProvider $dataProvider): void
    {
        $this->dataProvider = $dataProvider;
    }

    public function appendQueryToPool(ThreadedPromise $query): bool
    {
        return $this->getPromisePool()->addPromise($query);
    }

    protected function getPromisePool(): PromisePool
    {
        return $this->promisePool;
    }
}
