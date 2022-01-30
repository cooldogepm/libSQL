<?php

declare(strict_types=1);

namespace cooldogedev\libSQL\thread;

use cooldogedev\libSQL\interfaces\MySQLCredentialsHolder;
use cooldogedev\libSQL\query\SQLQuery;
use cooldogedev\libSQL\traits\MySQLCredentialsHolderTrait;
use mysqli;
use pocketmine\snooze\SleeperNotifier;
use RuntimeException;

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
        $connection = new mysqli($this->host, $this->username, $this->password, $this->schema, $this->port);

        while (!$connection->ping()) {
            $this->reconnect($connection);
        }

        $query->onRun($connection);
        $query->setFinished(true);

        $connection->close();
        $this->getSleeperNotifier()->wakeupSleeper();
    }

    public function reconnect(mysqli $connection): void
    {
        $connection->connect($this->host, $this->username, $this->password, $this->schema, $this->port);

        if ($connection->connect_error) {
            throw new RuntimeException($connection->connect_error);
        }
    }
}
