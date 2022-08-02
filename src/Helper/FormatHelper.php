<?php

namespace App\Helper;

use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\LocationGroup;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use DateTime;
use DateTimeInterface;
use WiiCommon\Helper\Stream;

class FormatHelper {

    public const ENGLISH_WEEK_DAYS = [
        1 => "Monday",
        2 => "Tuesday",
        3 => "Wednesday",
        4 => "Thursday",
        5 => "Friday",
        6 => "Saturday",
        7 => "Sunday",
    ];

    public const WEEK_DAYS = [
        1 => "Lundi",
        2 => "Mardi",
        3 => "Mercredi",
        4 => "Jeudi",
        5 => "Vendredi",
        6 => "Samedi",
        7 => "Dimanche",
    ];

    public const MONTHS = [
        1 => "Janvier",
        2 => "Février",
        3 => "Mars",
        4 => "Avril",
        5 => "Mai",
        6 => "Juin",
        7 => "Juillet",
        8 => "Août",
        9 => "Septembre",
        10 => "Octobre",
        11 => "Novembre",
        12 => "Décembre",
    ];

    private const QUANTITY_TYPE_LABELS = [
        ReferenceArticle::QUANTITY_TYPE_REFERENCE => 'Référence',
        ReferenceArticle::QUANTITY_TYPE_ARTICLE => 'Article',
    ];

    private const LANDING_PAGE_LABELS = [
        Role::LANDING_PAGE_DASHBOARD => 'Dashboard',
        Role::LANDING_PAGE_TRANSPORT_PLANNING => 'Planning',
        Role::LANDING_PAGE_TRANSPORT_REQUEST => 'Demande de transport',
    ];

    public static function parseDatetime(?string $date, array $expectedFormats = [
        "Y-m-d H:i:s",
        "d/m/Y H:i:s",
        "Y-m-d H:i",
        "Y-m-d\TH:i",
        "d/m/Y H:i",
        "Y-m-d",
        "d/m/Y"
    ]): ?DateTime {
        if (empty($date)) {
            return null;
        }

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

    public static function quantityTypeLabel(?string $quantityType, string $else = ""): string {
        return self::QUANTITY_TYPE_LABELS[$quantityType] ?? $else;
    }

    public static function landingPageLabel(?string $landingPage, string $else = ""): string {
        return self::LANDING_PAGE_LABELS[$landingPage] ?? $else;
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

    public static function supplier(?Fournisseur $supplier, $else = "") {
        return $supplier ? $supplier->getNom() : $else;
    }

    public static function location(?Emplacement $location, $else = "") {
        return $location ? $location->getLabel() : $else;
    }

    public static function locationGroup(?LocationGroup $locationGroup, $else = "") {
        return $locationGroup ? $locationGroup->getLabel() : $else;
    }

    public static function user(?Utilisateur $user, $else = "") {
        return $user ? $user->getUsername() : $else;
    }

    public static function nature(?Nature $nature, $else = "") {
        return $nature ? $nature->getLabel() : $else;
    }

    public static function visibilityGroup(?VisibilityGroup $visibilityGroup, $else = "") {
        return $visibilityGroup ? $visibilityGroup->getLabel() : $else;
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

    public static function longDate(?DateTimeInterface $date, array $options = [], $else = "-"): ?string {
        $short = $options['short'] ?? false;
        $time = $options['time'] ?? false;
        $year = $options['year'] ?? true;

        return $date
            ? (($short
                ? substr(self::WEEK_DAYS[$date->format("N")], 0, 3)
                : self::WEEK_DAYS[$date->format("N")])
                    . " "
                    . $date->format("d")
                    . " "
                    . strtolower(self::MONTHS[$date->format("n")])
                    . ($year ? (" " . $date->format("Y")) : '')
            . ($time ? $date->format(" à H:i") : ""))
            : $else;
    }

    public static function time(?DateTimeInterface $date, $else = "") {
        return $date ? $date->format("H:i") : $else;
    }

    public static function html(?string $comment, $else = "") {
        return $comment ? strip_tags($comment) : $else;
    }

    public static function freeField(?string $value, FreeField $freeField): ?string {
        $value = ($value ?? $freeField->getDefaultValue()) ?? '';
        switch ($freeField->getTypage()) {
            case FreeField::TYPE_DATE:
            case FreeField::TYPE_DATETIME:
                $valueDate = self::parseDatetime($value, ["Y-m-dTH:i", "Y-m-d", "d/m/Y H:i", "Y-m-d H:i", "d/m/Y"]);
                $hourFormat = ($freeField->getTypage() === FreeField::TYPE_DATETIME ? ' H:i' : '');
                $formatted = $valueDate ? $valueDate->format('d/m/Y' . $hourFormat) : $value;
                break;
            case FreeField::TYPE_BOOL:
                $formatted = ($value !== '' && $value !== null)
                    ? self::bool($value == 1)
                    : '';
                break;
            case FreeField::TYPE_LIST_MULTIPLE:
                $formatted = Stream::explode(';', $value)
                    ->filter(fn(string $val) => in_array(trim($val),
                        Stream::from($freeField->getElements())
                            ->map(fn(string $element) => trim($element))
                            ->toArray() ?: []))
                    ->join(', ');
                break;
            default:
                $formatted = $value;
                break;
        }
        return $formatted;
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

    public static function phone(?string $stringWithPhone): ?string {
        return $stringWithPhone ? preg_replace(
            "/(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})/",
            "<a href=\"tel:$0\">$0</a>",
            $stringWithPhone
        ) : null;
    }

}
