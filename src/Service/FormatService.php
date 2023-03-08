<?php

namespace App\Service;

use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\Language;
use App\Entity\LocationGroup;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\Zone;
use DateTime;
use DateTimeInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class FormatService
{

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

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public UserService $userService;

    #[Required]
    public TranslationService $translationService;

    private ?string $defaultLanguage = null;

    private function getUser(?Utilisateur $user = null): ?Utilisateur {
        return $user ?? $this->userService->getUser();
    }

    public function user(?Utilisateur $user, $else = "") {
        return $user ? $user->getUsername() : $else;
    }

    public function supplier(?Fournisseur $supplier, string $else = ""): string {
        return $supplier ? $supplier->getNom() : $else;
    }

    public function suppliers(?array $suppliers, string $else = ""): string {
        return $suppliers
            ? Stream::from($suppliers)
                ->map(fn(Fournisseur $supplier) => $this->supplier($supplier))
                ->join(', ')
            : $else;
    }

    public function users($users) {
        return $this->entity($users, "username");
    }

    public function defaultLanguage(): ?string {
        if(!$this->defaultLanguage) {
            $this->defaultLanguage = $this->languageService->getDefaultSlug();
        }

        return $this->defaultLanguage;
    }

    public function type(?Type $type, $else = "", ?Utilisateur $user = null): ?string {
        return $type
            ? ($type->getLabelIn($this->getUser($user)?->getLanguage() ?: $this->defaultLanguage(), $this->defaultLanguage()) ?: $type->getLabel())
            : $else;
    }

    public function status(?Statut $status, $else = "", ?Utilisateur $user = null): ?string {
        return $status
            ? ($status->getLabelIn($this->getUser($user)?->getLanguage() ?: $this->defaultLanguage(), $this->defaultLanguage()) ?: $status->getNom())
            : $else;
    }

    public function nature(?Nature $nature, $else = "", ?Utilisateur $user = null): ?string {
        return $nature
            ? ($nature->getLabelIn($this->getUser($user)?->getLanguage() ?: $this->defaultLanguage(), $this->defaultLanguage()) ?: $nature->getLabel())
            : $else;
    }

    public function date(?DateTimeInterface $date, $else = "", ?Utilisateur $user = null) {
        return $date ? $date->format($this->getUser($user)?->getDateFormat()) : $else;
    }

    public function carrier(?Transporteur $carrier, string $else = ""): string {
        return $carrier?->getLabel() ?: $else;
    }

    public function carriers($carriers) {
        return $this->entity($carriers, "label");
    }

    public function datetime(?DateTimeInterface $date, $else = "", $addAt = false, ?Utilisateur $user = null) {
        $prefix = $this->getUser($user)?->getDateFormat() ?: Utilisateur::DEFAULT_DATE_FORMAT;
        return $date ? $date->format($addAt ? "$prefix à H:i" : "$prefix H:i") : $else;
    }

    public function project(?Project $project, $else = "") {
        return $project ? $project->getCode() : $else;
    }

    public function time(?DateTimeInterface $date, $else = "") {
        return $date ? $date->format("H:i") : $else;
    }

    public function html(?string $comment, $else = "") {
        return $comment ? strip_tags($comment) : $else;
    }

    public function longDate(?DateTimeInterface $date, array $options = [], $else = "-"): ?string {
        $short = $options['short'] ?? false;
        $time = $options['time'] ?? false;
        $year = $options['year'] ?? true;
        $at = ($options['removeAt'] ?? false) ? 'à' : '';

        return $date
            ? (($short
                    ? substr(self::WEEK_DAYS[$date->format("N")], 0, 3)
                    : self::WEEK_DAYS[$date->format("N")])
                . " "
                . $date->format("d")
                . " "
                . strtolower(self::MONTHS[$date->format("n")])
                . ($year ? (" " . $date->format("Y")) : '')
                . ($time ? $date->format(" $at H:i") : ""))
            : $else;
    }

    public function location(?Emplacement $location, $else = "") {
        return $location ? $location->getLabel() : $else;
    }

    public function locations($locations) {
        return $this->entity($locations, "label");
    }

    public function visibilityGroup(?VisibilityGroup $visibilityGroup, $else = "") {
        return $visibilityGroup ? $visibilityGroup->getLabel() : $else;
    }

    public function locationGroup(?LocationGroup $locationGroup, $else = "") {
        return $locationGroup ? $locationGroup->getLabel() : $else;
    }

    public function pack(?Pack $pack, $else = "") {
        return $pack ? $pack->getCode() : $else;
    }

    public function handlingRequester(Handling $handling, $else = ""): string {
        $triggeringSensorWrapper = $handling->getTriggeringSensorWrapper();
        $triggeringSensorWrapperName = $triggeringSensorWrapper ? $triggeringSensorWrapper->getName() : null;
        $requester = $handling->getRequester();
        $requesterUsername = $requester ? $requester->getUsername() : null;
        return $triggeringSensorWrapperName
            ?: $requesterUsername
                ?: $else;
    }

    public function entity($entities, string $field, string $separator = ", "): string {
        return Stream::from($entities)
            ->filter(function($entity) use ($field) {
                return $entity !== null && is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->map(function($entity) use ($field) {
                return is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->join($separator);
    }

    public function parseDatetime(?string $date, array $expectedFormats = [
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

    public function freeField(?string     $value,
                              FreeField   $freeField,
                              Utilisateur $user = null): ?string {
        $userLanguage = (
            $this->getUser($user)?->getLanguage()?->getSlug()
            ?: $this->defaultLanguage()
        );

        $value = ($value ?? $freeField->getDefaultValue()) ?? '';
        switch ($freeField->getTypage()) {
            case FreeField::TYPE_DATE:
            case FreeField::TYPE_DATETIME:
                $formats = [
                    "Y-m-dTH:i",
                    "Y-m-d",
                    "d/m/Y H:i",
                    "Y-m-d H:i",
                    "m-d-Y H:i",
                    "m-d-Y",
                    "d/m/Y"
                ];
                if ($user && $user->getDateFormat()) {
                    $formats[] = $user->getDateFormat() . ' H:i';
                    $formats[] = $user->getDateFormat();
                }
                $valueDate = $this->parseDatetime($value, $formats);
                $hourFormat = ($freeField->getTypage() === FreeField::TYPE_DATETIME ? ' H:i' : '');
                $formatted = $valueDate ? $valueDate->format(($user && $user->getDateFormat() ? $user->getDateFormat() : Utilisateur::DEFAULT_DATE_FORMAT) . $hourFormat) : $value;
                break;
            case FreeField::TYPE_BOOL:
                $formatted = ($value !== '' && $value !== null)
                    ? $this->bool($value == 1)
                    : '';
                break;
            case FreeField::TYPE_LIST_MULTIPLE:
                $values = Stream::explode(';', $value)->toArray();
                $translatedValues = $this->translationService->translateFreeFieldListValues(Language::FRENCH_SLUG, $userLanguage, $freeField, $values, true);
                $formatted = Stream::from($translatedValues ?: [])->join(', ');
                break;
            case FreeField::TYPE_LIST:
                $formatted = $this->translationService->translateFreeFieldListValues(Language::FRENCH_SLUG, $userLanguage, $freeField, $value, true);
                break;
            default:
                $formatted = $value;
                break;
        }
        return $formatted;
    }

    public function bool(?bool $bool, $else = "") {
        $yes = $this->translationService->translate('Général', null, 'Modale', 'Oui', false);
        $no = $this->translationService->translate('Général', null, 'Modale', 'Non', false);
        return isset($bool) ? ($bool ? $yes : $no) : $else;
    }

    public function quantityTypeLabel(?string $quantityType, string $else = ""): string {
        return self::QUANTITY_TYPE_LABELS[$quantityType] ?? $else;
    }

    public function landingPageLabel(?string $landingPage, string $else = ""): string {
        return self::LANDING_PAGE_LABELS[$landingPage] ?? $else;
    }

    public function deliveryRequester(Demande $demande, $else = ""): string {
        $triggeringSensorWrapper = $demande->getTriggeringSensorWrapper();
        $triggeringSensorWrapperName = $triggeringSensorWrapper?->getName();
        $requester = $demande->getUtilisateur();
        $requesterUsername = $requester?->getUsername();
        return $triggeringSensorWrapperName
            ?: $requesterUsername
                ?: $else;
    }

    public function collectRequester(Collecte $collectRequest, $else = ""): string {
        $triggeringSensorWrapper = $collectRequest->getTriggeringSensorWrapper();
        $triggeringSensorWrapperName = $triggeringSensorWrapper?->getName();
        $requester = $collectRequest->getDemandeur();
        $requesterUsername = $requester?->getUsername();
        return $triggeringSensorWrapperName
            ?: $requesterUsername
                ?: $else;
    }

    public function decimal(?float $decimal, array $options = [], string $else = ""): string {
        $decimals = $options['decimals'] ?? 2;
        $decimalSeparator = $options['decimalSeparator'] ?? ',';
        $thousandsSeparator = $options['thousandsSeparator'] ?? ' ';
        return isset($decimal)
            ? number_format($decimal, $decimals, $decimalSeparator, $thousandsSeparator)
            : $else;
    }


    public function messageContent(SensorMessage $sensorMessage) {
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

    public function sqlString(string $sqlString): string {
        return str_replace(
            ["'", "\\"],
            ["''", "\\\\"],
            $sqlString
        );
    }

    public function phone(?string $stringWithPhone): ?string {
        return $stringWithPhone ? preg_replace(
            "/(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})/",
            "<a href=\"tel:$0\">$0</a>",
            $stringWithPhone
        ) : null;
    }

    public function zone(?Zone $zone, string $else = ""): string {
        return $zone ? $zone->getName() : $else;
    }

    public function zones(?array $zones, string $else = ""): string {
        return $zones
            ? Stream::from($zones)
                ->map(fn(Zone $zone) => $this->zone($zone))
                ->join(', ')
            : $else;
    }
}
