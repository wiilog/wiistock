<?php

namespace App\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class PostHelper {

    private static EntityManagerInterface $manager;

    public static function string(?ParameterBag $parameterBag, string $index, $else = "") {
        return $parameterBag->has($index) ? $parameterBag->get($index) : $else;
    }

    public static function entity(?ParameterBag $parameterBag, string $index, string $entity, $else = "") {
        return $parameterBag->has($index) ? self::$manager->getRepository($entity)->find($index) : $else;
    }

}
