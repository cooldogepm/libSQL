<?php

/**
 *  Copyright (c) 2021-2022 cooldogedev
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

namespace cooldogedev\libSQL\thread;

use cooldogedev\libSQL\interfaces\MySQLCredentialsHolder;
use cooldogedev\libSQL\query\SQLQuery;
use cooldogedev\libSQL\traits\MySQLCredentialsHolderTrait;
use mysqli;
use pocketmine\snooze\SleeperNotifier;
use RuntimeException;
use Throwable;

final class MySQLThread extends SQLThread implements MySQLCredentialsHolder
{
    use MySQLCredentialsHolderTrait;

    public function __construct(SleeperNotifier $sleeperNotifier, int $index, array $credentials)
    {
        parent::__construct($sleeperNotifier, $index);
        $this->setAll(... $credentials);
    }

    public function executeQuery(SQLQuery $query): void
    {
        try {
            $connection = new mysqli($this->host, $this->username, $this->password, $this->schema, $this->port);

            while (!$connection->ping()) {
                $this->reconnect($connection);
            }

            $query->onRun($connection);

            $query->setFinished(true);

            $connection->close();

            $this->getSleeperNotifier()->wakeupSleeper();

        } catch (Throwable $e) {
            $query->setError($e->getMessage());
        }
    }

    public function reconnect(mysqli $connection): void
    {
        $connection->connect($this->host, $this->username, $this->password, $this->schema, $this->port);

        if ($connection->connect_error) {
            throw new RuntimeException($connection->connect_error);
        }
    }
}
