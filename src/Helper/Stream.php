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
use ReflectionFunction;
use RuntimeException;
use Traversable;

class Stream implements Countable, IteratorAggregate, ArrayAccess {

    private const INVALID_STREAM = "Stream already got consumed";

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

    public static function explode($delimiters, string $value): Stream {
        if(is_array($delimiters)) {
            if(!count($delimiters)) {
                throw new RuntimeException("Empty delimiters array");
            }

            $delimiter = array_shift($delimiters);
            $value = str_replace($delimiters, $delimiter, $value);
            $exploded = explode($delimiter, $value);
        } else {
            $exploded = explode($delimiters, $value);
        }

        return new Stream(array_filter($exploded, function($item) {
            return $item !== "";
        }));
    }

    /**
     * @param Stream|Traversable|array $array
     * @param Stream|Traversable|array ...$others
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

    public function filter(callable $callable): Stream {
        if(isset($this->elements)) {
            $this->elements = array_filter($this->elements, $callable);
        } else {
            throw new Error(self::INVALID_STREAM);
        }
        return $this;
    }

    public function sort(callable $callable): Stream {
        if(isset($this->elements)) {
            usort($this->elements, $callable);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
        return $this;
    }

    public function first() {
        if(isset($this->elements)) {
            return $this->elements[0];
        } else {
            throw new Error($this::INVALID_STREAM);
        }
    }

    public function map(callable $callable): Stream {
        if(isset($this->elements)) {
            $this->elements = array_map($callable, $this->elements);
        } else {
            throw new Error(self::INVALID_STREAM);
        }
        return $this;
    }

    public function filterMap(callable $callable): Stream {
        return $this
            ->map($callable)
            ->filter(function($element) {
                return $element !== null;
            });
    }

    public function keymap(Closure $closure): self {
        if(!isset($this->elements)) {
            throw new Error(self::INVALID_STREAM);
        }

        $mapped = [];

        if(self::params($closure) == 1) {
            foreach($this->elements as $element) {
                [$key, $element] = $closure($element);

                $mapped[$key] = $element;
            }
        } else {
            foreach($this->elements as $key => $element) {
                [$key, $element] = $closure($key, $element);

                $mapped[$key] = $element;
            }
        }

        $this->elements = $mapped;
        return $this;
    }

    public function reduce(callable $callable, $carry) {
        if(isset($this->elements)) {
            return array_reduce($this->elements, $callable, $carry);
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

    public function each(callable $callable): self {
        if(isset($this->elements)) {
            array_walk($this->elements, $callable);
        } else {
            throw new Error(self::INVALID_STREAM);
        }

        return $this;
    }

    public function join($glue): string {
        $result = "";

        $last = array_key_last($this->elements);
        foreach($this->elements as $key => $element) {
            $result .= $element;

            if($key !== $last) {
                $result .= $glue;
            }
        }

        return $result;
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
     * @param callable $callable
     * @return $this
     */
    public function flatMap(callable $callable) {
        if(isset($this->elements)) {
            $mappedArray = $this->map($callable)->toArray();
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

    private static function params(Closure $closure): int {
        return (new ReflectionFunction($closure))->getNumberOfRequiredParameters();
    }

}
