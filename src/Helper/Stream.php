<?php

namespace App\Helper;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Error;
use IteratorAggregate;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Traversable;

class Stream implements Countable, IteratorAggregate, ArrayAccess {

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
     * @param Stream|Traversable|array $array
     * @param Stream|iterable|array ...$others
     * @return Stream
     */
    public static function from($array, ...$others): Stream {
        if($array instanceof Stream) {
            $stream = clone $array;
        } else if(is_array($array)) {
            $stream = new Stream($array);
        } else if($array instanceof Traversable) {
            $stream = new Stream(iterator_to_array($array));
        } else {
            if(is_object($array)) {
                $type = get_class($array);
            } else {
                $type = gettype($array);
            }

            throw new RuntimeException("Unsupported type `$type`, expected array or iterable");
        }

        return $stream->concat(...$others);
    }

    /**
     * @param Stream|Iterable|array ...$streams
     * @return Stream
     */
    public function concat(...$streams): Stream {
        $arrays = array_map(function($stream) {
            return $stream instanceof Stream
                ? $stream->toArray()
                : ($stream instanceof Traversable
                    ? iterator_to_array($stream)
                    : $stream);
        }, $streams);

        $this->elements = array_merge($this->elements, ...$arrays);
        return $this;
    }

    public function filter(Closure $closure): Stream {
        if(isset($this->elements)) {
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
    public function sort(Closure $closure): Stream
    {
        if (isset($this->elements)) {
            usort($this->elements, $closure);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
        return $this;
    }
    public function first()
    {
        if (isset($this->elements)) {
            return $this->elements[0];
        } else {
            throw new Error($this::INVALID_STREAM);
        }
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

    public function reduce(Closure $closure, $carry) {
        if(isset($this->elements)) {
            return array_reduce($this->elements, $closure, $carry);
        } else {
            throw new Error(self::INVALID_STREAM);
        }
    }

    public function flatten(): self {
        if(isset($this->elements)) {
            $elements = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($this->elements));

            foreach($iterator as $element) {
                $elements[] = $element;
            }

            $this->elements = $elements;
        } else {
            throw new Error(self::INVALID_STREAM);
        }

        return $this;
    }

    public function unique(): self {
        if(isset($this->elements)) {
            $this->elements = array_unique($this->elements);
        } else {
            throw new Error(self::INVALID_STREAM);
        }

        return $this;
    }

    public function each(Closure $closure): self {
        if(isset($this->elements)) {
            array_walk($this->elements, $closure);
        } else {
            throw new Error(self::INVALID_STREAM);
        }

        return $this;
    }

    public function toArray(): array {
        $streamArray = array_merge($this->elements);
        $this->elements = null;
        return $streamArray;
    }

    public function isEmpty(): bool {
        if(isset($this->elements)) {
            return $this->count() === 0;
        } else {
            throw new Error($this::INVALID_STREAM);
        }
    }

    /**
     * @param Closure $closure
     * @return $this
     */
    public function flatMap(Closure $closure) {
        if(isset($this->elements)) {
            $mappedArray = $this->map($closure)->toArray();
            $this->elements = array_merge(...$mappedArray);
        } else {
            throw new Error($this::INVALID_STREAM);
        }

        return $this;
    }

    public function count(): int {
        return count($this->elements);
    }

    public function getIterator() {
        return new ArrayIterator($this->elements);
    }

    public function offsetExists($offset) {
        return isset($this->elements[$offset]);
    }

    public function offsetGet($offset) {
        return $this->elements[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->elements[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->elements[$offset]);
    }

}
