<?php

namespace App\Helper;

use Closure;
use Countable;
use Doctrine\Common\Collections\ArrayCollection;
use Error;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use RuntimeException;

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

    public static function from($array): Stream
    {
        if(is_array($array)) {
            return new Stream($array);
        } else if(is_iterable($array)) {
            return new Stream(iterator_to_array($array));
        } else {
            if(is_object($array)) {
                $type = get_class($array);
            } else {
                $type = gettype($array);
            }

            throw new RuntimeException("Unsupported type `$type`, expected array or iterable");
        }
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

    public function flatten(): self {
        $elements = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($this->elements));

        foreach($iterator as $element) {
            $elements[] = $element;
        }

        $this->elements = $elements;
        return $this;
    }

    public function unique(): self {
        $this->elements = array_unique($this->elements);
        return $this;
    }

    public function each(Closure $closure): self {
        if (isset($this->elements)) {
            array_walk($this->elements, $closure);
        } else {
            throw new Error($this::INVALID_STREAM);
        }
    }

    public function isEmpty(): bool
    {
        if (isset($this->elements)) {
            return $this->count() === 0;
        } else {
            throw new Error($this::INVALID_STREAM);
        }
    }

    public function toArray(): array
    {
        $streamArray = array_merge($this->elements);
        $this->elements = null;
        return $streamArray;
    }

    public function count(): int
    {
        return count($this->elements);
    }
}
