<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpKernel\KernelInterface;

class CacheService {
    private static array $CREATED = [];

    public const PERMISSIONS = "permissions";
    public const IMPORTS = "imports";

    /** @Required  */
    public KernelInterface $kernel;

    public function delete(string $namespace, string $key): void {
        $cache = $this->getCache($namespace);
        $cache->deleteItem($key);
    }

    public function set(string $namespace, string $key, $value): void {
        $cache = $this->getCache($namespace);
        $item = $cache->getItem($key);
        $this->setValue($cache, $item, $value);
    }

    public function get(string $namespace, string $key, ?callable $callable) {
        $cache = $this->getCache($namespace);
        $item = $cache->getItem($key);

        $value = null;

        if (!$item->isHit()) {
            if ($callable) {
                $value = $callable();
                $this->setValue($cache, $item, $value);
            }
        }
        else {
            $value = $item->get();
        }

        return $value;
    }

    private function getCache(string $namespace): FilesystemAdapter {
        if (!isset(self::$CREATED[$namespace])) {
            self::$CREATED[$namespace] = new FilesystemAdapter($namespace, 0, $this->kernel->getCacheDir());
        }
        return self::$CREATED[$namespace];
    }

    private function setValue(FilesystemAdapter $cache, CacheItem $item, $value) {
        $item->set($value);
        $cache->save($item);
    }
}
