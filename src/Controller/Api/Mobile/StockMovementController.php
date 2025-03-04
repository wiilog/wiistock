<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Project;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\EmplacementDataService;
use App\Service\ExceptionLoggerService;
use App\Service\FreeFieldService;
use App\Service\ProjectHistoryRecordService;
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class StockMovementController extends AbstractController {

    #[Route("/stock-movements", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postStockMovements(Request                     $request,
                                       EmplacementDataService      $locationDataService,
                                       ExceptionLoggerService      $exceptionLoggerService,
                                       TrackingMovementService     $trackingMovementService,
                                       FreeFieldService            $freeFieldService,
                                       ProjectHistoryRecordService $projectHistoryRecordService,
                                       AttachmentService           $attachmentService,
                                       EntityManagerInterface      $entityManager): Response
    {
        $successData = [];
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST');

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;
        $mouvementsNomade = json_decode($request->request->get('mouvements'), true);
        $finishMouvementTraca = [];
        $successData['data'] = [
            'errors' => [],
        ];

        $emptyGroups = [];
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $projectRepository = $entityManager->getRepository(Project::class);


        $mustReloadLocation = false;

        $uniqueIds = Stream::from($mouvementsNomade)
            ->filterMap(fn(array $movement) => $movement['date'])
            ->toArray();

        $alreadySavedMovements = !empty($uniqueIds)
            ? Stream::from($trackingMovementRepository->findBy(['uniqueIdForMobile' => $uniqueIds]))
                ->keymap(fn(TrackingMovement $trackingMovement) => [$trackingMovement->getUniqueIdForMobile(), $trackingMovement])
                ->toArray()
            : [];

        foreach ($mouvementsNomade as $index => $mvt) {
            $invalidLocationTo = '';
            try {
                $entityManager->transactional(function ()
                use (
                    $projectHistoryRecordService,
                    $projectRepository,
                    $freeFieldService,
                    &$numberOfRowsInserted,
                    $mvt,
                    $nomadUser,
                    $request,
                    $attachmentService,
                    $index,
                    &$invalidLocationTo,
                    &$finishMouvementTraca,
                    $entityManager,
                    $trackingMovementService,
                    &$emptyGroups,
                    $emplacementRepository,
                    $packRepository,
                    $articleRepository,
                    $statutRepository,
                    $trackingMovementRepository,
                    $locationDataService,
                    &$mustReloadLocation,
                    $alreadySavedMovements,
                ) {
                    $trackingTypes = [
                        TrackingMovement::TYPE_PRISE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_PRISE),
                        TrackingMovement::TYPE_DEPOSE => $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_DEPOSE),
                    ];

                    if (!isset($alreadySavedMovements[$mvt['date']])) {
                        $options = [
                            'uniqueIdForMobile' => $mvt['date'],
                            'entityManager' => $entityManager,
                            'quantity' => $mvt['quantity'],
                            'commentaire' => isset($mvt['comment']) ? $mvt['comment'] : '',
                        ];

                        /** @var Statut $type */
                        $type = $trackingTypes[$mvt['type']];
                        $location = $locationDataService->findOrPersistWithCache($entityManager, $mvt['ref_emplacement'], $mustReloadLocation);

                        $dateArray = explode('_', $mvt['date']);
                        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

                        //trouve les ULs sans association à un article car les ULs
                        //associés a des articles SONT des articles donc on les traite normalement
                        $pack = $packRepository->findWithoutArticle($mvt['ref_article']);

                        //dans le cas d'une prise stock sur une UL, on ne peut pas créer de
                        //mouvement de stock sur l'UL donc on ignore la partie stock et
                        //on créé juste un mouvement de prise sur l'UL et ses articles
                        if ($pack && !$pack->getChildArticles()->isEmpty()) {
                            $packMvt = $trackingMovementService->treatLUPicking(
                                $pack,
                                $location,
                                $nomadUser,
                                $date,
                                $mvt,
                                $type,
                                $options,
                                $entityManager,
                                $emptyGroups,
                                $numberOfRowsInserted
                            );
                            $trackingMovementService->manageTrackingMovementsForLU(
                                $pack,
                                $entityManager,
                                $mvt,
                                $type,
                                $nomadUser,
                                $location,
                                $date,
                                $emptyGroups,
                                $numberOfRowsInserted
                            );

                            if ($type->getCode() === TrackingMovement::TYPE_PRISE) {
                                if (isset($mvt['projectId'])) {
                                    $project = $projectRepository->find($mvt['projectId']);
                                    $projectHistoryRecordService->changeProject($entityManager, $pack, $project, $date);

                                    foreach ($pack->getChildArticles() as $article) {
                                        $projectHistoryRecordService->changeProject($entityManager, $article, $project, $date);
                                    }
                                }
                                $signatureFile = $request->files->get("signature_$index");
                                $photoFile = $request->files->get("photo_$index");
                                $fileNames = [];
                                if (!empty($signatureFile)) {
                                    $fileNames = array_merge($fileNames, $attachmentService->saveFile($signatureFile));
                                }
                                if (!empty($photoFile)) {
                                    $fileNames = array_merge($fileNames, $attachmentService->saveFile($photoFile));
                                }
                                $attachments = $attachmentService->createAttachmentsDeprecated($fileNames);
                                foreach ($attachments as $attachment) {
                                    $entityManager->persist($attachment);
                                    $packMvt->addAttachment($attachment);
                                }
                            }

                            $entityManager->persist($packMvt);
                            $entityManager->flush();
                        } else { //cas mouvement stock classique sur un article ou une ref
                            $options += $trackingMovementService->treatStockMovement($entityManager, $type?->getCode(), $mvt, $nomadUser, $location, $date);
                            if ($options['invalidLocationTo'] ?? null) {
                                $invalidLocationTo = $options['invalidLocationTo'];
                                throw new Exception(TrackingMovementService::INVALID_LOCATION_TO);
                            }

                            $options += $trackingMovementService->treatTrackingData($mvt, $request->files, $index);
                            $createdMvt = $trackingMovementService->createTrackingMovement(
                            //either reference or article
                                $mvt["ref_article"],
                                $location,
                                $nomadUser,
                                $date,
                                true,
                                $mvt['finished'],
                                $type,
                                $options,
                            );

                            $entityManager->persist($createdMvt);

                            $associatedPack = $createdMvt->getPack();
                            $createdMvt->setLogisticUnitParent($associatedPack?->getArticle()?->getCurrentLogisticUnit());

                            if ($type->getCode() === TrackingMovement::TYPE_PRISE && $associatedPack?->getArticle()?->getCurrentLogisticUnit()) {
                                $movement = $trackingMovementService->persistTrackingMovement(
                                    $entityManager,
                                    $associatedPack,
                                    $location,
                                    $nomadUser,
                                    $date,
                                    true,
                                    TrackingMovement::TYPE_PICK_LU,
                                    false,
                                    $options
                                )['movement'];
                                $logisticUnit = $associatedPack->getArticle()->getCurrentLogisticUnit();
                                $movement->setLogisticUnitParent($logisticUnit);
                                $createdMvt->setMainMovement($movement);
                                $associatedPack->getArticle()->setCurrentLogisticUnit(null);
                                $logisticUnit->setQuantity($logisticUnit->getQuantity() - $associatedPack->getQuantity());
                                $trackingMovementService->persistSubEntities($entityManager, $movement);
                                $entityManager->persist($movement);
                                $numberOfRowsInserted++;
                            }

                            if ($associatedPack) {
                                $associatedGroup = $associatedPack->getGroup();

                                if ($associatedGroup) {
                                    $associatedGroup->removeContent($associatedPack);
                                    if ($associatedGroup->getContent()->isEmpty()) {
                                        $emptyGroups[] = $associatedGroup->getCode();
                                    }
                                }
                            }

                            $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                            $entityManager->persist($createdMvt);
                            $numberOfRowsInserted++;

                            if ($type?->getCode() === TrackingMovement::TYPE_DEPOSE) {
                                $finishMouvementTraca[] = $mvt['ref_article'];
                            }
                        }
                    }
                });
            } catch (Throwable $throwable) {
                if (!$entityManager->isOpen()) {
                    /** @var EntityManagerInterface $entityManager */
                    $entityManager = new EntityManager($entityManager->getConnection(), $entityManager->getConfiguration());
                    $entityManager->clear();
                    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                    $statutRepository = $entityManager->getRepository(Statut::class);
                    $nomadUser = $utilisateurRepository->find($nomadUser->getId());
                    $mustReloadLocation = true;
                    $trackingMovementService->stockStatuses = [];
                }

                if ($throwable->getMessage() === TrackingMovementService::INVALID_LOCATION_TO) {
                    $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                } else if ($throwable->getMessage() === Pack::PACK_IS_GROUP) {
                    $successData['data']['errors'][$mvt['ref_article']] = 'L\'unité logistique scannée est un groupe';
                } else {
                    $exceptionLoggerService->sendLog($throwable, $request);
                    $successData['data']['errors'][$mvt['ref_article']] = 'Une erreur s\'est produite lors de l\'enregistrement de ' . $mvt['ref_article'];
                }
            }
        }

        $trackingMovementService->clearTrackingMovement($mouvementsNomade, $finishMouvementTraca, $alreadySavedMovements);
        $entityManager->flush();

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $successData['success'] = true;
        $successData['data']['status'] = ($numberOfRowsInserted === 0)
            ? 'Aucun mouvement à synchroniser.'
            : ($numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s);
        $successData['data']['movementCounter'] = $numberOfRowsInserted;

        if (!empty($emptyGroups)) {
            $successData['data']['emptyGroups'] = $emptyGroups;
        }

        $response->setContent(json_encode($successData));
        return $response;
    }
}
