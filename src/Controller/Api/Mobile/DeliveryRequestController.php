<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Service\DeliveryRequestService;
use App\Service\LivraisonsManagerService;
use App\Service\MouvementStockService;
use App\Service\NotificationService;
use App\Service\PreparationsManagerService;
use App\Service\TrackingMovementService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile")]
class DeliveryRequestController extends AbstractController {

    #[Route("/valider-manual-dl", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function validateManualDL(Request                    $request,
                                     EntityManagerInterface     $entityManager,
                                     DeliveryRequestService     $demandeLivraisonService,
                                     LivraisonsManagerService   $livraisonsManagerService,
                                     NotificationService        $notificationService,
                                     MouvementStockService      $mouvementStockService,
                                     TrackingMovementService    $trackingMovementService,
                                     PreparationsManagerService $preparationsManagerService): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $logisticUnitRepository = $entityManager->getRepository(Pack::class);

        $nomadUser = $this->getUser();
        $location = json_decode($request->request->get('location'), true);
        $delivery = json_decode($request->request->get('delivery'), true);
        $now = new DateTime();

        $request = $demandeLivraisonService->newDemande([
            'isManual' => true,
            'type' => $delivery['type'],
            'demandeur' => $nomadUser,
            'destination' => $location['id'],
            'expectedAt' => $delivery['expectedAt'] ?? $now->format('Y-m-d'),
            'project' => $delivery['project'] ?? null,
            'commentaire' => $delivery['comment'] ?? null,
        ], $entityManager, true);

        $entityManager->persist($request);

        $location = $entityManager->find(Emplacement::class, $location['id']);
        foreach ($delivery['articles'] as $article) {
            $barcode = $article['barCode'];
            $article = $articleRepository->findOneBy(['barCode' => $barcode]);

            if (!$article) {
                $logisticUnit = $logisticUnitRepository->findOneBy(['code' => $barcode]);
            }

            $articles = $article ? [$article] : $logisticUnit->getChildArticles();
            foreach ($articles as $art) {
                $line = $demandeLivraisonService->createArticleLine($art, $request, [
                    'quantityToPick' => $art->getQuantite(),
                    'pickedQuantity' => $art->getQuantite(),
                ]);
                $entityManager->persist($line);
            }
        }

        $entityManager->flush();
        $response = $demandeLivraisonService->checkDLStockAndValidate(
            $entityManager,
            [
                'demande' => $request,
                'directDelivery' => true,
            ],
            false,
            false,
            true
        );

        if (!$response['success']) {
            $entityManager->remove($request);
            $entityManager->flush();
            return new JsonResponse($response);
        }
        $preparationOrder = $request->getPreparations()->first();
        $deliveryOrder = $livraisonsManagerService->createLivraison($now, $preparationOrder, $entityManager);

        // articles in delivery  which the logistics unit is not in delivery
        foreach ($delivery['articles'] as $articleArray) {
            $barcode = $articleArray['barCode'];
            $article = $articleRepository->findOneBy(['barCode' => $articleArray['barCode']]);
            if ($article && $article->getCurrentLogisticUnit()) {
                $article->setCurrentLogisticUnit(null);

                $trackingMovementService->persistTrackingMovement(
                    $entityManager,
                    $article->getTrackingPack() ?? $barcode,
                    $location,
                    $nomadUser,
                    $now,
                    false,
                    TrackingMovement::TYPE_PICK_LU,
                    false,
                    [
                        "delivery" => $deliveryOrder,
                        "stockAction" => true,
                    ]
                );
            }
        }

        foreach ($request->getArticleLines() as $articleLine) {
            $article = $articleLine->getArticle();
            $outMovement = $preparationsManagerService->persistDeliveryMovement(
                $entityManager,
                $article->getQuantite(),
                $nomadUser,
                $deliveryOrder,
                false,
                $article,
                $preparationOrder,
                true,
                $article->getEmplacement()
            );
            $entityManager->persist($outMovement);
            $mouvementStockService->finishStockMovement($outMovement, $now, $request->getDestination());
        }

        $newPreparation = $preparationsManagerService->treatPreparation($preparationOrder, $nomadUser, $request->getDestination(), [
            "entityManager" => $entityManager,
            "changeArticleLocation" => false,
        ]);
        $livraisonsManagerService->finishLivraison($nomadUser, $deliveryOrder, $now, $request->getDestination());

        $entityManager->flush(); // need to flush before quantity update
        $preparationsManagerService->updateRefArticlesQuantities($preparationOrder, $entityManager);
        $entityManager->flush();

        if ($newPreparation
            && $newPreparation->getDemande()->getType()->isNotificationsEnabled()) {
            $notificationService->toTreat($newPreparation);
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }

    #[Route("/valider-dl", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function checkAndValidateDL(Request                $request,
                                       EntityManagerInterface $entityManager,
                                       DeliveryRequestService $demandeLivraisonService): Response
    {
        $nomadUser = $this->getUser();

        $demandeArray = json_decode($request->request->get('demande'), true);
        $demandeArray['demandeur'] = $nomadUser;

        $freeFields = json_decode($demandeArray["freeFields"], true);

        if (is_array($freeFields)) {
            foreach ($freeFields as $key => $value) {
                $demandeArray[(int)$key] = $value;
            }
        }

        unset($demandeArray["freeFields"]);

        $responseAfterQuantitiesCheck = $demandeLivraisonService->checkDLStockAndValidate(
            $entityManager,
            $demandeArray,
            true
        );

        $responseAfterQuantitiesCheck['nomadMessage'] = $responseAfterQuantitiesCheck['nomadMessage']
            ?? $responseAfterQuantitiesCheck['msg']
            ?? '';

        return new JsonResponse($responseAfterQuantitiesCheck);
    }

    #[Route("/demande-livraison-data", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getDemandeLivraisonData(UserService $userService, EntityManagerInterface $entityManager): Response
    {
        $nomadUser = $this->getUser();

        $dataResponse = [];
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $httpCode = Response::HTTP_OK;
        $dataResponse['success'] = true;

        $rights = $userService->getMobileRights($nomadUser);
        if ($rights['deliveryRequest']) {
            $dataResponse['data'] = [
                'demandeLivraisonArticles' => $referenceArticleRepository->getByNeedsMobileSync(),
                'demandeLivraisonTypes' => array_map(function (Type $type) {
                    return [
                        'id' => $type->getId(),
                        'label' => $type->getLabel(),
                    ];
                }, $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON])),
            ];
        } else {
            $dataResponse['data'] = [
                'demandeLivraisonArticles' => [],
                'demandeLivraisonTypes' => [],
            ];
        }

        return new JsonResponse($dataResponse, $httpCode);
    }
}
