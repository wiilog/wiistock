<?php

namespace App\Helper;

use Closure;
use Countable;
use Error;

class Stream implements Countable
{

    private const INVALID_STREAM = 'The toArray method was called on this stream, therefore it is no longer usable.';

    /**
     * @var array $elements
     */
    private $elements;

    /**
     * Stream constructor.
     * @param array $array
     */
    private function __construct(array $array)
    {
        $this->elements = $array;
    }

    /**
     * @param array $array
     * @return Stream
     */
    public static function from(array $array): Stream
    {
        return new Stream($array);
    }

    /**
     * @param Closure $closure
     * @return Stream
     */
    public function filter(Closure $closure): Stream
    {
        if (isset($this->elements)) {
            $this->elements = array_filter($this->elements, $closure);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
        return $this;
    }

    /**
     * @param Closure $closure
     * @return Stream
     */
    public function map(Closure $closure): Stream
    {
        if (isset($this->elements)) {
            $this->elements = array_map($closure, $this->elements);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
        return $this;
    }

    /**
     * @param Closure $closure
     * @param $carry
     * @return mixed|null
     */
    public function reduce(Closure $closure, $carry)
    {
        if (isset($this->elements)) {
            return array_reduce($this->elements, $closure, $carry);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
    }

    /**
     * @param Closure $closure
     * @return bool
     */
    public function each(Closure $closure)
    {
        if (isset($this->elements)) {
            return array_walk($this->elements, $closure);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
    }

    /**
     * @param array $toMerge
     * @return array
     */
    public function merge(array $toMerge): self
    {
        if (isset($this->elements)) {
            $this->elements = array_merge($this->elements, $toMerge);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $streamArray = array_merge($this->elements);
        $this->elements = null;
        return $streamArray;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->elements);
    }
}
