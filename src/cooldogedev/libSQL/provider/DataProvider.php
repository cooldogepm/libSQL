<?php

declare(strict_types=1);

namespace cooldogedev\libSQL\provider;

use cooldogedev\libSQL\query\SQLQuery;

interface DataProvider
{
    public function getName(bool $lowercase = false): string;

    public function handleQuerySubmission(SQLQuery $query): void;
}
