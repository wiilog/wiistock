<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Handling;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
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
        return self::entity($users, "username");
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
        $prefix = $this->getUser($user)?->getDateFormat();
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

    public function freeField(?string $value, FreeField $freeField, Utilisateur $user = null): ?string
    {
        $value = ($value ?? $freeField->getDefaultValue()) ?? '';
        switch ($freeField->getTypage()) {
            case FreeField::TYPE_DATE:
            case FreeField::TYPE_DATETIME:
                $valueDate = self::parseDatetime($value, [
                    "Y-m-dTH:i",
                    "Y-m-d",
                    "d/m/Y H:i",
                    "Y-m-d H:i",
                    "m-d-Y H:i",
                    "m-d-Y",
                    "d/m/Y",
                    $user && $user->getDateFormat() ? $user->getDateFormat() . ' H:i' : '',
                    $user && $user->getDateFormat() ? $user->getDateFormat() : '',
                ]);
                $hourFormat = ($freeField->getTypage() === FreeField::TYPE_DATETIME ? ' H:i' : '');
                $formatted = $valueDate ? $valueDate->format(($user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y') . $hourFormat) : $value;
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
}
