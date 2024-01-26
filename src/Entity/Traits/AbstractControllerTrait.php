<?php


namespace App\Entity\Traits;

use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\FormatService;
use Symfony\Contracts\Service\Attribute\Required;

trait AbstractControllerTrait {

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public FormatService $formatService;

    private ?Utilisateur $user = null;

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
