<?php

namespace App\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("METHOD")
 */
class HasPermission {

    public const IN_RENDER = 0;
    public const IN_JSON = 1;

    /**
     * @var string[]
     */
    public $value;

    /**
     * @var integer
     */
    public $mode = self::IN_RENDER;

}
