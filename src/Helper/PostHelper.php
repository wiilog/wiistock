<?php

namespace App\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class PostHelper {

    public static function string($parameterBag,
                                  string $index,
                                  $else = null) {
        return self::has($parameterBag, $index)
            ? self::get($parameterBag, $index)
            : $else;
    }

    public static function entity(EntityManagerInterface $entityManager,
                                  $parameterBag,
                                  string $index,
                                  string $entity,
                                  $else = null) {
        return self::has($parameterBag, $index)
            ? $entityManager->getRepository($entity)->find(self::get($parameterBag, $index))
            : $else;
    }

    /**
     * @param ParameterBag|array $parameterBag
     * @param mixed $index
     */
    private static function get($parameterBag, $index) {
        return $parameterBag instanceof ParameterBag
            ? $parameterBag->get($index)
            : ($parameterBag[$index] ?? null);
    }

    /**
     * @param ParameterBag|array $parameterBag
     * @param mixed $index
     */
    private static function has($parameterBag, $index) {
        return $parameterBag instanceof ParameterBag
            ? $parameterBag->has($index)
            : isset($parameterBag[$index]);
    }

}
