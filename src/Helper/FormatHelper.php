<?php

namespace App\Helper;

use App\Entity\Emplacement;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
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

    public static function bool(?bool $bool, $else = null) {
        if($else !== null && $bool === null) {
            return $else;
        } else {
            return $bool ? "Oui" : "Non";
        }
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

}
