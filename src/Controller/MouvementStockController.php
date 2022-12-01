<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;

use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\MouvementStockService;
use App\Service\ProjectHistoryRecordService;
use App\Service\TrackingMovementService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * @Route("/mouvement-stock")
 */
class MouvementStockController extends AbstractController
{

    /**
     * @Route("/", name="mouvement_stock_index")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_MOUV_STOC})
     */
    public function index(EntityManagerInterface $entityManager)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);

        return $this->render('mouvement_stock/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_STOCK),
            'typesMvt' => [
                MouvementStock::TYPE_ENTREE,
                MouvementStock::TYPE_SORTIE,
                MouvementStock::TYPE_TRANSFER,
            ]
        ]);
    }

    /**
     * @Route("/api", name="mouvement_stock_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_MOUV_STOC}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, MouvementStockService $mouvementStockService): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = $mouvementStockService->getDataForDatatable($user, $request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/supprimer", name="mvt_stock_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            $movement = $mouvementStockRepository->find($data['mvt']);

            if (!empty($trackingMovementRepository->findBy(['mouvementStock' => $movement]))) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce mouvement de stock est lié à des mouvements de traçabilité.'
                ]);
            }

            $entityManager->remove($movement);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/nouveau", name="mvt_stock_new", options={"expose"=true},methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     */
    public function new(Request $request,
                        MouvementStockService $mouvementStockService,
                        TrackingMovementService $trackingMovementService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $statusRepository = $entityManager->getRepository(Statut::class);

            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $response = [
                'success' => false,
                'msg' => 'Mauvais type de mouvement choisi.'
            ];
            $chosenMvtType = $data["chosen-type-mvt"];
            $chosenMvtQuantity = $data["chosen-mvt-quantity"];
            $chosenMvtLocation = $data["chosen-mvt-location"];
            $movementBarcode = $data["movement-barcode"];
            $unavailableArticleStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);

            /** @var Article|ReferenceArticle|null $chosenArticleToMove */
            $chosenArticleToMove = (
                $referenceArticleRepository->findOneBy(['barCode' => $movementBarcode])
                ?: $articleRepository->findOneBy(['barCode' => $movementBarcode])
            );

            $chosenArticleStatus = $chosenArticleToMove->getStatut();
            $chosenArticleStatusName = $chosenArticleStatus ? $chosenArticleStatus?->getCode() : null;
            if (empty($chosenArticleToMove) || !in_array($chosenArticleStatusName, [ReferenceArticle::STATUT_ACTIF, Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])) {
                $response['msg'] = 'Le statut de la référence ou de l\'article choisi est incorrect, il doit être actif.';
            } else {
                $now = new DateTime();
                $emplacementTo = null;
                $emplacementFrom = null;
                $quantity = $chosenMvtQuantity;
                $associatedPickTracaMvt = null;
                $associatedDropTracaMvt = null;
                $chosenArticleToMoveAvailableQuantity = $chosenArticleToMove instanceof ReferenceArticle
                    ? $chosenArticleToMove->getQuantiteDisponible()
                    : $chosenArticleToMove->getQuantite();

                $chosenArticleToMoveStockQuantity = $chosenArticleToMove instanceof ReferenceArticle
                    ? $chosenArticleToMove->getQuantiteStock()
                    : $chosenArticleToMove->getQuantite();

                if ($chosenMvtType === MouvementStock::TYPE_SORTIE) {
                    if (intval($quantity) > $chosenArticleToMoveAvailableQuantity) {
                        $response['msg'] = 'La quantité saisie est superieure à la quantité disponible de la référence.';
                    } else {
                        $response['success'] = true;
                        $response['msg'] = "Mouvement créé avec succès";
                        $emplacementFrom = $chosenArticleToMove->getEmplacement();
                        if ($chosenArticleToMove instanceof ReferenceArticle) {
                            $chosenArticleToMove
                                ->setQuantiteStock($chosenArticleToMoveStockQuantity - $quantity);
                        } else {
                            if ($chosenArticleToMoveStockQuantity - $quantity === 0) {
                                $chosenArticleToMove->setStatut($unavailableArticleStatus);
                            } else {
                                $chosenArticleToMove
                                    ->setQuantite($chosenArticleToMoveStockQuantity - $quantity);
                            }
                        }
                    }
                } else if ($chosenMvtType === MouvementStock::TYPE_ENTREE) {
                    $response['success'] = true;
                    $response['msg'] = "Mouvement créé avec succès";
                    $emplacementTo = $chosenArticleToMove->getEmplacement();
                    if ($chosenArticleToMove instanceof ReferenceArticle) {
                        $chosenArticleToMove
                            ->setQuantiteStock($chosenArticleToMoveStockQuantity + $quantity);
                    } else {
                        $chosenArticleToMove
                            ->setQuantite($chosenArticleToMoveAvailableQuantity + $quantity);
                    }
                } else if ($chosenMvtType === MouvementStock::TYPE_TRANSFER) {
                    $chosenLocation = $emplacementRepository->find($chosenMvtLocation);
                    if (($chosenArticleToMove instanceof Article && !in_array($chosenArticleToMove->getStatut()->getCode(), [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]))
                        || ($chosenArticleToMove instanceof ReferenceArticle && $referenceArticleRepository->isUsedInQuantityChangingProcesses($chosenArticleToMove))) {
                        $response['msg'] = 'La référence saisie est présente dans une demande de livraison/collecte/transfert en cours de traitement, impossible de la transférer.';
                    } else if (empty($chosenLocation)) {
                        $response['msg'] = 'L\'emplacement saisi est inconnu.';
                    } else {
                        $response['success'] = true;
                        $response['msg'] = "Mouvement créé avec succès";
                        $quantity = $chosenArticleToMoveAvailableQuantity;
                        $emplacementTo = $chosenLocation;
                        $emplacementFrom = $chosenArticleToMove->getEmplacement();
                        $chosenArticleToMove->setEmplacement($emplacementTo);
                        $associatedPickTracaMvt = $trackingMovementService->createTrackingMovement(
                            $chosenArticleToMove->getBarCode(),
                            $emplacementFrom,
                            $loggedUser,
                            $now,
                            false,
                            true,
                            TrackingMovement::TYPE_PRISE,
                            ['quantity' => $quantity]
                        );
                        $trackingMovementService->persistSubEntities($entityManager, $associatedPickTracaMvt);
                        $createdPack = $associatedPickTracaMvt->getPack();

                        $associatedDropTracaMvt = $trackingMovementService->createTrackingMovement(
                            $createdPack,
                            $emplacementTo,
                            $loggedUser,
                            $now,
                            false,
                            true,
                            TrackingMovement::TYPE_DEPOSE,
                            ['quantity' => $quantity]
                        );
                        $trackingMovementService->persistSubEntities($entityManager, $associatedDropTracaMvt);
                        $entityManager->persist($associatedPickTracaMvt);
                        $entityManager->persist($associatedDropTracaMvt);
                    }
                }

                if ($response['success']) {
                    $newMvtStock = $mouvementStockService->createMouvementStock($loggedUser, $emplacementFrom, $quantity, $chosenArticleToMove, $chosenMvtType);
                    $mouvementStockService->finishMouvementStock($newMvtStock, $now, $emplacementTo);
                    if ($associatedDropTracaMvt && $associatedPickTracaMvt) {
                        $associatedPickTracaMvt->setMouvementStock($newMvtStock);
                        $associatedDropTracaMvt->setMouvementStock($newMvtStock);
                    }
                    $entityManager->persist($newMvtStock);
                    $entityManager->flush();
                }
            }
            return new JsonResponse($response);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_stock_movements_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return StreamedResponse
     * @throws Exception
     */
    public function getStockMovementsCSV(Request $request,
                                         MouvementStockService $mouvementStockService,
                                         EntityManagerInterface $entityManager,
                                         CSVExportService $CSVExportService): StreamedResponse
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $headers = [
                'date',
                'ordre',
                'référence article',
                'code barre référence article',
                'code barre article',
                'quantité',
                'origine',
                'destination',
                'type',
                'opérateur'
            ];

            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
            $movementIterator = $mouvementStockRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            return $CSVExportService->streamResponse(
                function ($output) use ($movementIterator, $mouvementStockService, $CSVExportService) {
                    foreach ($movementIterator as $movement) {
                        $mouvementStockService->putMovementLine($output, $CSVExportService, $movement);
                    }
                }, 'Export_mouvement_Stock.csv',
                $headers
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }
}
