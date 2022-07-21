<?php

namespace App\Service;

use Exception;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;
use Symfony\Component\HttpKernel\KernelInterface;

class CacheService {

    private const CACHE_FOLDER = "/cache";

    public const PERMISSIONS = "permissions";
    public const IMPORTS = "imports";

    /** @Required  */
    public KernelInterface $kernel;

    private function getCacheDirectory(): string {
        return $this->kernel->getProjectDir() . self::CACHE_FOLDER;
    }

    public function get(string $namespace, $key, ?callable $callback = null) {
        if($callback === null) {
            $callback = $key;
            $originalKey = "";
            $key = "";
        } else {
            $originalKey = $key;
            $key = ".$key";
        }

        $cache = $this->getCacheDirectory();
//        if (!file_exists("$cache/$namespace$key")) {
            $this->set($namespace, $originalKey, $callback());
//        }

        return unserialize(file_get_contents("$cache/$namespace$key"));
    }

    public function set(string $namespace, $key, $value = null) {
        if($value === null) {
            $value = $key;
            $key = "";
        } else {
            $key = ".$key";
        }

        $cache = $this->getCacheDirectory();
        if (!is_dir($cache)) {
            mkdir($cache);
        }

        $handle = fopen("$cache/$namespace$key", 'w+');
        fwrite($handle, serialize($value));
        fclose($handle);
    }

    public function delete(string $namespace, ?string $key = null): void {
        if($key) {
            $key = ".$key";
        }

        $cache = $this->getCacheDirectory();
        if (!is_dir($cache)) {
            mkdir($cache);
        }

        if(file_exists("$cache/$namespace$key")) {
            unlink("$cache/$namespace$key");
        }
    }

    public function clear() {
        $cache = $this->getCacheDirectory();
        if (is_dir($cache)) {
            $this->recursiveRemove($cache);
        }
    }

    private function recursiveRemove($dirname) {
        if (is_dir($dirname)) {
            $dir = new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST) as $object) {
                if ($object->isFile()) {
                    unlink($object);
                } elseif($object->isDir()) {
                    rmdir($object);
                } else {
                    throw new Exception('Unknown object type: '. $object->getFileName());
                }
            }
            rmdir($dirname); // Now remove myfolder
        } else {
            throw new Exception('This is not a directory');
        }
    }

}
