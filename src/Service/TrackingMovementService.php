<?php

namespace App\Service;

use App\Controller\AbstractController;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\Cart;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Livraison;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterRecord;
use App\Entity\MouvementStock;
use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Repository\TrackingMovementRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class TrackingMovementService extends AbstractController
{
    public const INVALID_LOCATION_TO = 'invalid-location-to';

    private $templating;
    private $security;
    private $entityManager;
    private $attachmentService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public PackService $packService;

    #[Required]
    public ProjectHistoryRecordService $projectHistoryRecordService;

    #[Required]
    public LoggerInterface $logger;

    private $locationClusterService;
    private $fieldModesService;
    private $groupService;

    #[Required]
    public MouvementStockService $stockMovementService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public CSVExportService $CSVExportService;

    public array $stockStatuses = [];

    private ?array $freeFieldsConfig = null;


    public function __construct(EntityManagerInterface $entityManager,
                                LocationClusterService $locationClusterService,
                                Twig_Environment       $templating,
                                Security               $security,
                                GroupService           $groupService,
                                FieldModesService      $fieldModesService,
                                AttachmentService      $attachmentService)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->attachmentService = $attachmentService;
        $this->locationClusterService = $locationClusterService;
        $this->fieldModesService = $fieldModesService;
        $this->groupService = $groupService;
    }

    public function getDataForDatatable($params = null): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        /** @var Utilisateur $user */
        $user = $this->security->getUser();
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_TRACA, $user);

        $queryResult = $trackingMovementRepository->findByParamsAndFilters($params, $filters, $user, $this->fieldModesService);

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


    public function getFromColumnData(TrackingMovement|array|null $movement): array
    {
        $data = [
            'entityPath' => null,
            'entityId' => null,
            'fromLabel' => null,
            'from' => '-',
        ];

        if (isset($movement)) {
            if (($movement instanceof TrackingMovement && $movement->getDispatch())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::DISPATCH_ENTITY)) {
                $data['entityPath'] = 'dispatch_show';
                $data['fromLabel'] = $this->translation->translate('Demande', 'Acheminements', 'Général', 'Acheminement', false);
                $data['entityId'] =  is_array($movement)
                    ? $movement['entityId']
                    : $movement->getDispatch()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getDispatch()->getNumber();
            } else if (($movement instanceof TrackingMovement && $movement->getArrivage())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::ARRIVAL_ENTITY)) {
                $data['entityPath'] = 'arrivage_show';
                $data['fromLabel'] = $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'Arrivage', false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getArrivage()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getArrivage()->getNumeroArrivage();
            } else if (($movement instanceof TrackingMovement && $movement->getReception())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::RECEPTION_ENTITY)) {
                $data['entityPath'] = 'reception_show';
                $data['fromLabel'] = $this->translation->translate('Ordre', 'Réceptions', 'Réception', false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getReception()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getReception()->getNumber();
            } else if (($movement instanceof TrackingMovement && $movement->getMouvementStock()?->getTransferOrder())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::TRANSFER_ORDER_ENTITY)) {
                $data['entityPath'] = 'transfer_order_show';
                $data['fromLabel'] = 'Transfert de stock';
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getMouvementStock()->getTransferOrder()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getMouvementStock()->getTransferOrder()->getNumber();
            } else if (($movement instanceof TrackingMovement && $movement->getPreparation())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::PREPARATION_ENTITY)) {
                $data['entityPath'] = 'preparation_show';
                $data['fromLabel'] = 'Preparation';
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getPreparation()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getPreparation()->getNumero();
            } else if (($movement instanceof TrackingMovement && $movement->getDelivery())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::DELIVERY_ORDER_ENTITY)) {
                $data['entityPath'] = 'livraison_show';
                $data['fromLabel'] = $this->translation->translate("Ordre", "Livraison", "Ordre de livraison", false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getDelivery()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getDelivery()->getNumero();
            } else if (($movement instanceof TrackingMovement && $movement->getDeliveryRequest())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::DELIVERY_REQUEST_ENTITY)) {
                $data['entityPath'] = 'demande_show';
                $data['fromLabel'] = $this->translation->translate("Demande", "Livraison", "Demande de livraison", false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getDeliveryRequest()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getDeliveryRequest()->getNumero();
            } else if (($movement instanceof TrackingMovement && $movement->getShippingRequest())
                || (is_array($movement) && $movement['entity'] === TrackingMovement::SHIPPING_REQUEST_ENTITY)) {
                $data['entityPath'] = 'shipping_request_show';
                $data['fromLabel'] = $this->translation->translate("Demande", "Expédition", "Demande d'expédition", false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getShippingRequest()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getShippingRequest()->getNumber();
            }
        }
        return $data;
    }

    public function dataRowMouvement(TrackingMovement $movement): array {
        $fromColumnData = $this->getFromColumnData($movement);
        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig(
                $this->entityManager,
                CategorieCL::MVT_TRACA,
                CategoryType::MOUVEMENT_TRACA
            );
        }

        if ($movement->getLogisticUnitParent()) {
            if (in_array($movement->getType()->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE])) {
                $pack = null;
            } else {
                $pack = $movement->getLogisticUnitParent();
            }
        } else {
            $pack = $movement->getPackArticle()
                ? null
                : $movement->getPack();
        }

        $row = [
            'id' => $movement->getId(),
            'date' => $this->formatService->datetime($movement->getDatetime()),
            'pack' => $pack
                ? $this->templating->render('tracking_movement/datatableMvtTracaRowFrom.html.twig', [
                    "entityPath" => "pack_show",
                    "entityId" => $pack?->getId(),
                    "from" => $pack?->getCode(),
                ])
                : '',
            'origin' => $this->templating->render('tracking_movement/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'group' => $movement->getPackParent()
                ? ($movement->getPackParent()->getCode() . '-' . ($movement->getGroupIteration() ?: '?'))
                : '',
            'location' => $this->formatService->location($movement->getEmplacement()),
            'reference' => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getReference()
                : ($movement->getPackArticle()
                    ? $movement->getPackArticle()->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : null),
            "label" => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getLibelle()
                : ($movement->getPackArticle()
                    ? $movement->getPackArticle()->getLabel()
                    : null),
            "quantity" => $movement->getQuantity(),
            "article" => $movement->getPackArticle()?->getBarCode(),
            "type" => $this->translation->translate('Traçabilité', 'Mouvements', $movement->getType()->getNom()) ,
            "operator" => $this->formatService->user($movement->getOperateur()),
            "actions" => $this->templating->render('tracking_movement/datatableMvtTracaRow.html.twig', [
                'mvt' => $movement,
                'attachmentsLength' => $movement->getAttachments()->count(),
            ]),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->fieldModesService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $movement->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function handleGroups(array $data,
                                 EntityManagerInterface $entityManager,
                                 Utilisateur $operator,
                                 DateTime $date): array {
        $packRepository = $entityManager->getRepository(Pack::class);
        $parentCode = $data['parent'];

        /** @var Pack $parentPack */
        $parentPack = $packRepository->findOneBy(['code' => $parentCode]);

        if ($parentPack && !$parentPack->isGroup()) {
            return [
                'success' => false,
                'msg' => 'Le contenant choisie est une unité logistique, veuillez choisir un groupage valide.',
            ];
        } else {
            $errors = [];
            $packCodes = explode(',', $data['pack']);
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
                    'msg' => 'Les UL '
                        . implode(', ', $errors)
                        . ' sont des groupages ou sont déjà présents dans un groupe, veuillez choisir des UL valides.',
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
                    $pack = $this->packService->persistPack($entityManager, $packCode, 1, null);
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
                            'onlyPack' => true,
                            'commentaire' => $data['commentaire'] ?? null,
                        ]
                    );

                    $pack->setParent($parentPack);

                    $entityManager->persist($groupingTrackingMovement);
                    $createdMovements[] = $groupingTrackingMovement;
                }
                return [
                    'success' => true,
                    'msg' => 'OK',
                    'createdMovements' => $createdMovements,
                ];
            }
        }
    }

    public function createTrackingMovement(Pack|string $packOrCode,
                                           ?Emplacement $location,
                                           Utilisateur $user,
                                           DateTime $date,
                                           bool $fromNomade,
                                           ?bool $finished,
                                           Statut|string|int $trackingType,
                                           array $options = []): TrackingMovement
    {
        $entityManager = $options['entityManager'] ?? $this->entityManager;
        $statutRepository = $entityManager->getRepository(Statut::class);

        $type = $this->getTrackingType($entityManager, $trackingType);

        if (!isset($type)) {
            throw new Exception('Le type de mouvement traca donné est invalide');
        }

        $commentaire = $options['commentaire'] ?? null;
        $mouvementStock = $options['mouvementStock'] ?? null;
        $fileBag = $options['fileBag'] ?? null;
        $quantity = $options['quantity'] ?? 1;
        $from = $options['from'] ?? null;
        $refOrArticle = $options['refOrArticle'] ?? null;
        $receptionReferenceArticle = $options['receptionReferenceArticle'] ?? null;
        $uniqueIdForMobile = $options['uniqueIdForMobile'] ?? null;
        $natureId = $options['natureId'] ?? null;
        $disableUngrouping = $options['disableUngrouping'] ?? false;
        $attachments = $options['attachments'] ?? null;
        $mainMovement = $options['mainMovement'] ?? null;
        $preparation = $options['preparation'] ?? null;
        $delivery = $options['delivery'] ?? null;
        $logisticUnitParent = $options['logisticUnitParent'] ?? null;

        /** @var Pack|null $parent */
        $parent = $options['parent'] ?? null;
        $removeFromGroup = $options['removeFromGroup'] ?? false;

        $pack = $this->packService->persistPack($entityManager, $packOrCode, $quantity, $natureId, $options['onlyPack'] ?? false);
        $ungroup = !$disableUngrouping
            && $pack->getParent()
            && in_array($type->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE]);

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
            ->setCommentaire(!empty($commentaire) ? $commentaire : null)
            ->setMainMovement($mainMovement)
            ->setPreparation($preparation)
            ->setDelivery($delivery)
            ->setLogisticUnitParent($logisticUnitParent);

        if ($attachments) {
            foreach($attachments as $attachment) {
                $tracking->addAttachment($attachment);
            }
        }

        if(!$ungroup && $parent) {
            // Si pas de mouvement de dégroupage, on set le parent
            $tracking->setPackParent($parent);
            $tracking->setGroupIteration($parent->getGroupIteration());
        }

        $pack->addTrackingMovement($tracking);

        $message = $this->buildCustomLogisticUnitHistoryRecord($tracking);
        $this->packService->persistLogisticUnitHistoryRecord($entityManager, $pack, $message, $tracking->getDatetime(), $tracking->getOperateur(), ucfirst($tracking->getType()->getCode()), $tracking->getEmplacement());

        if (!$pack->getLastTracking()
            || $pack->getLastTracking()->getDatetime() <= $tracking->getDatetime()) {
            $pack->setLastTracking($tracking);
        }

        $this->treatGroupDrop($entityManager, $pack, $tracking);

        $this->managePackLinksWithTracking($entityManager, $tracking);
        $this->manageTrackingLinks($entityManager, $tracking, [
            "from" => $from,
            "receptionReferenceArticle" => $receptionReferenceArticle,
            "refOrArticle" => $refOrArticle
        ]);
        $this->manageTrackingFiles($tracking, $fileBag);

        if ($ungroup) {
            $type = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, TrackingMovement::TYPE_UNGROUP);

            [$_, $orderIndex] = hrtime();

            $trackingUngroup = new TrackingMovement();
            $trackingUngroup
                ->setOrderIndex($orderIndex)
                ->setQuantity($pack->getQuantity())
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
            $message = $this->buildCustomLogisticUnitHistoryRecord($trackingUngroup);
            $this->packService->persistLogisticUnitHistoryRecord($entityManager, $pack, $message, $trackingUngroup->getDatetime(), $trackingUngroup->getOperateur(), ucfirst($trackingUngroup->getType()->getCode()), $trackingUngroup->getEmplacement());
        }
        [$_, $orderIndex] = hrtime();
        $tracking->setOrderIndex($orderIndex);

        return $tracking;
    }

    private function manageTrackingLinks(EntityManagerInterface $entityManager,
                                         TrackingMovement $tracking,
                                         array $options): void {
        $from = $options['from'] ?? null;
        $receptionReferenceArticle = $options['receptionReferenceArticle'] ?? null;
        $refOrArticle = $options['refOrArticle'] ?? null;

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $pack = $tracking->getPack();
        $packCode = $pack ? $pack->getCode() : null;
        if ($pack) {
            $alreadyLinkedArticle = $pack->getArticle();
            $alreadyLinkedReferenceArticle = $pack->getReferenceArticle();
            if (!isset($alreadyLinkedArticle) && !isset($alreadyLinkedReferenceArticle)) {
                $refOrArticle = (
                    $refOrArticle
                    ?: $referenceArticleRepository->findOneBy(['barCode' => $packCode])
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
            } else if ($from instanceof Arrivage) {
                $tracking->setArrivage($from);
            } else if ($from instanceof Dispatch) {
                $tracking->setDispatch($from);
            } else if ($from instanceof Demande) {
                $tracking->setDeliveryRequest($from);
            } else if ($from instanceof Preparation) {
                $tracking->setPreparation($from);
            } else if ($from instanceof Livraison) {
                $tracking->setDelivery($from);
            } else if ($from instanceof ShippingRequest) {
                $tracking->setShippingRequest($from);
            }
        }

        if (isset($receptionReferenceArticle)) {
            $tracking->setReceptionReferenceArticle($receptionReferenceArticle);
        }
    }

    private function manageTrackingFiles(TrackingMovement $tracking, $fileBag): void {
        if (isset($fileBag)) {
            $attachments = $this->attachmentService->createAttachmentsDeprecated($fileBag);
            foreach ($attachments as $attachment) {
                $tracking->addAttachment($attachment);
            }
        }
    }

    private function generateUniqueIdForMobile(EntityManagerInterface $entityManager,
                                               DateTime $date): string {
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
                                       TrackingMovement $trackingMovement): void {
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

        // si c'est une prise ou une dépose on vide ses UL liés
        $packsAlreadyExisting = $tracking->getLinkedPackLastDrop();
        if ($packsAlreadyExisting) {
            $packsAlreadyExisting->setLastDrop(null);
        }

        if ($pack
            && $tracking->isDrop()
            && (!$pack->getLastDrop() || $tracking->getDatetime() >= $pack->getLastDrop()->getDatetime())) {
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

        $columnsVisible = $currentUser->getFieldModes('trackingMovement');
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::MOUVEMENT_TRACA, CategorieCL::MVT_TRACA);

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Issu de', false), 'name' => 'origin', 'orderable' => false],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Date', false), 'name' => 'date'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Unité logistique', false), 'name' => 'pack'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Article', false), 'name' => 'article'],
            ['title' => $this->translation->translate('Traçabilité', 'Mouvements', 'Référence', false), 'name' => 'reference'],
            ['title' => $this->translation->translate('Traçabilité', 'Mouvements', 'Libellé', false),  'name' => 'label'],
            ['title' => $this->translation->translate('Traçabilité', 'Mouvements', 'Groupe', false),  'name' => 'group'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Quantité', false), 'name' => 'quantity'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Emplacement', false), 'name' => 'location'],
            ['title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type', false), 'name' => 'type'],
            ['title' => $this->translation->translate('Traçabilité', 'Général', 'Opérateur', false), 'name' => 'operator'],
        ];

        return $this->fieldModesService->getArrayConfig($columns, $freeFields, $columnsVisible);
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
            [
                'from' => $arrivage,
            ]
        );
        $this->persistSubEntities($entityManager, $mouvementDepose);
        $entityManager->persist($mouvementDepose);
    }

    public function putMovementLine($handle,
                                    array $movement,
                                    array $columnToExport,
                                    array $freeFieldsConfig,
                                    Utilisateur $user = null): void {

        $freeFieldValues = $movement["freeFields"];

        $fromData = $this->getFromColumnData($movement);
        $fromLabel = $fromData["fromLabel"] ?? "";
        $fromNumber = $fromData["from"] ?? "";
        $from = trim("$fromLabel $fromNumber") ?: null;

        $line = [];
        foreach ($columnToExport as $column) {
            if (preg_match('/free_field_(\d+)/', $column, $matches)) {
                $freeFieldId = $matches[1];
                $freeField = $freeFieldsConfig['freeFields'][$freeFieldId] ?? null;
                $value = $freeFieldValues[$freeFieldId] ?? null;
                $line[] = $freeField
                    ? $this->formatService->freeField($value, $freeField, $user)
                    : $value;
            }
            else {
                $line[] = match ($column) {
                    "date" => $movement["date"],
                    "logisticUnit" => $movement["logisticUnit"],
                    "location" => $movement["location"],
                    "quantity" => $movement["quantity"],
                    "type" => $movement["type"],
                    "operator" => $movement["operator"],
                    "comment" => $movement["comment"]
                        ? $this->formatService->html($movement["comment"])
                        : null,
                    "hasAttachments" => $movement["hasAttachments"],
                    "from" => $from,
                    "arrivalOrderNumber" => $movement["arrivalOrderNumber"]
                        ? implode(", ", $movement["arrivalOrderNumber"])
                        : null,
                    "isUrgent" => $this->formatService->bool($movement["isUrgent"]),
                    "packParent" => $movement["packParent"],
                };
            }
        }

        $this->CSVExportService->putLine($handle, $line);
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

    public function treatLUPicking(Pack $pack,
                                   Emplacement $location,
                                   Utilisateur $nomadUser,
                                   DateTime $date,
                                   array $mvt,
                                   Statut $type,
                                   array $options,
                                   EntityManagerInterface $entityManager,
                                   array &$emptyGroups,
                                   int &$numberOfRowsInserted) {
        $createdMvt = $this->createTrackingMovement(
            $pack->getCode(),
            $location,
            $nomadUser,
            $date,
            true,
            $mvt['finished'],
            $type,
            $options,
        );

        $associatedGroup = $pack->getParent();
        if ($associatedGroup) {
            $associatedGroup->removeChild($pack);
            if ($associatedGroup->getChildren()->isEmpty()) {
                $emptyGroups[] = $associatedGroup->getCode();
            }
        }

        $this->persistSubEntities($entityManager, $createdMvt);
        $entityManager->persist($createdMvt);
        $numberOfRowsInserted++;

        return $createdMvt;
    }

    public function manageTrackingMovementsForLU(Pack $pack,
                                                 EntityManagerInterface $entityManager,
                                                 MouvementStockService $mouvementStockService,
                                                 array $mvt,
                                                 Statut $type,
                                                 Utilisateur $nomadUser,
                                                 Emplacement $location,
                                                 DateTime $date,
                                                 array &$emptyGroups,
                                                 int &$numberOfRowsInserted) {
        //créé les mouvements traça pour les articles contenus
        //dans l'unité logistique
        /** @var Article $article */
        foreach($pack->getChildArticles() as $article) {
            $this->packService->updateArticlePack($entityManager, $article);

            $currentArticleOptions = [];
            $currentArticleOptions["entityManager"] = $entityManager;
            if($mvt['type'] === TrackingMovement::TYPE_DEPOSE) {
                $stockMovement = $mouvementStockService->createMouvementStock(
                    $nomadUser,
                    $article->getEmplacement(),
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_TRANSFER
                );

                $mouvementStockService->finishStockMovement($stockMovement, new DateTime(), $location);
                $article->setEmplacement($location);

                $entityManager->persist($stockMovement);

                $currentArticleOptions = [
                    "mouvementStock" => $stockMovement,
                ];
            }

            $createdMvt = $this->createTrackingMovement(
                $article->getBarCode(),
                $location,
                $nomadUser,
                $date,
                true,
                $mvt['finished'],
                $type,
                $currentArticleOptions,
            );

            if($article->getCurrentLogisticUnit()) {
                $createdMvt->setLogisticUnitParent($article->getCurrentLogisticUnit());
            }

            $associatedPack = $createdMvt->getPack();
            if ($associatedPack) {
                $associatedGroup = $associatedPack->getParent();

                if ($associatedGroup) {
                    $associatedGroup->removeChild($associatedPack);
                    if ($associatedGroup->getChildren()->isEmpty()) {
                        $emptyGroups[] = $associatedGroup->getCode();
                    }
                }
            }

            $this->persistSubEntities($entityManager, $createdMvt);
            $entityManager->persist($createdMvt);
            $numberOfRowsInserted++;
        }
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
        $options = [
            "stockAction" => true,
        ];

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
                    $this->stockMovementService->finishStockMovement($mouvementStockPrise, $date, $location);

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
                            'quantiteStock' => $stockQuantity,
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

        $ignoreProjectChange = $options['ignoreProjectChange'] ?? false;
        $stockAction = $options['stockAction'] ?? false;

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
                    'msg' => Pack::CONFIRM_CREATE_GROUP,
                    'group' => $associatedGroup->getCode(),
                ];
            } else if ($forced) {
                $associatedPack->setParent(null);
            }
        }

        $movementType = $movement->getType();
        if (!$ignoreProjectChange
            && $movementType?->getCode() === TrackingMovement::TYPE_DEPOSE
            && $movement->getPack()->getArticle()) {
            $this->projectHistoryRecordService->changeProject($entityManager, $movement->getPack(), null, $date);
        }

        // Dans le cas d'une dépose, on vérifie si l'emplacement peut accueillir l'UL
        if ($movementType?->getCode() === TrackingMovement::TYPE_DEPOSE
            && $stockAction === false
            && ($location && !$location->ableToBeDropOff($movement->getPack()))) {
            $natureTranslation = $this->translation->translate('Traçabilité', 'Mouvements', 'natures requises', false);
            $packCode = $movement->getPack()->getCode();
            return [
                'success' => false,
                'msg' => "L'unité logistique <strong>$packCode</strong> ne dispose pas des $natureTranslation pour être déposée sur l'emplacement <strong>$location</strong>.",
            ];
        }

        $this->persistSubEntities($entityManager, $movement);
        $entityManager->persist($movement);

        return [
            'success' => true,
            'movement' => $movement,
        ];
    }

    public function persistTrackingMovementForPackOrGroup(EntityManagerInterface $entityManager,
                                                          Pack|string            $packOrCode,
                                                          ?Emplacement           $location,
                                                          Utilisateur            $operator,
                                                          DateTime               $date,
                                                          ?bool                  $finished,
                                                          Statut|string|int      $trackingType,
                                                          bool                   $forced,
                                                          array                  $options = []): array {
        $packRepository = $entityManager->getRepository(Pack::class);

        $pack = $packOrCode instanceof Pack
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);

        if (!isset($pack) || $pack->getGroupIteration() === null) { // it's a simple pack
            if(isset($options["articles"]) && $options["articles"]) {
                return $this->persistLogisticUnitMovements($entityManager, $packOrCode, $location, $options["articles"], $operator, $options);
            } else {
                $newMovements = [];
                $movement = null;
                $trackingType = $this->getTrackingType($entityManager, $trackingType);
                $packArticle = $pack?->getArticle();
                $pickMvtOnArticleWithLU = $trackingType->getCode() === TrackingMovement::TYPE_PRISE && $packArticle?->getCurrentLogisticUnit();

                if($pickMvtOnArticleWithLU) {
                    $movement = $this->persistTrackingMovement(
                        $entityManager,
                        $pack ?? $packOrCode,
                        $location,
                        $operator,
                        $date,
                        $finished,
                        TrackingMovement::TYPE_PICK_LU,
                        $forced,
                        $options
                    );

                    if($movement["movement"] ?? null) {
                        $movement["movement"]->setLogisticUnitParent($packArticle?->getCurrentLogisticUnit());
                    }

                    $packArticle->setCurrentLogisticUnit(null);

                    if($movement["success"]) {
                        $newMovements[] = $movement["movement"];
                    } else {
                        return $movement;
                    }
                }

                $pickLU = $movement["movement"] ?? null;

                $movement = $this->persistTrackingMovement(
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

                if($movement["movement"] ?? null) {
                    $movement["movement"]->setLogisticUnitParent($packArticle?->getCurrentLogisticUnit());
                    if(isset($pickLU)) {
                        $movement["movement"]->setMainMovement($pickLU);
                    }
                }

                if($movement["success"]) {
                    $newMovements[] = $movement["movement"];
                } else {
                    return $movement;
                }

                if(!$pickMvtOnArticleWithLU
                    && in_array($trackingType->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE])
                    && $pack?->getChildArticles()?->count()) {
                    foreach($pack->getChildArticles() as $childArticle) {
                        $movement = $this->persistTrackingMovement(
                            $entityManager,
                            $childArticle->getTrackingPack() ?? $childArticle->getBarCode(),
                            $location,
                            $operator,
                            $date,
                            $finished,
                            $trackingType,
                            $forced,
                            []
                        );

                        if($movement["success"]) {
                            $movement["movement"]->setLogisticUnitParent($pack);
                            $newMovements[] = $movement["movement"];
                        } else {
                            return $movement;
                        }
                    }
                }

                return [
                    "success" => true,
                    "movements" => $newMovements,
                ];
            }
        }
        else { // it's a group
            $parent = $pack;
            $newMovements = [];
            /** @var Pack $child */
            foreach ($parent->getChildren() as $child) {
                $childOptions = [
                    $options,
                    'parent' => $parent,
                    'disableUngrouping' => true,
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
                'parent' => $parent,
            ];
        }
    }

    public function persistLogisticUnitMovements(EntityManagerInterface $manager,
                                                 Pack|string|null       $pack,
                                                 Emplacement            $dropLocation,
                                                 ?array                 $articles,
                                                 ?Utilisateur           $user,
                                                 array                  $options) {
        $packRepository = $manager->getRepository(Pack::class);

        $movements = [];
        $inCarts = [];

        $trackingDate = $options['trackingDate'] ?? new DateTime();
        $reception = $options['reception'] ?? false;

        // clear given options articles
        unset($options['articles']);
        unset($options['trackingDate']);

        if(is_string($pack)) {
            $pack = $this->packService->persistPack($manager, $pack, 1);
            $lastTracking = $this->persistTrackingMovement(
                $manager,
                $pack,
                $dropLocation,
                $user,
                $trackingDate,
                true,
                TrackingMovement::TYPE_DEPOSE,
                false,
                $options,
            );
            if (!$lastTracking['success']) {
                return $lastTracking;
            }
            $lastTracking = $lastTracking['movement'];
            $movements[] = $lastTracking;
        }

        /** @var Article $article */
        foreach($articles as $article) {
            $pickLocation = $article->getEmplacement();

            $isUnitChanges = ($article->getCurrentLogisticUnit()?->getId() !== $pack?->getId());
            $isLocationChanges = $pickLocation?->getId() !== $dropLocation->getId();

            $options['quantity'] = $article->getQuantite();

            $trackingPack = $this->packService->updateArticlePack($manager, $article);

            if($packRepository->isInOngoingReception($trackingPack)) {
                return [
                    "success" => false,
                    "error" => Pack::IN_ONGOING_RECEPTION,
                ];
            }
            if(!$article->getCarts()->isEmpty()) {
                $inCarts = array_merge($inCarts, $article->getCarts()->toArray());
            }

            $pack?->setArticleContainer(true);

            $newMovements = [];
            if (!$reception
                && ($isUnitChanges || $isLocationChanges)) {
                //generate pick movements
                $pick = $this->persistTrackingMovement(
                    $manager,
                    $trackingPack,
                    $pickLocation,
                    $user,
                    $trackingDate,
                    true,
                    TrackingMovement::TYPE_PRISE,
                    false,
                    $options + ["stockAction" => true],
                )["movement"];
                $movements[] = $pick;
                $newMovements[] = $pick;
            }

            if (!$reception && $isUnitChanges) {
                //generate pick in LU movements
                /** @var TrackingMovement $luPick */
                $luPick = $this->persistTrackingMovement(
                    $manager,
                    $trackingPack,
                    $pickLocation,
                    $user,
                    $trackingDate,
                    true,
                    TrackingMovement::TYPE_PICK_LU,
                    false,
                    $options + ["stockAction" => true],
                )["movement"];

                $oldCurrentLogisticUnit = $article->getCurrentLogisticUnit();
                $luPick->setLogisticUnitParent($oldCurrentLogisticUnit);
                $movements[] = $luPick;
                $newMovements[] = $luPick;
            }

            // now the pick LU movement is done, set the logistic unit
            $article->setCurrentLogisticUnit($pack);
            // then change the project of the article according to the pack project
            $this->projectHistoryRecordService->changeProject($manager, $article, $pack?->getProject(), $trackingDate);

            if ($reception || $isUnitChanges || $isLocationChanges) {
                //generate drop movements
                /** @var TrackingMovement $drop */
                $drop = $this->persistTrackingMovement(
                    $manager,
                    $trackingPack,
                    $dropLocation,
                    $user,
                    $trackingDate,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    false,
                    $options + ["ignoreProjectChange" => true, "stockAction" => true],
                )["movement"];

                $movements[] = $drop;
                $newMovements[] = $drop;
            }

            if($pack) {
                //generate drop in LU movements
                $luDrop = $this->persistTrackingMovement(
                    $manager,
                    $trackingPack,
                    $dropLocation,
                    $user,
                    $trackingDate,
                    true,
                    TrackingMovement::TYPE_DROP_LU,
                    false,
                    $options + ["stockAction" => true],
                )["movement"];

                $luDrop->setLogisticUnitParent($pack);
                $movements[] = $luDrop;
            }

            foreach ($newMovements as $movement) {
                $mainMovement = $luDrop ?? $drop ?? null;
                if ($mainMovement !== $movement) {
                    $movement->setMainMovement($mainMovement);
                }
                else {
                    $movement->setMainMovement(null);
                }
            }

            if ($isLocationChanges) {
                $stockMovement = $this->stockMovementService->createMouvementStock(
                    $user,
                    $pickLocation,
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_TRANSFER,
                    ['from' => $options['from'] ?? null]
                );

                $this->stockMovementService->finishStockMovement($stockMovement, $trackingDate, $dropLocation);
                $article->setEmplacement($dropLocation);

                $drop->setMouvementStock($stockMovement);
                $manager->persist($stockMovement);
            }
        }

        if ($oldCurrentLogisticUnit ?? null) {
            $this->updatePackQuantity($oldCurrentLogisticUnit ?? null);
        }

        if ($pack) {
            $this->updatePackQuantity($pack);
        }

        //add all new articles
        if($inCarts) {
            /** @var Cart $cart */
            foreach($inCarts as $cart) {
                foreach($articles as $article) {
                    $cart->addArticle($article);
                }
            }
        }

        return [
            "success" => true,
            "multiple" => true,
            "movements" => $movements,
        ];
    }

    public function getTrackingType(EntityManagerInterface $entityManager,
                                    Statut|int|string      $type): Statut|null {
        $statusRepository = $entityManager->getRepository(Statut::class);
        return ($type instanceof Statut)
            ? $type
            : (is_string($type)
                ? $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, $type)
                : $statusRepository->find($type));
    }

    private function updatePackQuantity(Pack $pack): void {
        $newQuantity = Stream::from($pack->getChildArticles())
            ->map(fn(Article $article) => $article->getQuantite())
            ->sum();
        $pack->setQuantity(max($newQuantity, 1));
    }

    public function treatGroupDrop(EntityManagerInterface $entityManager, Pack $pack, TrackingMovement $tracking): void {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $autoUngroup = (bool) $settingRepository->getOneParamByLabel(Setting::AUTO_UNGROUP);

        if(!$autoUngroup
            || (($tracking->getType()->getCode() !== TrackingMovement::TYPE_DEPOSE) || !$pack->isGroup())
            || $pack->getChildren()->isEmpty()
        ){
            return;
        }

        $autoUngroupTypes = Stream::explode(',', $settingRepository->getOneParamByLabel(Setting::AUTO_UNGROUP_TYPES) ?: '')
                ->filter(fn(string $type) => !empty($type))
                ->map(fn(string $type) => (int) $type)
                ->toArray();
        $linkedDispatches = $tracking->getEmplacement()
            ? $tracking->getEmplacement()->getDispatchesTo()
                ->map(fn (Dispatch $dispatch) => $dispatch->getId())
                ->toArray()
            : [];

        foreach ($pack->getChildren() as $children) {
            foreach($children->getDispatchPacks() as $dispatchPack) {
                $dispatch = $dispatchPack->getDispatch();

                if(in_array($dispatch->getId(), $linkedDispatches)
                    && in_array($dispatch->getType()->getId(), $autoUngroupTypes )){

                    $date = new DateTime('now');
                    $trackingMovement = $this->createTrackingMovement(
                        $children,
                        $tracking->getEmplacement(),
                        $tracking->getOperateur(),
                        $date,
                        false,
                        true,
                        TrackingMovement::TYPE_UNGROUP
                    );

                    $pack->removeChild($children);
                    $entityManager->persist($trackingMovement);
                    $this->persistSubEntities($entityManager, $trackingMovement);
                }
            }
        }
    }

    public function getTrackingMovementExportableColumns(EntityManagerInterface $entityManager): array {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);

        $freeFields = $freeFieldsRepository->findByFreeFieldCategoryLabels([CategorieCL::MVT_TRACA]);

        $userLanguage = $this->userService->getUser()?->getLanguage() ?: $this->languageService->getDefaultSlug();
        $defaultLanguage = $this->languageService->getDefaultSlug();

        return Stream::from(
            Stream::from([
                ["code" => "date", "label" => $this->translationService->translate('Traçabilité', 'Général', 'Date', false),],
                ["code" => "logisticUnit", "label" => $this->translationService->translate('Traçabilité', 'Général', 'Unité logistique', false),],
                ["code" => "location", "label" => $this->translationService->translate('Traçabilité', 'Général', 'Emplacement', false),],
                ["code" => "quantity", "label" => $this->translationService->translate('Traçabilité', 'Général', 'Quantité', false),],
                ["code" => "type", "label" => $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Type', false),],
                ["code" => "operator", "label" => $this->translationService->translate('Traçabilité', 'Général', 'Opérateur', false),],
                ["code" => "comment", "label" => $this->translationService->translate('Général', null, 'Modale', 'Commentaire', false),],
                ["code" => "hasAttachments", "label" => $this->translationService->translate('Général', null, 'Modale', 'Pièces jointes', false),],
                ["code" => "from", "label" => $this->translationService->translate('Traçabilité', 'Général', 'Issu de', false),],
                ["code" => "arrivalOrderNumber", "label" => $this->translationService->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° commande / BL', false),],
                ["code" => "packParent", "label" => $this->translationService->translate('Traçabilité', 'Unités logistiques', "Onglet \"Groupes\"", 'Groupe', false),],
            ]),
            Stream::from($freeFields)
                ->map(fn(FreeField $field) => [
                    "code" => "free_field_{$field->getId()}",
                    "label" => $field->getLabelIn($userLanguage, $defaultLanguage)
                        ?: $field->getLabel(),
                ])
        )
            ->toArray();
    }

    public function buildCustomLogisticUnitHistoryRecord(TrackingMovement $trackingMovement): string {
        $values = $trackingMovement->serialize($this->formatService);
        $message = "";

        Stream::from($values)
            ->filter(static fn(?string $value) => $value)
            ->each(static function (string $value, string $key) use (&$message) {
                $message .= "$key : $value\n";
                return $message;
            });

        return $message;
    }
}
