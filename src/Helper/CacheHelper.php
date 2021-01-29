<?php

namespace App\Helper;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheHelper {

    public static function create(string $namespace): FilesystemAdapter {
        return new FilesystemAdapter($namespace, 0, "../var/cache");
    }

}
