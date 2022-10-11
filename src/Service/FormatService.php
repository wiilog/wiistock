<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\Language;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class FormatService
{

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public Security $security;

    #[Required]
    public TranslationService $translationService;

    private ?string $defaultLanguage = null;

    private function getUser(?Utilisateur $user = null): ?Utilisateur {
        return $user ?? $this->security->getUser();
    }

    public function user(?Utilisateur $user, $else = "") {
        return $user ? $user->getUsername() : $else;
    }

    public function supplier(?Fournisseur $supplier, $else = "") {
        return $supplier ? $supplier->getNom() : $else;
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
        return $type ? $type->getLabelIn($this->getUser($user)?->getLanguage() ?: $this->defaultLanguage(), $this->defaultLanguage()) : $else;
    }

    public function status(?Statut $status, $else = "", ?Utilisateur $user = null): ?string {
        return $status ? $status->getLabelIn($this->getUser($user)?->getLanguage() ?: $this->defaultLanguage(), $this->defaultLanguage()) : $else;
    }

    public function nature(?Nature $nature, $else = "", ?Utilisateur $user = null): ?string {
        return $nature ? $nature->getLabelIn($this->getUser($user)?->getLanguage() ?: $this->defaultLanguage(), $this->defaultLanguage()) : $else;
    }

    public function date(?DateTimeInterface $date, $else = "", ?Utilisateur $user = null) {
        return $date ? $date->format($this->getUser($user)?->getDateFormat()) : $else;
    }

    public function datetime(?DateTimeInterface $date, $else = "", $addAt = false, ?Utilisateur $user = null) {
        $prefix = $this->getUser($user)?->getDateFormat() ?: Utilisateur::DEFAULT_DATE_FORMAT;
        return $date ? $date->format($addAt ? "$prefix à H:i" : "$prefix H:i") : $else;
    }

    public function location(?Emplacement $location, $else = "") {
        return $location ? $location->getLabel() : $else;
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

    public function entity($entities, string $field, string $separator = ", ") {
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
            $user?->getLanguage()?->getSlug()
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
}
