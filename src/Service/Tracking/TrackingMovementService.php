<?php

namespace App\Service\Tracking;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\Cart;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Livraison;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterRecord;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ProductionRequest;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\PackSplit;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Serializer\SerializerUsageEnum;
use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FieldModesService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\GroupService;
use App\Service\LanguageService;
use App\Service\LocationClusterService;
use App\Service\MouvementStockService;
use App\Service\ProjectHistoryRecordService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class TrackingMovementService {

    public const COMPARE_A_BEFORE_B = -1;
    public const COMPARE_A_AFTER_B = 1;
    public const COMPARE_A_EQUALS_B = 0;

    public const INVALID_LOCATION_TO = 'invalid-location-to';

    public array $stockStatuses = [];

    private ?array $freeFieldsConfig = null;

    public function __construct(
        private EntityManagerInterface      $entityManager,
        private LocationClusterService      $locationClusterService,
        private NormalizerInterface         $normalizer,
        private Twig_Environment            $templating,
        private Security                    $security,
        private GroupService                $groupService,
        private FieldModesService           $fieldModesService,
        private CSVExportService            $CSVExportService,
        private TranslationService          $translationService,
        private UserService                 $userService,
        private FormatService               $formatService,
        private AttachmentService           $attachmentService,
        private LanguageService             $languageService,
        private TranslationService          $translation,
        private MouvementStockService       $stockMovementService,
        private ProjectHistoryRecordService $projectHistoryRecordService,
        private FreeFieldService            $freeFieldService,
        private PackService                 $packService,
        private SettingsService             $settingsService,
    ) {
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
                || (is_array($movement) && ($movement['entity'] ?? null)  === TrackingMovement::DISPATCH_ENTITY)) {
                $data['entityPath'] = 'dispatch_show';
                $data['fromLabel'] = $this->translation->translate('Demande', 'Acheminements', 'Général', 'Acheminement', false);
                $data['entityId'] =  is_array($movement)
                    ? $movement['entityId']
                    : $movement->getDispatch()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getDispatch()->getNumber();
            } else if (($movement instanceof TrackingMovement && $movement->getArrivage())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::ARRIVAL_ENTITY)) {
                $data['entityPath'] = 'arrivage_show';
                $data['fromLabel'] = $this->translation->translate('Traçabilité', 'Flux - Arrivages', 'Divers', 'Arrivage', false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getArrivage()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getArrivage()->getNumeroArrivage();
            } else if (($movement instanceof TrackingMovement && $movement->getReception())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::RECEPTION_ENTITY)) {
                $data['entityPath'] = 'reception_show';
                $data['fromLabel'] = $this->translation->translate('Ordre', 'Réceptions', 'Réception', false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getReception()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getReception()->getNumber();
            } else if (($movement instanceof TrackingMovement && $movement->getMouvementStock()?->getTransferOrder())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::TRANSFER_ORDER_ENTITY)) {
                $data['entityPath'] = 'transfer_order_show';
                $data['fromLabel'] = 'Transfert de stock';
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getMouvementStock()->getTransferOrder()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getMouvementStock()->getTransferOrder()->getNumber();
            } else if (($movement instanceof TrackingMovement && $movement->getPreparation())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::PREPARATION_ENTITY)) {
                $data['entityPath'] = 'preparation_show';
                $data['fromLabel'] = 'Preparation';
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getPreparation()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getPreparation()->getNumero();
            } else if (($movement instanceof TrackingMovement && $movement->getDelivery())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::DELIVERY_ORDER_ENTITY)) {
                $data['entityPath'] = 'livraison_show';
                $data['fromLabel'] = $this->translation->translate("Ordre", "Livraison", "Ordre de livraison", false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getDelivery()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getDelivery()->getNumero();
            } else if (($movement instanceof TrackingMovement && $movement->getDeliveryRequest())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::DELIVERY_REQUEST_ENTITY)) {
                $data['entityPath'] = 'demande_show';
                $data['fromLabel'] = $this->translation->translate("Demande", "Livraison", "Demande de livraison", false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getDeliveryRequest()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getDeliveryRequest()->getNumero();
            } else if (($movement instanceof TrackingMovement && $movement->getShippingRequest())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::SHIPPING_REQUEST_ENTITY)) {
                $data['entityPath'] = 'shipping_request_show';
                $data['fromLabel'] = $this->translation->translate("Demande", "Expédition", "Demande d'expédition", false);
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getShippingRequest()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getShippingRequest()->getNumber();
            }
            else if (($movement instanceof TrackingMovement && $movement->getProductionRequest())
                || (is_array($movement) && ($movement['entity'] ?? null) === TrackingMovement::PRODUCTION_REQUEST_ENTITY)) {
                $data['entityPath'] = 'production_request_show';
                $data['fromLabel'] = 'Production';
                $data['entityId'] = is_array($movement)
                    ? $movement['entityId']
                    : $movement->getProductionRequest()->getId();
                $data['from'] = is_array($movement)
                    ? $movement['entityNumber']
                    : $movement->getProductionRequest()->getNumber();
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
            'group' => $movement->getPackGroup()
                ? ($movement->getPackGroup()->getCode() . '-' . ($movement->getGroupIteration() ?: '?'))
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
            $errorPackCodes = [];
            $errorType = null;
            $packCodes = explode(',', $data['pack']);

            foreach ($packCodes as $packCode) {
                $existingPack = $packRepository->findOneBy(['code' => $packCode]);
                $isGroup = $existingPack?->isGroup();
                if ($isGroup) {
                    $errorType = Pack::PACK_IS_GROUP;
                    $errorPackCodes = [];
                    break;
                } else if($existingPack?->getGroup()) {
                    if($existingPack->getGroup()->getId() === $parentPack?->getId()) {
                        $errorType = Pack::PACK_ALREADY_IN_GROUP;
                        $errorPackCodes = [];
                        break;
                    }
                    $errorType = Pack::CONFIRM_SPLIT_PACK;
                    $errorPackCodes[] = $existingPack->getCode();
                }
            }

            if ($errorType && (!$data["forced"] ?? false)) {
                return $this->treatPersistTrackingError([
                    "packs" => $errorPackCodes,
                    "error" => $errorType,
                ]);
            }
            else {
                $createdMovements = [];
                $isNewGroupInstance = false;
                if (!$parentPack) {
                    $parentPack = $this->groupService->createParentPack($data);
                    $entityManager->persist($parentPack);
                    $isNewGroupInstance = true;
                } else if ($parentPack->getContent()->isEmpty()) {
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

                $countContent = $parentPack->getContent()->count() + count($packCodes);
                if($countContent > Pack::GROUPING_LIMIT) {
                    $limit = Pack::GROUPING_LIMIT;
                    $packParentCode = $parentPack->getCode();
                    throw new FormException("Le groupe $packParentCode ne peut pas contenir plus de $limit unités logistiques.");
                }

                foreach ($packCodes as $packCode) {

                    $packParent = null;
                    $movedPack = $packRepository->findOneBy(['code' => $packCode]);

                    $packSplittingCase = $movedPack?->getGroup() !== null;

                    // case pack splitting
                    if(!isset($movedPack)
                        || $packSplittingCase) {

                        if ($packSplittingCase) {
                            $packParent = $movedPack;

                            $childNumber = $packParent->getSplitTargets()->count();
                            $packCode = $packParent->getCode() . '.' . ($childNumber + 1);
                        }

                        $movedPack = $this->packService->persistPack($entityManager, $packCode, 1);
                    }

                    $groupingTrackingMovement = $this->createTrackingMovement(
                        $movedPack,
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

                    $movedPack->setGroup($parentPack);
                    $entityManager->persist($groupingTrackingMovement);
                    $createdMovements[] = $groupingTrackingMovement;

                    if($packSplittingCase) {
                        $this->manageSplitPack($entityManager, $packParent, $movedPack, $date);
                    }
                }
                return [
                    'success' => true,
                    'msg' => 'OK',
                    'createdMovements' => $createdMovements,
                ];
            }
        }
    }

    public function treatPersistTrackingError(array $res): array {
        if (isset($res["error"])) {
            if (in_array($res["error"], [
                Pack::CONFIRM_CREATE_GROUP,
                Pack::CONFIRM_SPLIT_PACK,
            ])) {
                $packs = $res["packs"] ?? [];
                $packsCount = count($packs);
                $packsStr = Stream::from($res["packs"] ?? [])->join(', ');
                $suffixMessage = match($packsCount) {
                    0 => "Des unités logistiques sont présentes dans des groupes.",
                    1 => "L'unité logistique {$packsStr} est présente dans un groupe.",
                    // plural
                    default => "Les unités logistiques {$packsStr} sont présentes dans des groupes."
                };

                $message = match ($res["error"]) {
                    Pack::CONFIRM_CREATE_GROUP => "Confirmer le mouvement l\'enlèvera du groupe. <br>Voulez-vous continuer ?",
                    Pack::CONFIRM_SPLIT_PACK => match ($packsCount) {
                        1 => "Voulez-vous la diviser ?",
                        // plural or 0
                        default => "Voulez-vous les diviser ?",
                    },
                    default => throw new Exception("Unknown error {$res["error"]}"),
                };

                return [
                    "success" => true,
                    "group" => $res["packs"],
                    "error" => $res["error"],
                    "confirmMessage" => "{$suffixMessage} <br/> {$message}",
                ];
            } else if ($res['error'] === Pack::IN_ONGOING_RECEPTION) {
                return [
                    "success" => false,
                    "msg" => $this->translationService->translate("Traçabilité", "Mouvements", "L'unité logistique est dans une réception en attente et ne peut pas être mouvementée."),
                ];
            } else if ($res["error"] === Pack::PACK_IS_GROUP) {
                return [
                    "success" => false,
                    "msg" => "Une des UL choisie est un groupe, veuillez choisir une UL valide.",
                ];
            } else if ($res["error"] === Pack::PACK_ALREADY_IN_GROUP) {
                return [
                    "success" => false,
                    "msg" => "Une des UL scannée est déjà présente dans ce groupe.",
                ];
            }

            throw new Exception('untreated error');
        }
        else {
            return $res;
        }
    }

    public function createTrackingMovement(Pack|string       $packOrCode,
                                           ?Emplacement      $location,
                                           Utilisateur       $user,
                                           DateTime          $date,
                                           bool              $fromNomade,
                                           ?bool             $finished,
                                           Statut|string|int $trackingType,
                                           array             $options = []): TrackingMovement {
        $entityManager = $options['entityManager'] ?? $this->entityManager;

        $type = $this->getTrackingType($entityManager, $trackingType);

        if (!isset($type)) {
            throw new Exception('Le type de mouvement traca donné est invalide');
        }

        $commentaire = $options['commentaire'] ?? null;
        $createHistoryTracking = $options['historyTracking'] ?? true;
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
        $manualDelayStart = $options['manualDelayStart'] ?? null;
        $groupIteration = $options['groupIteration'] ?? null;

        /** @var Pack|null $group */
        $group = $options['parent'] ?? null;
        $removeFromGroup = $options['removeFromGroup'] ?? false;

        $pack = $this->packService->persistPack($entityManager, $packOrCode, $quantity, $natureId, $options['onlyPack'] ?? false);
        $doUngroup = !$disableUngrouping
            && $pack->getGroup()
            && in_array($type->getCode(), [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE]);

        $orderIndex = $options["orderIndex"]
            ?? floor(hrtime(true) / 1000000);

        $tracking = new TrackingMovement();
        $tracking
            ->setPack($pack)
            ->setQuantity($quantity)
            ->setEmplacement($location)
            ->setOperateur($user)
            ->setUniqueIdForMobile($uniqueIdForMobile ?: ($fromNomade ? $this->generateUniqueIdForMobile($entityManager, $date) : null))
            ->setDatetime($date)
            ->setFinished($finished)
            ->setType($type)
            ->setOrderIndex($orderIndex + 2) // order index greater than the order index of the ungroup tracking movement next creation
            ->setMouvementStock($mouvementStock)
            ->setCommentaire(!empty($commentaire) ? $commentaire : null)
            ->setMainMovement($mainMovement)
            ->setPreparation($preparation)
            ->setDelivery($delivery)
            ->setLogisticUnitParent($logisticUnitParent);

        // must be after movement initialization
        // after set type & location
        // $editedTrackingOnSetEvent is not necessary the same as $tracking one due to WIIS-12109 task
        $this->setTrackingEvent($tracking, (bool) $manualDelayStart, $editedTrackingOnSetEvent);

        $tracking->calculateTrackingDelayData = [
            "previousTrackingEvent" => $this->getLastPackMovement($pack)?->getEvent(),
            "nextTrackingEvent"     => $editedTrackingOnSetEvent->getEvent(),
            "nextType"              => $tracking->getType()?->getCode(),
        ];

        if ($attachments) {
            foreach($attachments as $attachment) {
                $tracking->addAttachment($attachment);
            }
        }

        if(!$doUngroup && $group) {
            // Si pas de mouvement de dégroupage, on set le parent
            $tracking->setPackGroup($group);
            $tracking->setGroupIteration($groupIteration ?: $group->getGroupIteration());

            if($type->getCode() === TrackingMovement::TYPE_GROUP && $createHistoryTracking) {
                $this->packService->persistLogisticUnitHistoryRecord($entityManager,  $group, [
                    "message" => $this->buildCustomLogisticUnitHistoryRecord($tracking, [], $pack),
                    "historyDate" => $tracking->getDatetime(),
                    "user" => $tracking->getOperateur(),
                    "type" => ucfirst("Entrée d'UL"),
                    "location" => $tracking->getEmplacement(),
                ]);
            }
        }

        $this->managePackLinksWithTracking(
            $entityManager,
            [
                $tracking,
                ...($editedTrackingOnSetEvent !== $tracking ? [$editedTrackingOnSetEvent] : [])
            ]
        );
        $this->managePackLinksWithOperations($entityManager, $tracking, [
            "from" => $from,
            "receptionReferenceArticle" => $receptionReferenceArticle,
            "refOrArticle" => $refOrArticle
        ]);
        $this->treatGroupDrop($entityManager, $pack, $tracking);
        $this->manageTrackingFiles($tracking, $fileBag);

        $natureChangedData = $this->changeNatureAccordingToLocation($tracking);

        if ($createHistoryTracking) {
            $this->packService->persistLogisticUnitHistoryRecord($entityManager, $pack, [
                "message" => $this->buildCustomLogisticUnitHistoryRecord($tracking, $natureChangedData),
                "historyDate" => $tracking->getDatetime(),
                "user" => $tracking->getOperateur(),
                "type" => ucfirst($tracking->getType()->getCode()),
                "location" => $tracking->getEmplacement(),
            ]);
        }

        if($manualDelayStart){
            $this->createTrackingMovement(
                $pack,
                $location,
                $user,
                $manualDelayStart,
                $fromNomade,
                $finished,
                TrackingMovement::TYPE_INIT_TRACKING_DELAY,
                [
                    'quantity' => $pack->getQuantity(),
                    'commentaire' => $commentaire,
                    'uniqueIdForMobile' => $fromNomade ? $this->generateUniqueIdForMobile($entityManager, $date) : null,
                    'preparation' => $preparation,
                    'delivery' => $delivery,
                    'mouvementStock' => $mouvementStock,
                    'mainMovement' => $mainMovement,
                    'logisticUnitParent' => $logisticUnitParent,
                    'orderIndex' => $orderIndex
                ]
            );
        }

        if ($doUngroup) {
            $this->createTrackingMovement(
                $pack,
                $location,
                $user,
                $date,
                $fromNomade,
                $finished,
                TrackingMovement::TYPE_UNGROUP,
                [
                    'entityManager' => $entityManager,
                    'mouvementStock' => $mouvementStock,
                    'commentaire' => $commentaire,
                    'uniqueIdForMobile' => $fromNomade ? $this->generateUniqueIdForMobile($entityManager, $date) : null,
                    'natureId' => $natureId,
                    'disableUngrouping' => true,
                    'preparation' => $preparation,
                    'delivery' => $delivery,
                    'removeFromGroup' => true,
                    'groupIteration' => $pack->getGroup()?->getGroupIteration(),
                    'parent' => $pack->getGroup(),
                    'orderIndex' => $orderIndex + 1
                ]
            );

            if ($createHistoryTracking) {
                $this->packService->persistLogisticUnitHistoryRecord($entityManager, $pack->getGroup(), [
                    "message" => $this->buildCustomLogisticUnitHistoryRecord($tracking, $natureChangedData, $pack),
                    "historyDate" => $tracking->getDatetime(),
                    "user" => $tracking->getOperateur(),
                    "type" => ucfirst("Sortie d'UL"),
                    "location" => $tracking->getEmplacement(),
                ]);
            }

            if ($removeFromGroup) {
                $pack->setGroup(null);
            }
        }

        return $tracking;
    }

    private function managePackLinksWithOperations(EntityManagerInterface $entityManager,
                                                   TrackingMovement       $tracking,
                                                   array                  $options): void {
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
            match (get_class($from)) {
                Reception::class => $tracking->setReception($from),
                Arrivage::class => $tracking->setArrivage($from),
                Dispatch::class => $tracking->setDispatch($from),
                Demande::class => $tracking->setDeliveryRequest($from),
                Preparation::class => $tracking->setPreparation($from),
                Livraison::class => $tracking->setDelivery($from),
                ShippingRequest::class => $tracking->setShippingRequest($from),
                ProductionRequest::class => $tracking->setProductionRequest($from),
                default => null,
            };
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

        foreach ($trackingMovement->getAttachments() as $attachement) {
            $entityManager->persist($attachement);
        }
    }

    public function changeNatureAccordingToLocation(TrackingMovement $trackingMovement): array {
        $pack = $trackingMovement->getPack();
        $location = $trackingMovement->getEmplacement();
        $oldNature = $pack?->getNature();
        $returnData = [];

        if(($pack?->isBasicUnit() || $pack?->getLastAction() === $trackingMovement)){
            $isNatureChangeEnabled  = match (true) {
                $trackingMovement->isDrop()    => $location?->isNewNatureOnDropEnabled(),
                $trackingMovement->isPicking() => $location?->isNewNatureOnPickEnabled(),
                default                        => false,
            };

            if ($isNatureChangeEnabled) {
                $newNature = match (true) {
                    $trackingMovement->isDrop()    => $location?->getNewNatureOnDrop(),
                    $trackingMovement->isPicking() => $location?->getNewNatureOnPick(),
                    default                        => null,
                };

                if ($newNature?->getId() === $pack?->getNature()?->getId()) {
                    $returnData = [
                        "natureChanged" => false,
                    ];
                } else {
                    $pack->setNature($newNature);
                    $trackingMovement
                        ->setOldNature($oldNature)
                        ->setNewNature($newNature);

                    $returnData =[
                        "natureChanged" => true,
                        "oldNature" => $oldNature,
                        "newNature" => $newNature,
                    ];
                }
            }
        } else {
            $returnData =[
                "natureChanged" => false,
            ];
        }
        return $returnData;
    }

    /**
     * @param TrackingMovement[] $editedTrackingMovements
     */
    public function managePackLinksWithTracking(EntityManagerInterface $entityManager,
                                                array                  $editedTrackingMovements): void {

        foreach($editedTrackingMovements as $tracking) {
            $pack = $tracking->getPack();

            if (!$pack) {
                return;
            }

            $lastTrackingMovements = $pack->getTrackingMovements()->toArray();
            $locationClusterRecordRepository = $entityManager->getRepository(LocationClusterRecord::class);

            /** @var TrackingMovement|null $previousLastAction */
            $previousLastAction = (!empty($lastTrackingMovements) && count($lastTrackingMovements) > 1)
                ? $lastTrackingMovements[1]
                : null;

            if (!$pack->getFirstAction()
                || $this->compareMovements($pack->getFirstAction(), $tracking) === TrackingMovementService::COMPARE_A_AFTER_B) {
                $pack->setFirstAction($tracking);
            }

            if (!$pack->getLastAction()
                || $this->compareMovements($pack->getLastAction(), $tracking) === TrackingMovementService::COMPARE_A_BEFORE_B) {
                $pack->setLastAction($tracking);
            }

            if ($tracking->isPicking()
                && (
                    !$pack->getLastPicking()
                    || $this->compareMovements($pack->getLastPicking(), $tracking) === TrackingMovementService::COMPARE_A_BEFORE_B
                )) {
                $pack->setLastPicking($tracking);
            }

            if ($tracking->isDrop()
                && (
                    !$pack->getLastDrop()
                    || $this->compareMovements($pack->getLastDrop(), $tracking) === TrackingMovementService::COMPARE_A_BEFORE_B
                )) {
                $pack->setLastDrop($tracking);
            }

            if ($tracking->isDrop()
                && (!$pack->getLastOngoingDrop() || $this->compareMovements($pack->getLastOngoingDrop(), $tracking) === TrackingMovementService::COMPARE_A_BEFORE_B)) {
                $pack->setLastOngoingDrop($tracking);
            }
            else {
                if ($tracking->isPicking()) {
                    $pack->setLastOngoingDrop(null);
                }
            }

            if ($tracking->isStart()
                && (!$pack->getLastStart() || $this->compareMovements($pack->getLastStart(), $tracking) === TrackingMovementService::COMPARE_A_BEFORE_B)) {
                $pack->setLastStart($tracking);
            }

            if ($tracking->isStop()
                && (!$pack->getLastStop() || $this->compareMovements($pack->getLastStop(), $tracking) === TrackingMovementService::COMPARE_A_BEFORE_B)) {
                $pack->setLastStop($tracking);
            }

            $location = $tracking->getEmplacement();
            if ($location) {
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
                            || !$previousLastAction
                            || ($previousRecordLastTracking->getId() !== $previousLastAction->getId())) {
                            $record->setFirstDrop($tracking);
                        }
                        $this->locationClusterService->setMeter(
                            $entityManager,
                            LocationClusterService::METER_ACTION_INCREASE,
                            $tracking->getDatetime(),
                            $cluster
                        );

                        if ($previousLastAction?->isPicking()) {
                            $locationPreviousLastTracking = $previousLastAction->getEmplacement();
                            $locationClustersPreviousLastTracking = $locationPreviousLastTracking
                                ? $locationPreviousLastTracking->getClusters()
                                : [];
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
                    else {
                        $record?->setActive(false);
                    }

                    // set last tracking after check of drop
                    $record?->setLastTracking($tracking);
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

        if ($movement["from"] ?? false) {
            $from = $movement["from"];
        } else {
            $fromData = $this->getFromColumnData($movement);
            $fromLabel = $fromData["fromLabel"] ?? "";
            $fromNumber = $fromData["from"] ?? "";
            $from = trim("$fromLabel $fromNumber") ?: null;
        }

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
                    "packGroup" => $movement["packGroup"],
                };
            }
        }

        $this->CSVExportService->putLine($handle, $line);
    }

    public function getMobileUserPicking(EntityManagerInterface $entityManager,
                                         Utilisateur            $user,
                                         string                 $type,
                                         array                  $filterDemandeCollecteIds = [],
                                                                $includeMovementId = false): array {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $pickingMovements = $trackingMovementRepository->getPickingByOperatorAndNotDropped($user, $type, $filterDemandeCollecteIds);
        return Stream::from($pickingMovements)
            ->map(fn(TrackingMovement $trackingMovement) => (
                $this->normalizer->normalize($trackingMovement, null, [
                    "usage" => SerializerUsageEnum::MOBILE_DROP_MENU,
                    "includeMovementId" => $includeMovementId,
                ])
            ))
            ->values();
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
            $pack,
            $location,
            $nomadUser,
            $date,
            true,
            $mvt['finished'],
            $type,
            $options,
        );

        $associatedGroup = $pack->getGroup();
        if ($associatedGroup) {
            $associatedGroup->removeContent($pack);
            if ($associatedGroup->getContent()->isEmpty()) {
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
                $stockMovement = $this->stockMovementService->createMouvementStock(
                    $nomadUser,
                    $article->getEmplacement(),
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_TRANSFER
                );

                $this->stockMovementService->finishStockMovement($stockMovement, new DateTime(), $location);
                $article->setEmplacement($location);

                $entityManager->persist($stockMovement);

                $currentArticleOptions = [
                    "mouvementStock" => $stockMovement,
                ];
            }

            $createdMvt = $this->createTrackingMovement(
                $article->getTrackingPack() ?: $article->getBarCode(),
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
                $associatedGroup = $associatedPack->getGroup();

                if ($associatedGroup) {
                    $associatedGroup->removeContent($associatedPack);
                    if ($associatedGroup->getContent()->isEmpty()) {
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
            $associatedGroup = $associatedPack->getGroup();

            if (!$forced && $associatedGroup) {
                return [
                    'success' => false,
                    'error' => Pack::CONFIRM_CREATE_GROUP,
                    'msg' => Pack::CONFIRM_CREATE_GROUP,
                    'packs' => [$associatedPack->getCode()],
                ];
            } else if ($forced) {
                $associatedPack->setGroup(null);
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
            foreach ($parent->getContent() as $child) {
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
        $autoUngroup = (bool) $this->settingsService->getValue($entityManager, Setting::AUTO_UNGROUP);

        if (!$autoUngroup
            || !$tracking->isDrop()
            || !$pack->isGroup()
            || $pack->getContent()->isEmpty()
        ){
            return;
        }

        $autoUngroupTypes = Stream::explode(',', $this->settingsService->getValue($entityManager, Setting::AUTO_UNGROUP_TYPES) ?: '')
                ->filter(fn(string $type) => !empty($type))
                ->map(fn(string $type) => (int) $type)
                ->toArray();
        $linkedDispatches = $tracking->getEmplacement()
            ? $tracking->getEmplacement()->getDispatchesTo()
                ->map(fn (Dispatch $dispatch) => $dispatch->getId())
                ->toArray()
            : [];

        foreach ($pack->getContent() as $children) {
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

                    $pack->removeContent($children);
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
                ["code" => "packGroup", "label" => $this->translationService->translate('Traçabilité', 'Unités logistiques', "Onglet \"Groupes\"", 'Groupe', false),],
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

    public function getTrackingMovementExportableColumnsSorted(EntityManagerInterface $entityManager): array {
        return Stream::from($this->getTrackingMovementExportableColumns($entityManager))
            ->reduce(
                static function (array $carry, array $column) {
                    $carry["labels"][] = $column["label"] ?? '';
                    $carry["codes"][] = $column["code"] ?? '';
                    return $carry;
                },
                ["labels" => [], "codes" => []]
            );
    }

    public function buildCustomLogisticUnitHistoryRecord(TrackingMovement $trackingMovement,
                                                         array            $natureChangedData = [],
                                                         ?Pack            $pack = null): string {

        $natureChanged = $natureChangedData["natureChanged"] ?? false;
        $oldNature = $natureChangedData["oldNature"] ?? null;
        $newNature = $natureChangedData["newNature"] ?? null;

        $pack = $trackingMovement->getPack();
        $packGroup = $trackingMovement->getPackGroup();

        $values = [
            FixedFieldEnum::quantity->value => $trackingMovement->getQuantity(),
            FixedFieldEnum::comment->value => $this->formatService->html($trackingMovement->getCommentaire()),
            FixedFieldEnum::group->value => $this->formatService->pack($packGroup),
        ];

        if($pack) {
            $values["Unité logistique"] = $this->formatService->pack($pack);
        }

        if ($natureChanged) {
            $values["Nouvelle nature"] = $this->formatService->nature($newNature);
            $values["Ancienne nature"] = $this->formatService->nature($oldNature);
        }
        else {
            $values[FixedFieldEnum::nature->value] = $this->formatService->nature($pack->getNature());
        }

        return $this->formatService->list($values);
    }

    public function setTrackingEvent(TrackingMovement  $trackingMovement,
                                     bool              $manualDelayStart,
                                     ?TrackingMovement &$trackingToSetEvent = null): void {
        $trackingLocation = $trackingMovement->getEmplacement();
        $trackingToSetEvent = $trackingMovement;

        if (!$trackingLocation) {
            return;
        }

        if ($trackingMovement->isInitTrackingDelay()) {
            $trackingEvent = TrackingEvent::START;
        }
        else if ($trackingMovement->isPicking()
            && $trackingLocation->isStartTrackingTimerOnPicking()
            && !$manualDelayStart) {
            $pack = $trackingMovement->getPack();
            $location = $trackingMovement->getEmplacement();
            $lastOngoingDrop = $pack->getLastOngoingDrop();

            // the last ongoing drop is defined,
            // AND its location is same as the current movement location,
            // AND he hasn't a tracking event OR he has a STOP tracking event (see WIIS-12750 / case 1)
            // => then we set the start event on the drop movement
            if ($location?->getId()
                && $lastOngoingDrop
                && in_array($lastOngoingDrop->getEvent(), [null, TrackingEvent::STOP])
                && $location->getId() === $lastOngoingDrop->getEmplacement()?->getId()) {
                $trackingToSetEvent = $lastOngoingDrop;
            }

            $trackingEvent = TrackingEvent::START;
        }
        else if ($trackingMovement->isDrop()
            && $trackingLocation->isStopTrackingTimerOnDrop()) {
            $trackingEvent = TrackingEvent::STOP;
        }
        else if ($trackingMovement->isDrop()
            && $trackingLocation->isPauseTrackingTimerOnDrop()) {
            $trackingEvent = TrackingEvent::PAUSE;
        }

        $trackingToSetEvent->setEvent($trackingEvent ?? null);
    }


    public function compareMovements(TrackingMovement $trackingMovementA,
                                     TrackingMovement $trackingMovementB): int {
        $ABeforeB = (
            $trackingMovementA->getDatetime() < $trackingMovementB->getDatetime()
            || (
                $trackingMovementA->getDatetime() == $trackingMovementB->getDatetime()
                && (
                    $trackingMovementA->getOrderIndex() < $trackingMovementB->getOrderIndex()
                    // second movement not persisted in database
                    || ($trackingMovementA->getId() && !$trackingMovementB->getId())
                    || ( // two movement persist in database
                        $trackingMovementA->getId()
                        && $trackingMovementB->getId()
                        && $trackingMovementA->getId() < $trackingMovementB->getId()
                    )
                )
            )
        );
        $AAfterB = (
            $trackingMovementA->getDatetime() > $trackingMovementB->getDatetime()
            || (
                $trackingMovementA->getDatetime() == $trackingMovementB->getDatetime()
                && (
                    $trackingMovementA->getOrderIndex() > $trackingMovementB->getOrderIndex()

                    // first movement not persisted in database
                    || (!$trackingMovementA->getId() && $trackingMovementB->getId())
                    || (// two movement persist in database
                        $trackingMovementA->getId()
                        && $trackingMovementB->getId()
                        && $trackingMovementA->getId() > $trackingMovementB->getId()
                    )
                )
            )
        );

        return match(true) {
            $ABeforeB => self::COMPARE_A_BEFORE_B,
            $AAfterB  => self::COMPARE_A_AFTER_B,
            default   => self::COMPARE_A_EQUALS_B,
        };
    }

    /**
     * Return more recent movement between $pack->getLastDrop() and $pack->getLastPicking().
     * If the two movements are not distinguishable we return in priority the last drop.
     *
     * @see Pack::getLastDrop()
     * @see Pack::getLastPicking()
     */
    public function getLastPackMovement(Pack $pack): ?TrackingMovement {
        if (!$pack->getLastDrop() || !$pack->getLastPicking()) {
            return $pack->getLastDrop() ?: $pack->getLastPicking();
        }

        $compareRes = $this->compareMovements($pack->getLastPicking(), $pack->getLastDrop());

        return match($compareRes) {
            self::COMPARE_A_AFTER_B  => $pack->getLastPicking(),
            default                  => $pack->getLastDrop(), // self::COMPARE_A_BEFORE_B or self::COMPARE_A_EQUALS_B or any other value
        };
    }

    public function manageSplitPack(EntityManagerInterface $entityManager,
                                    Pack                   $packParent,
                                    Pack                   $pack,
                                    DateTime               $date): void {

        if ($packParent->getSplitCountFrom() >= Pack::SPLIT_MAX_ANCESTORS) {
            throw new FormException("Impossible de diviser le colis {$packParent->getCode()} : nombre maximum de générations atteint.");
        }

        if ($packParent->getSplitCountTarget() >= Pack::SPLIT_MAX_CHILDREN) {
            throw new FormException("Impossible de diviser le colis {$packParent->getCode()} : nombre maximum d'enfants atteint.");
        }

        $packSplitTrackingMovement = $this->createTrackingMovement(
            $pack,
            null,
            $this->userService->getUser(),
            $date,
            true,
            true,
            TrackingMovement::TYPE_PACK_SPLIT,
            [
                'disableUngrouping' => true,
                'historyTracking' => false
            ]
        );

        $entityManager->persist($packSplitTrackingMovement);

        if($packParent->getLastOngoingDrop()){
            $this->manageLinksForClonedMovement($packParent->getLastOngoingDrop(), $packSplitTrackingMovement);

            $splitFromLastOnGoingDrop = $packParent->getLastOngoingDrop();
            $targetDropTrackingMovement = $this->createTrackingMovement(
                $pack,
                $splitFromLastOnGoingDrop->getEmplacement(),
                $splitFromLastOnGoingDrop->getOperateur(),
                $splitFromLastOnGoingDrop->getDatetime(),
                true,
                $splitFromLastOnGoingDrop->getFinished(),
                $splitFromLastOnGoingDrop->getType(),
                [
                    'disableUngrouping' => true,
                    "parent" => $splitFromLastOnGoingDrop->getPackGroup(),
                    "historyTracking" => false,
                ]
            );

            $this->manageLinksForClonedMovement($splitFromLastOnGoingDrop, $targetDropTrackingMovement);
            $entityManager->persist($targetDropTrackingMovement);

            $pack->setLastOngoingDrop($targetDropTrackingMovement);
        }

        $this->packService->persistLogisticUnitHistoryRecord($entityManager,  $packParent, [
            "message" => $this->buildCustomLogisticUnitHistoryRecord($packSplitTrackingMovement, [], $pack),
            "historyDate" => $packSplitTrackingMovement->getDatetime(),
            "user" => $packSplitTrackingMovement->getOperateur(),
            "type" => ucfirst(TrackingMovement::TYPE_PACK_SPLIT),
            "location" => $packSplitTrackingMovement->getEmplacement(),
        ]);

        $packSplit = (new PackSplit())
            ->setTarget($pack)
            ->setFrom($packParent)
            ->setSplittingAt(new DateTime());

        $entityManager->persist($packSplit);
    }
}
