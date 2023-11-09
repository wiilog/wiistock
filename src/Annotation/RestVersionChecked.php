<?php

namespace App\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

#[Attribute(Attribute::TARGET_METHOD)]
class RestVersionChecked {

}
