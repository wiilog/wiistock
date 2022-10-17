<?php

namespace App\Helper;

use Symfony\Component\Filesystem\Filesystem as SymfonyFileSystem;

class FileSystem
{

    private SymfonyFileSystem $filesystem;

    private ?string $root;

    public function __construct(?string $root = null) {
        $this->filesystem = new SymfonyFileSystem();
        $this->root = $root;
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function setRoot(string $root): void
    {
        $this->root = $root;
    }

    private function appendRoot(iterable|string|null $path): iterable|string {
        if(!$path) {
            return $this->root;
        }

        if(is_iterable($path)) {
            return array_map(fn(string $p) => "$this->root/$p", (array)$path);
        } else {
            return "$this->root/$path";
        }
    }

    public function isFile(?string $path = null): bool {
        return is_file($this->appendRoot($path));
    }

    public function isDir(?string $path = null): bool {
        return is_dir($this->appendRoot($path));
    }

    public function getContent(?string $path = null): string {
        return file_get_contents($this->appendRoot($path));
    }

    public function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void
    {
        $this->filesystem->copy($this->appendRoot($originFile), $this->appendRoot($targetFile), $overwriteNewerFiles);
    }

    public function mkdir(iterable|string|null $dirs = null, int $mode = 0777): void
    {
        $this->filesystem->mkdir($this->appendRoot($dirs), $mode);
    }

    public function exists(iterable|string|null $files = null): bool
    {
        return $this->filesystem->exists($this->appendRoot($files));
    }

    public function touch(iterable|string $files, int $time = null, int $atime = null): void
    {
        $this->filesystem->touch($this->appendRoot($files), $time, $atime);
    }

    public function remove(iterable|string|null $files = null): void
    {
        $this->filesystem->remove($this->appendRoot($files));
    }

    public function chmod(iterable|string $files, int $mode, int $umask = 0000, bool $recursive = false): void
    {
        $this->filesystem->chmod($this->appendRoot($files), $mode, $umask, $recursive);
    }

    public function chown(iterable|string $files, int|string $user, bool $recursive = false): void
    {
        $this->filesystem->chown($this->appendRoot($files), $user, $recursive);
    }

    public function chgrp(iterable|string $files, int|string $group, bool $recursive = false): void
    {
        $this->filesystem->chgrp($this->appendRoot($files), $group, $recursive);
    }

    public function rename(string $origin, string $target, bool $overwrite = false): void
    {
        $this->filesystem->rename($this->appendRoot($origin), $this->appendRoot($target), $overwrite);
    }

    public function symlink(string $originDir, string $targetDir, bool $copyOnWindows = false): void
    {
        $this->filesystem->symlink($this->appendRoot($originDir), $this->appendRoot($targetDir), $copyOnWindows);
    }

    public function hardlink(string $originFile, iterable|string $targetFiles): void
    {
        $this->filesystem->hardlink($this->appendRoot($originFile), $this->appendRoot($targetFiles));
    }

    public function readlink(string $path, bool $canonicalize = false): ?string
    {
        return $this->filesystem->readlink($this->appendRoot($path), $canonicalize);
    }

    public function makePathRelative(string $endPath, string $startPath): string
    {
        return $this->filesystem->makePathRelative($this->appendRoot($endPath), $this->appendRoot($startPath));
    }

    public function mirror(string $originDir, string $targetDir, \Traversable $iterator = null, array $options = []): void
    {
        $this->filesystem->mirror($this->appendRoot($originDir), $this->appendRoot($targetDir), $iterator, $options);
    }

    public function tempnam(string $dir, string $prefix, string $suffix = ''): string
    {
        return $this->filesystem->tempnam($this->appendRoot($dir), $prefix, $suffix);
    }

    public function dumpFile(string $filename, $content): void
    {
        $this->filesystem->dumpFile($this->appendRoot($filename), $content);
    }

    public function appendToFile(string $filename, $content): void
    {
        $this->filesystem->appendToFile($this->appendRoot($filename), $content);
    }

}
