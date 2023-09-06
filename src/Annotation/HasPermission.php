<?php

namespace App\Annotation;

use Attribute;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("METHOD")
 */
#[Attribute(Attribute::IS_REPEATABLE|Attribute::TARGET_METHOD)]
class HasPermission {

    public const IN_RENDER = 0;
    public const IN_JSON = 1;

    public const TYPE_QUERY_PARAM = 'query';
    public const TYPE_REQUEST_PARAM = 'request';

    public array|string|null $value = null;

    public function __construct(array|string|null $value = null, public $mode = self::IN_RENDER, public $type = null) {
        $this->value = $value;
    }

}
