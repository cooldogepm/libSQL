<?php

declare(strict_types=1);

namespace cooldogedev\libSQL\thread;

use cooldogedev\libSQL\interfaces\SQLiteFileHolder;
use cooldogedev\libSQL\query\SQLQuery;
use cooldogedev\libSQL\traits\SQLiteFileHolderTrait;
use pocketmine\snooze\SleeperNotifier;
use SQLite3;

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

        $query->onRun($connection);
        $query->setFinished(true);

        $connection->close();
        $this->getSleeperNotifier()->wakeupSleeper();
    }
}
