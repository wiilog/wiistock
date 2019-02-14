<?php

namespace App\Serializer\Normalizer;

use FOS\RestBundle\Serializer\Normalizer\FormErrorNormalizer as FosRestFormErrorNormalizer;

class FormErrorNormalizer extends FosRestFormErrorNormalizer {
	/**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        return [
            'status' => 'error',
            'errors' => parent::normalize($object, $format, $context)['errors'],
        ];
    }
}