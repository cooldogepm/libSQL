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

namespace cooldogedev\libSQL\context;

use Closure;
use Threaded;

final class ClosureContext extends Threaded
{
    protected const CLOSURE_CONTEXT_KEY = "CONTEXT";

    protected ?Closure $first;
    protected bool $running;

    public function __construct(array $closures = [])
    {
        $this->first = null;
        $this->running = false;
        $this[ClosureContext::CLOSURE_CONTEXT_KEY] = $closures;
    }

    public static function create(...$closures): ClosureContext
    {
        return new ClosureContext($closures);
    }

    public function first(Closure $closure): ClosureContext
    {
        if ($this->getFirst()) {
            $this->push($this->getFirst());
        }
        $this->first = $closure;
        return $this;
    }

    public function getFirst(): ?Closure
    {
        return $this->first;
    }

    public function push(Closure $closure): ClosureContext
    {
        $this[ClosureContext::CLOSURE_CONTEXT_KEY] = $closure;
        return $this;
    }

    public function invoke(mixed $response): void
    {
        $this->setRunning(true);

        $first = $this->getFirst();

        if ($first) {
            $newValue = $first(
                $response,
                fn() => $this->setRunning(false)
            );

            if ($newValue !== null && $newValue !== $response) {
                $response = $newValue;
            }
        }

        if (count($this->getClosures()) === 0) {
            return;
        }

        foreach ($this->getClosures() as $closure) {
            if (!$this->isRunning()) {
                break;
            }
            $newValue = $closure(
                $response,
                fn() => $this->setRunning(false)
            );
            if ($newValue !== null && $newValue !== $response) {
                $response = $newValue;
            }
        }

        $this->running = false;
        $this->first = null;
    }

    /**
     * @return Closure[]
     */
    public function getClosures(): array
    {
        return (array)$this[ClosureContext::CLOSURE_CONTEXT_KEY];
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function setRunning(bool $running): void
    {
        $this->running = $running;
    }
}
