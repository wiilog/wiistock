<?php

namespace App\Annotation;
/**
 * Class BarcodeAnnotation
 *
 * @Annotation
 * @package App\Annotation
 */
class BarcodeAnnotation {

    public const MAX_LENGTH = 21;
    public const PREG_MATCH_INVALID = '/[^\x20-\x7e]/';

    /**
     * @var string name of the getter of the current property. The return of this method must be a string.
     */
    public $getter;
}