<?php

namespace App\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("METHOD")
 */
class Authenticated {

    const MOBILE = "api";
    const WEB = "web";

    public $value;

    public function getValue() {
        return $this->value;
    }

}
