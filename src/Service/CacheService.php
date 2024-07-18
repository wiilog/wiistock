<?php

namespace App\Service;

use App\Helper\FileSystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

class CacheService
{

    private const CACHE_FOLDER = "/cache";

    public const COLLECTION_PERMISSIONS = "permissions";
    public const COLLECTION_TRANSLATIONS = "translations";
    public const COLLECTION_LANGUAGES = "languages";
    public const COLLECTION_EXPORTS = "exports";
    public const COLLECTION_IMPORTS = "imports";
    public const COLLECTION_SETTINGS = "settings";

    private FileSystem $filesystem;
    private string $absoluteCachePath;

    public function __construct(KernelInterface $kernel)
    {
        $this->absoluteCachePath = $kernel->getProjectDir() . self::CACHE_FOLDER;
        $this->filesystem = new FileSystem($this->absoluteCachePath);
    }

    public function get(string $namespace, string $key, ?callable $callback = null): mixed
    {
        $cacheExists = $this->filesystem->exists("$namespace/$key");

        if ($cacheExists || $callback) {
            if (!$cacheExists) {
                $this->set($namespace, $key, $callback());
            }

            try {
                $result = unserialize($this->filesystem->getContent("$namespace/$key"));
            }
            catch(Throwable) {
                $result = null;
            }

        }
        else {
            $result = null;
        }

        return $result;

    }

    public function set(string $namespace, string $key, mixed $value = null): void
    {
        if (!$this->filesystem->isDir()) {
            $this->clear();
        }

        if(!$this->filesystem->isFile("$namespace/$key")) {
            $this->filesystem->remove("$namespace/$key");
        }

        $this->filesystem->dumpFile("$namespace/$key", serialize($value));
    }

    public function delete(string $namespace, ?string $key = null): void
    {
        if ($this->filesystem->exists("$namespace/$key")) {
            $this->filesystem->remove("$namespace/$key");
        }
    }

    public function clear(): void
    {
        if ($this->filesystem->exists()) {

            $dirFinder = new Finder();
            $dirFinder
                ->depth('== 0')
                ->ignoreDotFiles(true)
                ->in($this->absoluteCachePath);

            if ($dirFinder->hasResults()) {
                foreach ($dirFinder as $directory) {
                    $fs = new FileSystem($directory);
                    $fs->remove();
                }
            }

        }
    }

}
