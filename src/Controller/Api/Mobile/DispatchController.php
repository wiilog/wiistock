<?php

namespace App\Controller\Api\Mobile;

use App\Entity\Utilisateur;
use FOS\RestBundle\Controller\AbstractFOSRestController;

class DispatchController extends AbstractFOSRestController {

    private ?Utilisateur $user = null;

    public function getUser(): Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): void {
        $this->user = $user;
    }

}
