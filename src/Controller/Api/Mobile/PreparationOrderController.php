<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Exceptions\NegativeQuantityException;
use App\Service\ExceptionLoggerService;
use App\Service\LivraisonsManagerService;
use App\Service\NotificationService;
use App\Service\PreparationsManagerService;
use App\Service\SettingsService;
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class PreparationOrderController extends AbstractController {

    #[Route("/finishPrepa", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishPrepa(Request                    $request,
                                ExceptionLoggerService     $exceptionLoggerService,
                                LivraisonsManagerService   $livraisonsManager,
                                TrackingMovementService    $trackingMovementService,
                                PreparationsManagerService $preparationsManager,
                                SettingsService            $settingsService,
                                NotificationService        $notificationService,
                                EntityManagerInterface     $entityManager) {
        $insertedPrepasIds = [];
        $statusCode = Response::HTTP_OK;

        $nomadUser = $this->getUser();

        $articleRepository = $entityManager->getRepository(Article::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $resData = ['success' => [], 'errors' => [], 'data' => []];

        $preparations = json_decode($request->request->get('preparations'), true);

        // on termine les préparations
        // même comportement que LivraisonController.new()
        foreach ($preparations as $preparationArray) {
            $preparation = $preparationRepository->find($preparationArray['id']);
            if ($preparation) {
                // if it has not been begun
                try {
                    $dateEnd = DateTime::createFromFormat(DateTimeInterface::ATOM, $preparationArray['date_end']);
                    // flush auto at the end
                    $entityManager->transactional(function () use (
                        &$insertedPrepasIds,
                        $preparationsManager,
                        $livraisonsManager,
                        $preparationArray,
                        $preparation,
                        $nomadUser,
                        $dateEnd,
                        $entityManager,
                        $trackingMovementService,
                        $notificationService
                    ) {

                        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                        $articleRepository = $entityManager->getRepository(Article::class);
                        $ligneArticlePreparationRepository = $entityManager->getRepository(PreparationOrderReferenceLine::class);
                        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

                        $preparationsManager->setEntityManager($entityManager);
                        $mouvementsNomade = $preparationArray['mouvements'];
                        $totalQuantitiesWithRef = [];
                        $livraison = $livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);
                        $emplacementPrepa = $emplacementRepository->findOneBy(['label' => $preparationArray['emplacement']]);
                        foreach ($mouvementsNomade as $mouvementNomade) {
                            if (!$mouvementNomade['is_ref'] && $mouvementNomade['selected_by_article']) {
                                /** @var Article $article */
                                $article = $articleRepository->findOneByReference($mouvementNomade['reference']);
                                $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                if (!isset($totalQuantitiesWithRef[$refArticle->getReference()])) {
                                    $totalQuantitiesWithRef[$refArticle->getReference()] = 0;
                                }
                                $totalQuantitiesWithRef[$refArticle->getReference()] += $mouvementNomade['quantity'];
                            }
                            $preparationsManager->treatMouvementQuantities($mouvementNomade, $preparation);
                        }

                        $articlesToKeep = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $nomadUser, $entityManager);
                        $movementRepository = $entityManager->getRepository(MouvementStock::class);
                        $movements = $movementRepository->findByPreparation($preparation);

                        foreach ($movements as $movement) {
                            if ($movement->getType() === MouvementStock::TYPE_TRANSFER) {
                                /** @var ReferenceArticle|Article $refOrArticle */
                                $refOrArticle = $movement->getRefArticle() ?: $movement->getArticle();
                                $preparationsManager->persistDeliveryMovement(
                                    $entityManager,
                                    $movement->getQuantity(),
                                    $nomadUser,
                                    $livraison,
                                    !empty($movement->getRefArticle()),
                                    $refOrArticle,
                                    $preparation,
                                    false,
                                    $emplacementPrepa
                                );

                                $trackingMovementPick = $trackingMovementService->createTrackingMovement(
                                    $refOrArticle->getTrackingPack() ?: $refOrArticle->getBarCode(),
                                    $movement->getEmplacementFrom(),
                                    $nomadUser,
                                    $dateEnd,
                                    true,
                                    true,
                                    TrackingMovement::TYPE_PRISE,
                                    [
                                        'mouvementStock' => $movement,
                                        'preparation' => $preparation,
                                        'quantity' => $movement->getQuantity(),
                                    ]
                                );
                                $entityManager->persist($trackingMovementPick);
                                $entityManager->flush();
                                $trackingMovementDrop = $trackingMovementService->createTrackingMovement(
                                    $trackingMovementPick->getPack(),
                                    $emplacementPrepa,
                                    $nomadUser,
                                    $dateEnd,
                                    true,
                                    true,
                                    TrackingMovement::TYPE_DEPOSE,
                                    [
                                        'mouvementStock' => $movement,
                                        'preparation' => $preparation,
                                        'quantity' => $movement->getQuantity(),
                                    ]
                                );

                                $entityManager->persist($trackingMovementDrop);
                                $ulToMove[] = $movement->getArticle()?->getCurrentLogisticUnit();
                                $entityManager->flush();
                            }
                        }
                        if (isset($ulToMove)) {
                            /** @var Pack $lu */
                            foreach (array_unique($ulToMove) as $lu) {
                                if ($lu != null) {
                                    $pickTrackingMovement = $trackingMovementService->createTrackingMovement(
                                        $lu,
                                        $lu->getLastOngoingDrop()->getEmplacement(),
                                        $nomadUser,
                                        $dateEnd,
                                        true,
                                        true,
                                        TrackingMovement::TYPE_PRISE,
                                        [
                                            'preparation' => $preparation,
                                        ]
                                    );
                                    $DropTrackingMovement = $trackingMovementService->createTrackingMovement(
                                        $lu,
                                        $emplacementPrepa,
                                        $nomadUser,
                                        $dateEnd,
                                        true,
                                        true,
                                        TrackingMovement::TYPE_DEPOSE,
                                        [
                                            'preparation' => $preparation,
                                        ]
                                    );
                                    $entityManager->persist($pickTrackingMovement);
                                    $entityManager->persist($DropTrackingMovement);

                                    $lu->setLastOngoingDrop($DropTrackingMovement)->setLastAction($DropTrackingMovement);
                                }
                            }
                        }

                        foreach ($totalQuantitiesWithRef as $ref => $quantity) {
                            $refArticle = $referenceArticleRepository->findOneBy(['reference' => $ref]);
                            $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation);
                            $preparationsManager->deleteLigneRefOrNot($ligneArticle, $preparation, $entityManager);
                        }

                        $insertedPreparation = $preparationsManager->treatPreparation($preparation, $nomadUser, $emplacementPrepa, [
                            "articleLinesToKeep" => $articlesToKeep,
                            "entityManager" => $entityManager
                        ]);

                        if ($insertedPreparation) {
                            $insertedPrepasIds[] = $insertedPreparation->getId();
                        }

                        if ($emplacementPrepa) {
                            $preparationsManager->closePreparationMovements($preparation, $dateEnd, $emplacementPrepa);
                        } else {
                            throw new Exception(PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                        }

                        $entityManager->flush(); // need to flush before quantity update

                        if ($insertedPreparation
                            && $insertedPreparation->getDemande()->getType()->isNotificationsEnabled()) {
                            $notificationService->toTreat($insertedPreparation);
                        }
                        if ($livraison->getDemande()->getType()->isNotificationsEnabled()) {
                            $notificationService->toTreat($livraison);
                        }

                        $preparationsManager->updateRefArticlesQuantities($preparation, $entityManager);
                    });

                    $resData['success'][] = [
                        'numero_prepa' => $preparation->getNumero(),
                        'id_prepa' => $preparation->getId(),
                    ];
                } catch (Throwable $throwable) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        /** @var EntityManagerInterface $entityManager */
                        $entityManager = new EntityManager($entityManager->getConnection(), $entityManager->getConfiguration());
                        $preparationsManager->setEntityManager($entityManager);
                    }
                    $message = (
                        ($throwable instanceof NegativeQuantityException) ? "Une quantité en stock d\'un article est inférieure à sa quantité prélevée" :
                        (($throwable->getMessage() === PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                        (($throwable->getMessage() === PreparationsManagerService::ARTICLE_ALREADY_SELECTED) ? "L'article n'est pas sélectionnable" :
                        false))
                    );

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable);
                    }

                    $resData['errors'][] = [
                        'numero_prepa' => $preparation->getNumero(),
                        'id_prepa' => $preparation->getId(),
                        'message' => $message ?: 'Une erreur est survenue',
                    ];
                }
            }
        }

        if (!empty($insertedPrepasIds)) {
            $displayPickingLocation = $settingsService->getValue($entityManager, Setting::DISPLAY_PICKING_LOCATION);

            $resData['data']['preparations'] = Stream::from($preparationRepository->getMobilePreparations($nomadUser, $insertedPrepasIds, $displayPickingLocation))
                ->map(function ($preparationArray) {
                    if (!empty($preparationArray['comment'])) {
                        $preparationArray['comment'] = substr(strip_tags($preparationArray['comment']), 0, 200);
                    }
                    return $preparationArray;
                })
                ->toArray();
            $resData['data']['articlesPrepa'] = $preparationsManager->getArticlesPrepaArrays($entityManager, $insertedPrepasIds, true);
            $resData['data']['articlesPrepaByRefArticle'] = $articleRepository->getArticlePrepaForPickingByUser($nomadUser, $insertedPrepasIds);
        }

        $preparationsManager->removeRefMouvements();
        $entityManager->flush();

        return new JsonResponse($resData, $statusCode);
    }

    #[Route("/beginPrepa", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function beginPrepa(Request                $request,
                               EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $id = $request->request->get('id');
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $preparation = $preparationRepository->find($id);
        $data = [];

        if ($preparation->getStatut()?->getCode() == Preparation::STATUT_A_TRAITER ||
            $preparation->getUtilisateur() === $nomadUser) {
            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette préparation a déjà été prise en charge par un opérateur.";
            $data['data'] = [];
        }

        return $this->json($data);
    }
}
