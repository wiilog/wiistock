<?php

namespace App\Helper;

use App\Entity\Emplacement;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTimeInterface;

class FormatHelper {

    public static function type(?Type $type, $else = "") {
        return $type ? $type->getLabel() : $else;
    }

    public static function status(?Statut $status, $else = "") {
        return $status ? $status->getNom() : $else;
    }

    public static function location(?Emplacement $location, $else = "") {
        return $location ? $location->getLabel() : $else;
    }

    public static function user(?Utilisateur $user, $else = "") {
        return $user ? $user->getUsername() : $else;
    }

    public static function entity($entities, string $field, string $separator = ", ") {
        return Stream::from($entities)
            ->filter(function($entity) use ($field) {
                return $entity !== null && is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->map(function($entity) use ($field) {
                return is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->join($separator);
    }

    public static function users($users) {
        return self::entity($users, "username");
    }

    public static function carriers($carriers) {
        return self::entity($carriers, "label");
    }

    public static function locations($locations) {
        return self::entity($locations, "label");
    }

    public static function bool(?bool $bool, $else = "") {
        return isset($bool) ? ($bool ? 'oui' : 'non') : $else;
    }

    public static function date(?DateTimeInterface $date, $else = "") {
        return $date ? $date->format("d/m/Y") : $else;
    }

    public static function datetime(?DateTimeInterface $date, $else = "") {
        return $date ? $date->format("d/m/Y H:i") : $else;
    }

    public static function time(?DateTimeInterface $date, $else = "") {
        return $date ? $date->format("H:i") : $else;
    }

    public static function html(?string $comment, $else = "") {
        return $comment ? strip_tags($comment) : $else;
    }

    public static function sqlString(string $sqlString): string {
        return str_replace(
            ["'", "\\"],
            ["''", "\\\\"],
            $sqlString
        );
    }

}
