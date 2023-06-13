<?php

namespace App\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("METHOD")
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class HasPermission {

    public const IN_RENDER = 0;
    public const IN_JSON = 1;

    public ?array $value = null;

    public function __construct(?array $value = null, public $mode = self::IN_RENDER) {
        $this->value = $value;
    }

}
