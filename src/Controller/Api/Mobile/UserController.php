<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Repository\TrackingMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Rest\Route("/api/mobile")]
class UserController extends AbstractController {

    #[Rest\Get("/users/{user}/previous-picking-counter", condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getPreviousPickingCounter(EntityManagerInterface $entityManager,
                                              Utilisateur            $user): JsonResponse {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $movements = $trackingMovementRepository->getPickingByOperatorAndNotDropped(
            $user,
            TrackingMovementRepository::MOUVEMENT_TRACA_DEFAULT
        );

        return $this->json([
            "success" => true,
            "counter" => count($movements),
        ]);
    }
}

