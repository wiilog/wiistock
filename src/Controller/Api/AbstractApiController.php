<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\FormatService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Contracts\Service\Attribute\Required;

class AbstractApiController extends AbstractFOSRestController {

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public FormatService $formatService;

    private ?Utilisateur $user = null;

    public function getUser(): ?Utilisateur {
        return $this->user;
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
