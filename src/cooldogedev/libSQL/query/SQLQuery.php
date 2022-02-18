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

namespace cooldogedev\libSQL\query;

use cooldogedev\libSQL\context\ClosureContext;
use Threaded;

abstract class SQLQuery extends Threaded
{
    public const SERIALIZABLE_DATA_TYPES = [
        "array" => true,
        "object" => true,
    ];

    protected ?ClosureContext $closureContext = null;

    protected ?string $table = null;

    protected ?string $error = null;

    protected bool $finished = false;

    protected mixed $result = null;
    protected bool $serialized = false;

    public function getClosureContext(): ?ClosureContext
    {
        return $this->closureContext;
    }

    public function setClosureContext(?ClosureContext $closureContext): void
    {
        $this->closureContext = $closureContext;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function setFinished(bool $finished): void
    {
        $this->finished = $finished;
    }

    public function getResult(): mixed
    {
        return $this->isSerialized() ? unserialize($this->result) : $this->result;
    }

    public function setResult(mixed $result): void
    {
        if (isset(SQLQuery::SERIALIZABLE_DATA_TYPES[gettype($result)])) {
            $this->result = serialize($result);
            $this->setSerialized(true);
        } else {
            $this->result = $result;
        }
    }

    public function isSerialized(): bool
    {
        return $this->serialized;
    }

    public function setSerialized(bool $serialized): void
    {
        $this->serialized = $serialized;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function setTable(?string $table): void
    {
        $this->table = $table;
    }

    public function isFailed(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }
}
