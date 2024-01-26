<?php

namespace App\Controller\Api;

use App\Entity\Traits\AbstractControllerTrait;
use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\FormatService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Contracts\Service\Attribute\Required;

class AbstractApiController extends AbstractFOSRestController {

    use AbstractControllerTrait;

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

}
