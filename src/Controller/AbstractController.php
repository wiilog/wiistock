<?php

namespace App\Controller;

use App\Entity\Traits\AbstractControllerTrait;
use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\FormatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Contracts\Service\Attribute\Required;

class AbstractController extends SymfonyAbstractController {

    use AbstractControllerTrait;

    public function getUser(): ?Utilisateur {
        return $this->user ?? parent::getUser();
    }

}
