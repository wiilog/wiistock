<?php

namespace App\Controller\MobileApi;

use App\Entity\Utilisateur;
use FOS\RestBundle\Controller\AbstractFOSRestController;

class MobileApiDispatchController extends AbstractFOSRestController {

    private ?Utilisateur $user = null;

    public function getUser(): Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): void {
        $this->user = $user;
    }

}
