<?php

namespace App\Helper;

use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Handling;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use WiiCommon\Utils\DateTime;
use DateTimeInterface;
use WiiCommon\Helper\Stream;

class FormatHelper {

    public static function parseDatetime(?string $date, array $expectedFormats = ["Y-m-d H:i:s", "d/m/Y H:i:s", "Y-m-d H:i", "d/m/Y H:i"]): ?DateTimeInterface {
        foreach($expectedFormats as $format) {
            if($out = DateTime::createFromFormat($format, $date)) {
                return $out;
            }
        }

        return new DateTime($date) ?: null;
    }

    public static function type(?Type $type, $else = "") {
        return $type ? $type->getLabel() : $else;
    }

    public static function handlingRequester(Handling $handling, $else = ""): string {
        $triggeringSensorWrapper = $handling->getTriggeringSensorWrapper();
        $triggeringSensorWrapperName = $triggeringSensorWrapper ? $triggeringSensorWrapper->getName() : null;
        $requester = $handling->getRequester();
        $requesterUsername = $requester ? $requester->getUsername() : null;
        return $triggeringSensorWrapperName
            ?: $requesterUsername
            ?: $else;
    }

    public static function deliveryRequester(Demande $demande, $else = ""): string {
        $triggeringSensorWrapper = $demande->getTriggeringSensorWrapper();
        $triggeringSensorWrapperName = $triggeringSensorWrapper ? $triggeringSensorWrapper->getName() : null;
        $requester = $demande->getUtilisateur();
        $requesterUsername = $requester ? $requester->getUsername() : null;
        return $triggeringSensorWrapperName
            ?: $requesterUsername
            ?: $else;
    }

    public static function collectRequester(Collecte $collectRequest, $else = ""): string {
        $triggeringSensorWrapper = $collectRequest->getTriggeringSensorWrapper();
        $triggeringSensorWrapperName = $triggeringSensorWrapper ? $triggeringSensorWrapper->getName() : null;
        $requester = $collectRequest->getDemandeur();
        $requesterUsername = $requester ? $requester->getUsername() : null;
        return $triggeringSensorWrapperName
            ?: $requesterUsername
            ?: $else;
    }

    public static function status(?Statut $status, $else = "") {
        return $status ? $status->getNom() : $else;
    }

    public static function pack(?Pack $pack, $else = "") {
        return $pack ? $pack->getCode() : $else;
    }

    public static function provider(?Fournisseur $provider, $else = "") {
        return $provider ? $provider->getNom() : $else;
    }

    public static function location(?Emplacement $location, $else = "") {
        return $location ? $location->getLabel() : $else;
    }

    public static function user(?Utilisateur $user, $else = "") {
        return $user ? $user->getUsername() : $else;
    }

    public static function nature(?Nature $nature, $else = "") {
        return $nature ? $nature->getLabel() : $else;
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

    public static function date(?DateTimeInterface $date, $else = "", $switchEnFormat = false) {
        return $date ? $date->format($switchEnFormat ? "d-m-Y" : 'd/m/Y') : $else;
    }

    public static function datetime(?DateTimeInterface $date, $else = "", $addAt = false) {
        return $date ? $date->format($addAt ? "d/m/Y à H:i" : "d/m/Y H:i") : $else;
    }

    public static function time(?DateTimeInterface $date, $else = "") {
        return $date ? $date->format("H:i") : $else;
    }

    public static function html(?string $comment, $else = "") {
        return $comment ? strip_tags($comment) : $else;
    }

    public static function messageContent(SensorMessage $sensorMessage) {
        $type = $sensorMessage->getSensor() ? self::type($sensorMessage->getSensor()->getType()) : '';
        $content = $sensorMessage->getContent();
        switch ($type) {
            case Sensor::TEMPERATURE:
                $measureUnit = '°C';
                break;
            case Sensor::GPS:
            case Sensor::ACTION:
            default:
                $measureUnit = '';
        }

        return $content . $measureUnit;
    }

    public static function sqlString(string $sqlString): string {
        return str_replace(
            ["'", "\\"],
            ["''", "\\\\"],
            $sqlString
        );
    }

}
