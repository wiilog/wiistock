<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Dispatch;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterRecord;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\TrackingMovement;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use WiiCommon\Helper\Stream;
use App\Repository\TrackingMovementRepository;
use WiiCommon\Utils\DateTime;
use Exception;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use DateTimeInterface;

class TrackingMovementService
{
    public const INVALID_LOCATION_TO = 'invalid-location-to';

    private $templating;
    private $security;
    private $entityManager;
    private $attachmentService;
    private $freeFieldService;
    private $locationClusterService;
    private $visibleColumnService;
    private $groupService;

    public function __construct(EntityManagerInterface $entityManager,
                                LocationClusterService $locationClusterService,
                                Twig_Environment $templating,
                                FreeFieldService $freeFieldService,
                                Security $security,
                                GroupService $groupService,
                                VisibleColumnService $visibleColumnService,
                                AttachmentService $attachmentService)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->attachmentService = $attachmentService;
        $this->locationClusterService = $locationClusterService;
        $this->freeFieldService = $freeFieldService;
        $this->visibleColumnService = $visibleColumnService;
        $this->groupService = $groupService;
    }

    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_TRACA, $this->security->getUser());
        $queryResult = $trackingMovementRepository->findByParamsAndFilters($params, $filters);

        $mouvements = $queryResult['data'];

        $rows = [];
        foreach ($mouvements as $mouvement) {
            $rows[] = $this->dataRowMouvement($mouvement);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }


    public function getFromColumnData(?TrackingMovement $movement): array
    {
        $data = [
            'entityPath' => null,
            'entityId' => null,
            'fromLabel' => null,
            'from' => '-',
        ];
        if (isset($movement)) {
            if ($movement->getArrivage()) {
                $data ['entityPath'] = 'arrivage_show';
                $data ['fromLabel'] = 'arrivage.arrivage';
                $data ['entityId'] = $movement->getArrivage()->getId();
                $data ['from'] = $movement->getArrivage()->getNumeroArrivage();
            } else if ($movement->getReception()) {
                $data ['entityPath'] = 'reception_show';
                $data ['fromLabel'] = 'réception.réception';
                $data ['entityId'] = $movement->getReception()->getId();
                $data ['from'] = $movement->getReception()->getNumber();
            } else if ($movement->getDispatch()) {
                $data ['entityPath'] = 'dispatch_show';
                $data ['fromLabel'] = 'acheminement.Acheminement';
                $data ['entityId'] = $movement->getDispatch()->getId();
                $data ['from'] = $movement->getDispatch()->getNumber();
            } else if ($movement->getMouvementStock() && $movement->getMouvementStock()->getTransferOrder()) {
                $data ['entityPath'] = 'transfer_order_show';
                $data ['fromLabel'] = 'Transfert de stock';
                $data ['entityId'] = $movement->getMouvementStock()->getTransferOrder()->getId();
                $data ['from'] = $movement->getMouvementStock()->getTransferOrder()->getNumber();
            }
        }
        return $data;
    }

    public function dataRowMouvement(TrackingMovement $movement)
    {
        $fromColumnData = $this->getFromColumnData($movement);

        $categoryFFRepository = $this->entityManager->getRepository(CategorieCL::class);
        $freeFieldsRepository = $this->entityManager->getRepository(FreeField::class);

        $categoryFF = $categoryFFRepository->findOneBy(['label' => CategorieCL::MVT_TRACA]);
        $category = CategoryType::MOUVEMENT_TRACA;
        $freeFields = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categoryFF);
        $trackingPack = $movement->getPack();

        $rows = [
            'id' => $movement->getId(),
            'date' => FormatHelper::datetime($movement->getDatetime()),
            'code' => FormatHelper::pack($trackingPack),
            'origin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'group' => $movement->getPackParent()
                ? ($movement->getPackParent()->getCode() . '-' . ($movement->getGroupIteration() ?: '?'))
                : '',
            'location' => FormatHelper::location($movement->getEmplacement()),
            'reference' => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getReference()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : ($trackingPack && $trackingPack->getLastTracking() && $trackingPack->getLastTracking()->getMouvementStock()
                        ? $trackingPack->getLastTracking()->getMouvementStock()->getArticle()->getArticleFournisseur()->getReferenceArticle()->getLibelle()
                        : '')),
            "label" => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getLibelle()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getLabel()
                    : ($trackingPack && $trackingPack->getLastTracking() && $trackingPack->getLastTracking()->getMouvementStock()
                        ? $trackingPack->getLastTracking()->getMouvementStock()->getArticle()->getLabel()
                        : '')),
            "quantity" => $movement->getQuantity() ?: '',
            "type" => FormatHelper::status($movement->getType()),
            "operator" => FormatHelper::user($movement->getOperateur()),
            "actions" => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $movement,
                'attachmentsLength' => $movement->getAttachments()->count(),
            ])
        ];

        foreach ($freeFields as $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeField['id']);
            $rows[$freeFieldName] = $this->freeFieldService->serializeValue([
                "valeur" => $movement->getFreeFieldValue($freeField["id"]),
                "typage" => $freeField["typage"],
            ]);
        }

        return $rows;
    }

    public function handleGroups(array $data, EntityManagerInterface $entityManager, Utilisateur $operator, DateTime $date): array {
        $packRepository = $entityManager->getRepository(Pack::class);
        $parentCode = $data['parent'];

        /** @var Pack $parentPack */
        $parentPack = $packRepository->findOneBy(['code' => $parentCode]);

        if ($parentPack && !$parentPack->isGroup()) {
            return [
                'success' => false,
                'msg' => 'Le colis contenant choisi est un colis, veuillez choisir un groupage valide.'
            ];
        } else {
            $errors = [];
            $colisArray = explode(',', $data['colis']);
            foreach ($colisArray as $colis) {
                $pack = $packRepository->findOneBy(['code' => $colis]);
                $isParentPack = $pack && $pack->isGroup();
                $isChildPack = $pack && $pack->getParent();
                if ($isParentPack || $isChildPack) {
                    $errors[] = $colis;
                }
            }

            if (!empty($errors)) {
                return [
                    'success' => false,
                    'msg' => 'Les colis '
                        . implode(', ', $errors)
                        . ' sont des groupages ou sont déjà présents dans un groupe, veuillez choisir des colis valides.'
                ];
            }
            else {
                $createdMovements = [];
                $isNewGroupInstance = false;
                if (!$parentPack) {
                    $parentPack = $this->groupService->createParentPack($data);
                    $entityManager->persist($parentPack);
                    $isNewGroupInstance = true;
                } else if ($parentPack->getChildren()->isEmpty()) {
                    $parentPack->incrementGroupIteration();
                    $isNewGroupInstance = true;
                }

                if ($isNewGroupInstance) {
                    $groupingTrackingMovement = $this->createTrackingMovement(
                        $parentPack,
                        null,
                        $operator,
                        $date,
                        $data['fromNomade'] ?? false,
                        true,
                        TrackingMovement::TYPE_GROUP,
                        ['parent' => $parentPack]
                    );

                    $entityManager->persist($groupingTrackingMovement);
                    $createdMovements[] = $groupingTrackingMovement;
                }

                foreach ($colisArray as $colis) {
                    $groupingTrackingMovement = $this->createTrackingMovement(
                        $colis,
                        null,
                        $operator,
                        $date,
                        $data['fromNomade'] ?? false,
                        true,
                        TrackingMovement::TYPE_GROUP,
                        [
                            'parent' => $parentPack,
                            'onlyPack' => true
                        ]
                    );

                    $pack = $groupingTrackingMovement->getPack();
                    if ($pack) {
                        $pack->setParent($parentPack);
                    }

                    $entityManager->persist($groupingTrackingMovement);
                    $createdMovements[] = $groupingTrackingMovement;
                }
                return [
                    'success' => true,
                    'msg' => 'OK',
                    'createdMovements' => $createdMovements
                ];
            }
        }
    }

    public function createTrackingMovement($packOrCode,
                                           ?Emplacement $location,
                                           Utilisateur $user,
                                           DateTime $date,
                                           bool $fromNomade,
                                           ?bool $finished,
                                           $trackingType,
                                           array $options = []): TrackingMovement
    {
        $entityManager = $options['entityManager'] ?? $this->entityManager;
        $statutRepository = $entityManager->getRepository(Statut::class);

        $type = ($trackingType instanceof Statut)
            ? $trackingType
            : (is_string($trackingType)
                ? $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, $trackingType)
                : $statutRepository->find($trackingType));

        if (!isset($type)) {
            throw new Exception('Le type de mouvement traca donné est invalide');
        }

        $commentaire = $options['commentaire'] ?? null;
        $mouvementStock = $options['mouvementStock'] ?? null;
        $fileBag = $options['fileBag'] ?? null;
        $quantity = $options['quantity'] ?? 1;
        $from = $options['from'] ?? null;
        $receptionReferenceArticle = $options['receptionReferenceArticle'] ?? null;
        $uniqueIdForMobile = $options['uniqueIdForMobile'] ?? null;
        $natureId = $options['natureId'] ?? null;
        $disableUngrouping = $options['disableUngrouping'] ?? false;

        /** @var Pack|null $parent */
        $parent = $options['parent'] ?? null;
        $removeFromGroup = $options['removeFromGroup'] ?? false;

        $pack = $this->persistPack($entityManager, $packOrCode, $quantity, $natureId, $options['onlyPack'] ?? false);

        $tracking = new TrackingMovement();
        $tracking
            ->setQuantity($quantity)
            ->setEmplacement($location)
            ->setOperateur($user)
            ->setUniqueIdForMobile($uniqueIdForMobile ?: ($fromNomade ? $this->generateUniqueIdForMobile($entityManager, $date) : null))
            ->setDatetime($date)
            ->setFinished($finished)
            ->setType($type)
            ->setMouvementStock($mouvementStock)
            ->setCommentaire(!empty($commentaire) ? $commentaire : null);

        $pack->addTrackingMovement($tracking);

        $pack->setLastTracking($tracking);
        $this->managePackLinksWithTracking($entityManager, $tracking);
        $this->manageTrackingLinks($entityManager, $tracking, $from, $receptionReferenceArticle);
        $this->manageTrackingFiles($tracking, $fileBag);

        if (!$disableUngrouping
             && $pack->getParent()
             && in_array($type->getNom(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE])) {
            $type = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_UNGROUP);

            $trackingUngroup = new TrackingMovement();
            $trackingUngroup
                ->setQuantity($quantity)
                ->setOperateur($user)
                ->setUniqueIdForMobile($fromNomade ? $this->generateUniqueIdForMobile($entityManager, $date) : null)
                ->setDatetime($date)
                ->setFinished($finished)
                ->setType($type)
                ->setPackParent($pack->getParent())
                ->setGroupIteration($pack->getParent() ? $pack->getParent()->getGroupIteration() : null)
                ->setMouvementStock($mouvementStock)
                ->setCommentaire(!empty($commentaire) ? $commentaire : null);
            $pack->addTrackingMovement($trackingUngroup);
            if ($removeFromGroup) {
                $pack->setParent(null);
            }
            $entityManager->persist($trackingUngroup);
        } else if($parent) {
            // Si pas de mouvement de dégroupage, on set le parent
            $tracking->setPackParent($parent);
            $tracking->setGroupIteration($parent->getGroupIteration());
        }

        return $tracking;
    }

    public function persistPack(EntityManagerInterface $entityManager,
                                $packOrCode,
                                $quantity,
                                $natureId,
                                bool $onlyPack = false): Pack {
        $packRepository = $entityManager->getRepository(Pack::class);

        $codePack = $packOrCode instanceof Pack ? $packOrCode->getCode() : $packOrCode;

        $pack = ($packOrCode instanceof Pack)
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);

        if ($onlyPack && $pack && $pack->isGroup()) {
            throw new Exception(Pack::PACK_IS_GROUP);
        }

        if (!isset($pack)) {
            $pack = new Pack();
            $pack
                ->setQuantity($quantity)
                ->setCode(str_replace("    ", " ", $codePack));
            $entityManager->persist($pack);
        }

        if (!empty($natureId)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $nature = $natureRepository->find($natureId);

            if (!empty($nature)) {
                $pack->setNature($nature);
            }
        }

        return $pack;
    }

    private function manageTrackingLinks(EntityManagerInterface $entityManager,
                                         TrackingMovement $tracking,
                                         $from,
                                         $receptionReferenceArticle) {

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $pack = $tracking->getPack();
        $packCode = $pack ? $pack->getCode() : null;

        if ($pack) {
            $alreadyLinkedArticle = $pack->getArticle();
            $alreadyLinkedReferenceArticle = $pack->getReferenceArticle();
            if (!isset($alreadyLinkedArticle) && !isset($alreadyLinkedReferenceArticle)) {
                $refOrArticle = (
                    $referenceArticleRepository->findOneBy(['barCode' => $packCode])
                    ?: $articleRepository->findOneBy(['barCode' => $packCode])
                );

                if ($refOrArticle instanceof ReferenceArticle) {
                    $pack->setReferenceArticle($refOrArticle);
                }
                else if ($refOrArticle instanceof Article) {
                    $pack->setArticle($refOrArticle);
                }
            }
        }

        if (isset($from)) {
            if ($from instanceof Reception) {
                $tracking->setReception($from);
            } else if ($from instanceof Dispatch) {
                $tracking->setDispatch($from);
            }
        }

        if (isset($receptionReferenceArticle)) {
            $tracking->setReceptionReferenceArticle($receptionReferenceArticle);
        }
    }

    private function manageTrackingFiles(TrackingMovement $tracking, $fileBag) {
        if (isset($fileBag)) {
            $attachments = $this->attachmentService->createAttachements($fileBag);
            foreach ($attachments as $attachment) {
                $tracking->addAttachment($attachment);
            }
        }
    }

    private function generateUniqueIdForMobile(EntityManagerInterface $entityManager,
                                               DateTime $date): string
    {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        //same format as moment.defaultFormat
        $dateStr = $date->format(DateTimeInterface::ATOM);
        $randomLength = 9;
        do {
            $random = strtolower(substr(sha1(rand()), 0, $randomLength));
            $uniqueId = $dateStr . '_' . $random;
            $existingMovements = $trackingMovementRepository->findBy(['uniqueIdForMobile' => $uniqueId]);
        } while (!empty($existingMovements));

        return $uniqueId;
    }

    public function persistSubEntities(EntityManagerInterface $entityManager,
                                       TrackingMovement $trackingMovement) {
        $pack = $trackingMovement->getPack();
        if (!empty($pack)) {
            $entityManager->persist($pack);
        }
        $linkedPackLastDrop = $trackingMovement->getLinkedPackLastDrop();
        if ($linkedPackLastDrop) {
            $entityManager->persist($linkedPackLastDrop);
        }

        $linkedPackLastTracking = $trackingMovement->getLinkedPackLastTracking();
        if ($linkedPackLastTracking) {
            $entityManager->persist($linkedPackLastTracking);
        }

        foreach ($trackingMovement->getAttachments() as $attachement) {
            $entityManager->persist($attachement);
        }
    }

    public function managePackLinksWithTracking(EntityManagerInterface $entityManager,
                                                TrackingMovement $tracking): void {

        $pack = $tracking->getPack();
        $lastTrackingMovements = $pack ? $pack->getTrackingMovements()->toArray() : [];

        $previousLastTracking = (!empty($lastTrackingMovements) && count($lastTrackingMovements) > 1)
            ? $lastTrackingMovements[1]
            : null;

        // si c'est une prise ou une dépose on vide ses colis liés
        $packsAlreadyExisting = $tracking->getLinkedPackLastDrop();
        if ($packsAlreadyExisting) {
            $packsAlreadyExisting->setLastDrop(null);
        }

        if ($pack && $tracking->isDrop()) {
            $pack->setLastDrop($tracking);
        }

        $location = $tracking->getEmplacement();
        if ($pack && $location) {
            /** @var LocationCluster $cluster */
            foreach ($location->getClusters() as $cluster) {
                $record = $cluster->getLocationClusterRecord($pack);

                if (isset($record)) {
                    $currentFirstDrop = $record->getFirstDrop();
                    if ($currentFirstDrop && ($currentFirstDrop->getEmplacement() !== $location)) {
                        $entityManager->remove($record);
                        $record = null;
                    }
                }

                if (!isset($record)) {
                    $record = new LocationClusterRecord();
                    $record
                        ->setPack($pack)
                        ->setLocationCluster($cluster);
                    $entityManager->persist($record);
                }

                if ($tracking->isDrop()) {
                    $record->setActive(true);
                    $previousRecordLastTracking = $record->getLastTracking();
                    // check if pack previous last tracking !== record previous lastTracking
                    // IF not equals then we set firstDrop
                    // ELSE that is to say the pack come from the location cluster
                    if (!$previousRecordLastTracking
                        || !$previousLastTracking
                        || ($previousRecordLastTracking->getId() !== $previousLastTracking->getId())) {
                        $record->setFirstDrop($tracking);
                    }

                    $this->locationClusterService->setMeter(
                        $entityManager,
                        LocationClusterService::METER_ACTION_INCREASE,
                        $tracking->getDatetime(),
                        $cluster
                    );

                    if ($previousLastTracking
                        && $previousLastTracking->isTaking()) {

                        $locationPreviousLastTracking = $previousLastTracking->getEmplacement();
                        $locationClustersPreviousLastTracking = $locationPreviousLastTracking ? $locationPreviousLastTracking->getClusters() : [];
                        /** @var LocationCluster $locationClusterPreviousLastTracking */
                        foreach ($locationClustersPreviousLastTracking as $locationClusterPreviousLastTracking) {
                            $this->locationClusterService->setMeter(
                                $entityManager,
                                LocationClusterService::METER_ACTION_INCREASE,
                                $tracking->getDatetime(),
                                $cluster,
                                $locationClusterPreviousLastTracking
                            );
                        }
                    }
                }
                else if (isset($record)) {
                    $record->setActive(false);
                }

                if (isset($record)) {
                    // set last tracking after check of drop
                    $record->setLastTracking($tracking);
                }
            }
        }
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $columnsVisible = $currentUser->getColumnsVisibleForTrackingMovement();
        $categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::MVT_TRACA]);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::MOUVEMENT_TRACA, $categorieCL);

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Issu de', 'name' => 'origin', 'orderable' => false],
            ['title' => 'Date', 'name' => 'date'],
            ['title' => 'mouvement de traçabilité.Colis', 'name' => 'code', 'translated' => true],
            ['title' => 'Référence', 'name' => 'reference'],
            ['title' => 'Libellé',  'name' => 'label'],
            ['title' => 'Groupe',  'name' => 'group'],
            ['title' => 'Quantité', 'name' => 'quantity'],
            ['title' => 'Emplacement', 'name' => 'location'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Opérateur', 'name' => 'operator']
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function persistTrackingForArrivalPack(EntityManagerInterface $entityManager,
                                                  Pack $pack,
                                                  ?Emplacement $location,
                                                  Utilisateur $user,
                                                  DateTime $date,
                                                  Arrivage $arrivage) {

        $mouvementDepose = $this->createTrackingMovement(
            $pack,
            $location,
            $user,
            $date,
            false,
            true,
            TrackingMovement::TYPE_DEPOSE,
            ['from' => $arrivage]
        );
        $this->persistSubEntities($entityManager, $mouvementDepose);
        $entityManager->persist($mouvementDepose);
    }

    public function putMovementLine($handle,
                                    CSVExportService $CSVExportService,
                                    FreeFieldService $freeFieldService,
                                    array $movement,
                                    array $attachement,
                                    array $freeFieldsConfig)
    {

        $attachementName = $attachement[$movement['id']] ?? ' ' ;

        if(!empty($movement['numeroArrivage'])) {
           $origine =  'Arrivage-' . $movement['numeroArrivage'];
        }
        if(!empty($movement['receptionNumber'])) {
            $origine = 'Reception-' . $movement['receptionNumber'];
        }
        if(!empty($movement['dispatchNumber'])) {
            $origine = 'Acheminement-' . $movement['dispatchNumber'];
        }
        if(!empty($movement['transferNumber'])) {
            $origine = 'transfert-' . $movement['transferNumber'];
        }

        $data = [
            FormatHelper::datetime($movement['datetime']),
            $movement['code'],
            $movement['locationLabel'],
            $movement['quantity'],
            $movement['typeName'],
            $movement['operatorUsername'],
            $movement['commentaire'],
            $attachementName,
            $origine ?? ' ',
            $movement['numeroCommandeListArrivage'] && !empty($movement['numeroCommandeListArrivage'])
                        ? implode(', ', $movement['numeroCommandeListArrivage'])
                        : ($movement['orderNumber'] ?: ''),
            $movement['isUrgent'] ? 'oui' : 'non',
            $movement['packParent'],
        ];

        foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
            $data[] = $freeFieldService->serializeValue([
                'typage' => $freeFieldsConfig['freeFieldsIdToTyping'][$freeFieldId],
                'valeur' => $movement['freeFields'][$freeFieldId] ?? ''
            ]);
        }
        $CSVExportService->putLine($handle, $data);
    }

    public function getMobileUserPicking(EntityManagerInterface $entityManager, Utilisateur $user): array {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        return Stream::from(
            $trackingMovementRepository->getPickingByOperatorAndNotDropped($user, TrackingMovementRepository::MOUVEMENT_TRACA_DEFAULT, [], true)
        )
            ->filterMap(function (array $picking) use ($trackingMovementRepository) {
                $id = $picking['id'];
                unset($picking['id']);

                $isGroup = $picking['isGroup'] == '1';

                if ($isGroup) {
                    $tracking = $trackingMovementRepository->find($id);
                    $subPacks = $tracking
                        ->getPack()
                        ->getChildren()
                        ->map(fn(Pack $pack) => $pack->serialize());
                }

                $picking['subPacks'] = $subPacks ?? [];

                return (!$isGroup || !empty($subPacks)) ? $picking : null;
            })
            ->toArray();
    }

    public function finishTrackingMovement(?TrackingMovement $trackingMovement): ?string {
        if ($trackingMovement) {
            $type = $trackingMovement->getType();
            if ($type->getNom() === TrackingMovement::TYPE_PRISE) {
                $trackingMovement->setFinished(true);
                return $trackingMovement->getPack()->getCode();
            }
        }
        return null;
    }
}
