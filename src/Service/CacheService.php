<?php

namespace App\Service;

use App\Helper\FileSystem;
use Symfony\Component\HttpKernel\KernelInterface;

class CacheService
{

    private const CACHE_FOLDER = "/cache";

    public const PERMISSIONS = "permissions";
    public const TRANSLATIONS = "translations";

    private FileSystem $filesystem;

    public function __construct(KernelInterface $kernel)
    {
        $this->filesystem = new FileSystem($kernel->getProjectDir() . self::CACHE_FOLDER);
    }

    public function get(string $namespace, string $key, ?callable $callback = null): mixed
    {
        if ($callback && !$this->filesystem->exists("$namespace.$key")) {
            $this->set($namespace, $key, $callback());
        }

        return unserialize($this->filesystem->getContent("$namespace.$key"));
    }

    public function set(string $namespace, string $key, mixed $value = null): void
    {
        if (!$this->filesystem->isDir()) {
            $this->filesystem->remove();
        }

        if (!$this->filesystem->isDir($namespace)) {
            $this->filesystem->remove($namespace);
            $this->filesystem->mkdir($namespace);
        }

        $this->filesystem->dumpFile("$namespace.$key", serialize($value));
    }

    public function delete(string $namespace, ?string $key = null): void
    {
        if ($this->filesystem->exists("$namespace.$key")) {
            $this->filesystem->remove("$namespace.$key");
        }
    }

    public function clear(): void
    {
        if ($this->filesystem->exists()) {
            $this->filesystem->remove();
            $this->filesystem->mkdir();
        }
    }

}
