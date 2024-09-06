<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Repository\Tracking\TrackingMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile")]
class UserController extends AbstractController {

    #[Route("/users/{user}/previous-picking-counter", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
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

