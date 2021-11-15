<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\Annotations as Rest;

class KubernetesController extends AbstractFOSRestController {

    private ?Utilisateur $user = null;

    public function getUser(): Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): void {
        $this->user = $user;
    }

    /**
     * @Rest\Get("/api/kube-ping")
     * @Rest\View()
     */
    public function ping(): JsonResponse {
        $response = new JsonResponse(['success' => true]);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
        return $response;
    }

}
