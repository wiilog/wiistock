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
    private function __construct(array $array) {
        $this->elements = $array;
    }

    /**
     * @param array $array
     * @param array[] ...$others
     * @return Stream
     */
    public static function from(array $array, array ...$others): Stream {
        return (new Stream($array))->concat(...$others);
    }

    /**
     * @param Stream|array[] ...$streams
     * @return Stream
     */
    public function concat(...$streams): Stream {
        $arrays = array_map(function ($stream) {
            return $stream instanceof Stream
                ? $stream->toArray()
                : $stream;
        }, $streams);
        $this->elements = array_merge($this->elements, ...$arrays);
        return $this;
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
            throw new Error(self::INVALID_STREAM);
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
            throw new Error(self::INVALID_STREAM);
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
            throw new Error(self::INVALID_STREAM);
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
            throw new Error(self::INVALID_STREAM);
        }
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
