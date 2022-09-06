<?php

namespace App\Service;

use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;

class FormatService
{

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public Security $security;

    #[Required]
    public EntityManagerInterface $entityManager;

    private ?string $defaultLanguage = null;

    private function getUser(?Utilisateur $user = null): ?Utilisateur {
        return $user ?? $this->security->getUser();
    }

    private function defaultLanguage(): ?string {
        if(!$this->defaultLanguage) {
            $this->defaultLanguage = $this->languageService->getDefaultSlug();
        }

        return $this->defaultLanguage;
    }

    public function type(?Type $type, $else = "", ?Utilisateur $user = null): ?string {
        return $type ? $type->getLabelIn($this->getUser($user)->getLanguage(), $this->defaultLanguage()) : $else;
    }

    public function status(?Statut $status, $else = "", ?Utilisateur $user = null): ?string {
        return $status ? $status->getLabelIn($this->getUser($user)->getLanguage(), $this->defaultLanguage()) : $else;
    }

    public function nature(?Nature $nature, $else = "", ?Utilisateur $user = null): ?string {
        return $nature ? $nature->getLabelIn($this->getUser($user)->getLanguage(), $this->defaultLanguage()) : $else;
    }

    public function date(?DateTimeInterface $date, $else = "", ?Utilisateur $user = null) {
        return $date ? $date->format($this->getUser($user)?->getDateFormat() ) : $else;
    }

    public function datetime(?DateTimeInterface $date, $else = "", $addAt = false, ?Utilisateur $user = null) {
        $prefix = $this->getUser($user)?->getDateFormat();
        return $date ? $date->format($addAt ? "$prefix à H:i" : "$prefix H:i") : $else;
    }

}
