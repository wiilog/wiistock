<?php

namespace App\Service;

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
            $key = "";
        } else {
            $key = ".$key";
        }

        $cache = $this->getCacheDirectory();
        if (!file_exists("$cache/$namespace$key")) {
            $this->set($namespace, $callback());
        }

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
        chmod("$cache/$namespace$key", 0777);
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

        unlink("$cache/$namespace.$key");
    }

}
