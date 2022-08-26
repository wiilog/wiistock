<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\FormatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Contracts\Service\Attribute\Required;

class AbstractController extends SymfonyAbstractController {

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public FormatService $formatService;

    private ?Utilisateur $user = null;

    public function getUser(): ?Utilisateur {
        return $this->user ?? parent::getUser();
    }

    public function setUser(?Utilisateur $user): void {
        $this->user = $user;
    }

    public function getCache(): CacheService {
        return $this->cacheService;
    }

    public function getFormatter(): FormatService {
        return $this->formatService;
    }

}
