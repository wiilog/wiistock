<?php

namespace App\Service;

use App\Controller\AbstractController;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Dispatch;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterRecord;
use App\Entity\MouvementStock;
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
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use App\Repository\TrackingMovementRepository;
use DateTime;
use Exception;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use DateTimeInterface;
use WiiCommon\Helper\StringHelper;

class TrackingMovementService extends AbstractController
{
    public const INVALID_LOCATION_TO = 'invalid-location-to';

    private $templating;
    private $security;
    private $entityManager;
    private $attachmentService;

    #[Required]
    public FreeFieldService $freeFieldService;

    private $locationClusterService;
    private $visibleColumnService;
    private $groupService;

    #[Required]
    public MouvementStockService $stockMovementService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    public array $stockStatuses = [];

    private ?array $freeFieldsConfig = null;

    public function __construct(EntityManagerInterface $entityManager,
                                LocationClusterService $locationClusterService,
                                Twig_Environment $templating,
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
        $this->visibleColumnService = $visibleColumnService;
        $this->groupService = $groupService;
    }

    public function getDataForDatatable($params = null): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        /** @var Utilisateur $user */
        $user = $this->security->getUser();
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_TRACA, $user);

        $queryResult = $trackingMovementRepository->findByParamsAndFilters($params, $filters, $user, $this->visibleColumnService);

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
            if ($movement->getDispatch()) {
                $data ['entityPath'] = 'dispatch_show';
                $data ['fromLabel'] = $this->translation->translate('Demande', 'Acheminements', 'Général', 'Acheminement', false);
                $data ['entityId'] = $movement->getDispatch()->getId();
                $data ['from'] = $movement->getDispatch()->getNumber();
            } else if ($movement->getArrivage()) {
                $data ['entityPath'] = 'arrivage_show';
                $data ['fromLabel'] = $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'Arrivage UL', false);
                $data ['entityId'] = $movement->getArrivage()->getId();
                $data ['from'] = $movement->getArrivage()->getNumeroArrivage();
            } else if ($movement->getReception()) {
                $data ['entityPath'] = 'reception_show';
                $data ['fromLabel'] = $this->translation->translate('Ordre', 'Réceptions', 'Réception', false);
                $data ['entityId'] = $movement->getReception()->getId();
                $data ['from'] = $movement->getReception()->getNumber();
            } else if ($movement->getMouvementStock() && $movement->getMouvementStock()->getTransferOrder()) {
                $data ['entityPath'] = 'transfer_order_show';
                $data ['fromLabel'] = 'Transfert de stock';
                $data ['entityId'] = $movement->getMouvementStock()->getTransferOrder()->getId();
                $data ['from'] = $movement->getMouvementStock()->getTransferOrder()->getNumber();
            }
        }
        return $data;
    }

    public function dataRowMouvement(TrackingMovement $movement): array {
        $fromColumnData = $this->getFromColumnData($movement);

        $trackingPack = $movement->getPack();

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig(
                $this->entityManager,
                CategorieCL::MVT_TRACA,
                CategoryType::MOUVEMENT_TRACA
            );
        }

        $row = [
            'id' => $movement->getId(),
            'date' => $this->formatService->datetime($movement->getDatetime()),
            'code' => $this->formatService->pack($trackingPack),
            'origin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'group' => $movement->getPackParent()
                ? ($movement->getPackParent()->getCode() . '-' . ($movement->getGroupIteration() ?: '?'))
                : '',
            'location' => $this->formatService->location($movement->getEmplacement()),
            'reference' => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getReference()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : ($trackingPack && $trackingPack->getLastTracking() && $trackingPack->getLastTracking()->getMouvementStock()
                        ? $trackingPack->getLastTracking()->getMouvementStock()->getArticle()?->getArticleFournisseur()->getReferenceArticle()->getLibelle()
                        : '')),
            "label" => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getLibelle()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getLabel()
                    : ($trackingPack && $trackingPack->getLastTracking() && $trackingPack->getLastTracking()->getMouvementStock()
                        ? $trackingPack->getLastTracking()->getMouvementStock()->getArticle()?->getLabel()
                        : '')),
            "quantity" => $movement->getQuantity() ?: '',
            "type" => $this->translation->translate('Traçabilité', 'Mouvements', $movement->getType()->getNom()) ,
            "operator" => $this->formatService->user($movement->getOperateur()),
            "actions" => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $movement,
                'attachmentsLength' => $movement->getAttachments()->count(),
            ])
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $movement->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
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
            $packCodes = explode(',', $data['colis']);
            foreach ($packCodes as $packCode) {
                $pack = $packRepository->findOneBy(['code' => $packCode]);
                $isParentPack = $pack && $pack->isGroup();
                $isChildPack = $pack && $pack->getParent();
                if ($isParentPack || $isChildPack) {
                    $errors[] = $packCode;
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

                foreach ($packCodes as $packCode) {
                    $pack = $this->persistPack($entityManager, $packCode, 1, null);
                    $location = $location ?? ($pack->getLastTracking() ? $pack->getLastTracking()->getEmplacement() : null);

                    $groupingTrackingMovement = $this->createTrackingMovement(
                        $pack,
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

                    $pack->setParent($parentPack);

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
        $attachments = $options['attachments'] ?? null;

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
            ->setCommentaire(!empty($commentaire) ? StringHelper::cleanedComment($commentaire) : null);

        if ($attachments) {
            foreach($attachments as $attachment) {
                $tracking->addAttachment($attachment);
            }
        }

        $pack->addTrackingMovement($tracking);

        $pack->setLastTracking($tracking);
        $this->managePackLinksWithTracking($entityManager, $tracking);
        $this->manageTrackingLinks($entityManager, $tracking, $from, $receptionReferenceArticle);
        $this->manageTrackingFiles($tracking, $fileBag);

        if (!$disableUngrouping
             && $pack->getParent()
             && in_array($type?->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE])) {
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
                ->setCommentaire(!empty($commentaire) ? StringHelper::cleanedComment($commentaire) : null);
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
        $locationClusterRecordRepository = $entityManager->getRepository(LocationClusterRecord::class);

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
                $record = $pack->getId()
                    ? $locationClusterRecordRepository->findOneByPackAndCluster($cluster, $pack)
                    : null;

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

        $columnsVisible = $currentUser->getVisibleColumns()['trackingMovement'];
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::MOUVEMENT_TRACA, CategorieCL::MVT_TRACA);

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Issu de', false), 'name' => 'origin', 'orderable' => false],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Date', false), 'name' => 'date'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Unité logistique', false), 'name' => 'code'],
            ['title' => $this->translation->translate('Traçabilité', 'Mouvements', 'Référence', false), 'name' => 'reference'],
            ['title' => $this->translation->translate('Traçabilité', 'Mouvements', 'Libellé', false),  'name' => 'label'],
            ['title' => $this->translation->translate('Traçabilité', 'Mouvements', 'Groupe', false),  'name' => 'group'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Quantité', false), 'name' => 'quantity'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Emplacement', false), 'name' => 'location'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type', false), 'name' => 'type'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Opérateur', false), 'name' => 'operator']
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
                                    array $movement,
                                    array $attachement,
                                    array $freeFieldsConfig)
    {

        $attachementName = $attachement[$movement['id']] ?? ' ' ;

        if(!empty($movement['numeroArrivage'])) {
           $origine =  $this->translation->translate("Traçabilité", "Arrivages UL", "Divers", "Arrivage UL", false) . '-' . $movement['numeroArrivage'];
        }
        if(!empty($movement['receptionNumber'])) {
            $origine = $this->translation->translate("Ordre", "Réceptions", "Reception", false) . '-' . $movement['receptionNumber'];
        }
        if(!empty($movement['dispatchNumber'])) {
            $origine = $this->translation->translate("Demande", "Acheminements", "Général", "Acheminement", false) . '-' . $movement['dispatchNumber'];
        }
        if(!empty($movement['transferNumber'])) {
            $origine = 'transfert-' . $movement['transferNumber'];
        }

        $data = [
            $this->formatService->datetime($movement['datetime']),
            $movement['code'],
            $movement['locationLabel'],
            $movement['quantity'],
            $this->translation->translate("Traçabilité", "Mouvements", $movement['typeName'], false),
            $movement['operatorUsername'],
            strip_tags($movement['commentaire']),
            $attachementName,
            $origine ?? ' ',
            $movement['numeroCommandeListArrivage'] && !empty($movement['numeroCommandeListArrivage'])
                        ? implode(', ', $movement['numeroCommandeListArrivage'])
                        : ($movement['orderNumber'] ?: ''),
            $this->formatService->bool($movement['isUrgent']),
            $movement['packParent'],
        ];

        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $data[] = $this->formatService->freeField($movement['freeFields'][$freeFieldId] ?? '', $freeField);
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
            if ($type?->getCode() === TrackingMovement::TYPE_PRISE) {
                $trackingMovement->setFinished(true);
                return $trackingMovement->getPack()->getCode();
            }
        }
        return null;
    }

    public function treatStockMovement(EntityManagerInterface $entityManager,
                                       string                 $trackingType,
                                       array                  $movement,
                                       Utilisateur            $nomadUser,
                                       Emplacement            $location,
                                       $date): array {
        $articleRepository = $entityManager->getRepository(Article::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $options = [];

        if ($trackingType === TrackingMovement::TYPE_PRISE) {
            $article = $articleRepository->findOneByBarCodeAndLocation($movement['ref_article'], $movement['ref_emplacement']);
            if (!isset($article)) {
                $article = $referenceArticleRepository->findOneByBarCodeAndLocation($movement['ref_article'], $movement['ref_emplacement']);
            }

            if (isset($article)) {
                $quantiteMouvement = ($article instanceof Article)
                    ? $article->getQuantite()
                    : $article->getQuantiteStock(); // ($article instanceof ReferenceArticle)

                $newMouvement = $this->stockMovementService->createMouvementStock($nomadUser, $location, $quantiteMouvement, $article, MouvementStock::TYPE_TRANSFER);
                $options['mouvementStock'] = $newMouvement;
                $options['quantity'] = $newMouvement->getQuantity();
                $entityManager->persist($newMouvement);

                if ($article instanceof Article) {
                    $status = $this->stockStatuses['transitArticleStatus']
                        ?? $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
                    $this->stockStatuses['transitArticleStatus'] = $status;
                }
                else {
                    $status = $this->stockStatuses['inactiveReferenceStatus']
                        ?? $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF);
                    $this->stockStatuses['inactiveReferenceStatus'] = $status;
                }

                $article->setStatut($status);
            }
        }
        else { // MouvementTraca::TYPE_DEPOSE
            $mouvementTracaPrises = $trackingMovementRepository->findLastTakingNotFinished($movement['ref_article']);
            /** @var TrackingMovement|null $mouvementTracaPrise */
            $mouvementTracaPrise = count($mouvementTracaPrises) > 0 ? $mouvementTracaPrises[0] : null;
            if (isset($mouvementTracaPrise)) {
                $mouvementStockPrise = $mouvementTracaPrise->getMouvementStock();
                $article = $mouvementStockPrise->getArticle()
                    ?: $mouvementStockPrise->getRefArticle();

                $collecteOrder = $mouvementStockPrise->getCollecteOrder();
                if (isset($collecteOrder)
                    && ($article instanceof ReferenceArticle)
                    && $article->getEmplacement()
                    && ($article->getEmplacement()->getId() !== $location->getId())) {
                    $options['invalidLocationTo'] = $article->getEmplacement()->getLabel();
                    return $options;
                } else {
                    $options['mouvementStock'] = $mouvementStockPrise;
                    $options['quantity'] = $mouvementStockPrise->getQuantity();
                    $this->stockMovementService->finishMouvementStock($mouvementStockPrise, $date, $location);

                    if ($article instanceof Article) {
                        $status = $this->stockStatuses['activeArticleStatus']
                            ?? $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
                        $this->stockStatuses['activeArticleStatus'] = $status;
                    }
                    else {
                        $status = $this->stockStatuses['activeReferenceStatus']
                            ?? $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF);
                        $this->stockStatuses['activeReferenceStatus'] = $status;
                    }

                    $article
                        ->setStatut($status)
                        ->setEmplacement($location);

                    // we update quantity if it's reference article from collecte
                    if (isset($collecteOrder) && ($article instanceof ReferenceArticle)) {
                        $stockQuantity = ($article->getQuantiteStock() ?? 0) + $mouvementStockPrise->getQuantity();
                        $referenceArticleRepository->updateFields($article, [
                            'quantiteStock' => $stockQuantity
                        ]);
                        $article->setQuantiteStock($stockQuantity);
                    }
                }
            }
        }

        return $options;
    }

    public function treatTrackingData(array $movement, FileBag $files, int $index): array {
        $options = [];
        if (!empty($movement['comment'])) {
            $options['commentaire'] = $movement['comment'];
        }

        $signatureFile = $files->get("signature_$index");
        $photoFile = $files->get("photo_$index");
        if (!empty($signatureFile) || !empty($photoFile)) {
            $options['fileBag'] = [];
            if (!empty($signatureFile)) {
                $options['fileBag'][] = $signatureFile;
            }

            if (!empty($photoFile)) {
                $options['fileBag'][] = $photoFile;
            }
        }
        return $options;
    }

    public function clearTrackingMovement(array $trackingMovements,
                                          array $finishMouvementTraca,
                                          array $alreadySavedMovements): void {
        // Pour tous les mouvement de prise envoyés, on les marques en fini si un mouvement de dépose a été donné
        foreach ($trackingMovements as $mvt) {
            /** @var TrackingMovement $mouvementTracaPriseToFinish */
            $mouvementTracaPriseToFinish = $alreadySavedMovements[$mvt['date']] ?? null;

            if (isset($mouvementTracaPriseToFinish)) {
                $trackingPack = $mouvementTracaPriseToFinish->getPack();
                if ($trackingPack) {
                    $packCode = $trackingPack->getCode();
                    if (($mouvementTracaPriseToFinish->getType()?->getCode() === TrackingMovement::TYPE_PRISE) &&
                        in_array($packCode, $finishMouvementTraca) &&
                        !$mouvementTracaPriseToFinish->isFinished()) {
                        $mouvementTracaPriseToFinish->setFinished((bool)$mvt['finished']);
                    }
                }
            }
        }
    }

    public function manageLinksForClonedMovement(TrackingMovement $from, TrackingMovement $to) {
        $to
            ->setArrivage($from->getArrivage())
            ->setDispatch($from->getDispatch())
            ->setReception($from->getReception())
            ->setReceptionReferenceArticle($from->getReceptionReferenceArticle())
            ->setMouvementStock($from->getMouvementStock());

        foreach ($from->getAttachments() as $attachment) {
            $to->addAttachment($attachment);
        }
        return $to;
    }

    public function persistTrackingMovement(EntityManagerInterface $entityManager,
                                                                   $packOrCode,
                                            ?Emplacement           $location,
                                            Utilisateur            $operator,
                                            DateTime               $date,
                                            ?bool                  $finished,
                                                                   $trackingType,
                                            bool                   $forced,
                                            array                  $options = [],
                                            bool                   $keepGroup = false): array {

        $movement = $this->createTrackingMovement(
            $packOrCode,
            $location,
            $operator,
            $date,
            false,
            $finished,
            $trackingType,
            $options
        );

        $associatedPack = $movement->getPack();
        if (!$keepGroup && $associatedPack) {
            $associatedGroup = $associatedPack->getParent();

            if (!$forced && $associatedGroup) {
                return [
                    'success' => false,
                    'error' => Pack::CONFIRM_CREATE_GROUP,
                    'group' => $associatedGroup->getCode()
                ];
            } else if ($forced) {
                $associatedPack->setParent(null);
            }
        }

        $movementType = $movement->getType();

        // Dans le cas d'une dépose, on vérifie si l'emplacement peut accueillir le colis
        if ($movementType?->getCode() === TrackingMovement::TYPE_DEPOSE && !$location->ableToBeDropOff($movement->getPack())) {
            $packTranslation = $this->translation->translate('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', 'Unité logistique', false);
            $natureTranslation = $this->translation->translate('Traçabilité', 'Mouvements', 'natures requises', false);
            $packCode = $movement->getPack()->getCode();
            $bold = '<span class="font-weight-bold"> ';
            return [
                'success' => false,
                'msg' => 'Le ' . $packTranslation . $bold . $packCode . '</span> ne dispose pas des ' . $natureTranslation . ' pour être déposé sur l\'emplacement' . $bold . $location . '</span>.'
            ];
        }

        $this->persistSubEntities($entityManager, $movement);
        $entityManager->persist($movement);

        return [
            'success' => true,
            'movement' => $movement
        ];
    }

    public function persistTrackingMovementForPackOrGroup(EntityManagerInterface $entityManager,
                                                                                 $packOrCode,
                                                          ?Emplacement           $location,
                                                          Utilisateur            $operator,
                                                          DateTime               $date,
                                                          ?bool                  $finished,
                                                                                 $trackingType,
                                                          bool                   $forced,
                                                          array                  $options = []): array {
        $packRepository = $entityManager->getRepository(Pack::class);
        $pack = $packOrCode instanceof Pack
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);
        if (!isset($pack) || $pack->getGroupIteration() === null) { // it's a simple pack
            return $this->persistTrackingMovement(
                $entityManager,
                $pack ?? $packOrCode,
                $location,
                $operator,
                $date,
                $finished,
                $trackingType,
                $forced,
                $options
            );
        }
        else { // it's a group
            $parent = $pack;
            $newMovements = [];
            /** @var Pack $child */
            foreach ($parent->getChildren() as $child) {
                $childOptions = [
                    $options,
                    'parent' => $parent,
                    'disableUngrouping' => true
                ];

                $childTrackingRes = $this->persistTrackingMovement(
                    $entityManager,
                    $child,
                    $location,
                    $operator,
                    $date,
                    $finished,
                    $trackingType,
                    $forced,
                    $childOptions,
                    true
                );

                if (!$childTrackingRes['success']) {
                    return $childTrackingRes;
                }
                else {
                    $newMovements[] = $childTrackingRes['movement'];
                }
            }

            $childTrackingRes = $this->persistTrackingMovement(
                $entityManager,
                $parent,
                $location,
                $operator,
                $date,
                $finished,
                $trackingType,
                $forced,
                $options
            );

            if (!$childTrackingRes['success']) {
                return $childTrackingRes;
            }
            else {
                $newMovements[] = $childTrackingRes['movement'];
            }

            return [
                'success' => true,
                'multiple' => true,
                'movements' => $newMovements,
                'parent' => $parent
            ];
        }
    }
}
