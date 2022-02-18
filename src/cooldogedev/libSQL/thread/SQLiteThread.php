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

use cooldogedev\libSQL\interfaces\SQLiteFileHolder;
use cooldogedev\libSQL\query\SQLQuery;
use cooldogedev\libSQL\traits\SQLiteFileHolderTrait;
use pocketmine\snooze\SleeperNotifier;
use SQLite3;
use Throwable;

final class SQLiteThread extends SQLThread implements SQLiteFileHolder
{
    use SQLiteFileHolderTrait;

    public function __construct(SleeperNotifier $sleeperNotifier, int $index, string $file, string $path)
    {
        parent::__construct($sleeperNotifier, $index);
        $this->setPath($path);
        $this->setFile($file);
    }

    public function executeQuery(SQLQuery $query): void
    {
        $connection = new SQLite3($this->getPath() . $this->getFile());
        $connection->busyTimeout(60000);

        try {
            $query->onRun($connection);
        } catch (Throwable $e) {
            $query->setFinished(true);
            $query->setError($e->getMessage());
        } finally {
            $query->setFinished(true);

            $connection->close();

            $this->getSleeperNotifier()->wakeupSleeper();
        }
    }
}
