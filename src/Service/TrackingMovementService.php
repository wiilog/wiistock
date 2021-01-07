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
use DateTime;
use Exception;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TrackingMovementService
{
    public const INVALID_LOCATION_TO = 'invalid-location-to';

    private $templating;
    private $router;
    private $userService;
    private $security;
    private $entityManager;
    private $attachmentService;
    private $freeFieldService;
    private $locationClusterService;
    private $visibleColumnService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                LocationClusterService $locationClusterService,
                                Twig_Environment $templating,
                                FreeFieldService $freeFieldService,
                                Security $security,
                                VisibleColumnService $visibleColumnService,
                                AttachmentService $attachmentService)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
        $this->attachmentService = $attachmentService;
        $this->locationClusterService = $locationClusterService;
        $this->freeFieldService = $freeFieldService;
        $this->visibleColumnService = $visibleColumnService;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws Exception
     */
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

    /**
     * @param TrackingMovement $movement
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowMouvement(TrackingMovement $movement)
    {
        $fromColumnData = $this->getFromColumnData($movement);

        $categoryFFRepository = $this->entityManager->getRepository(CategorieCL::class);
        $freeFieldsRepository = $this->entityManager->getRepository(FreeField::class);

        $categoryFF = $categoryFFRepository->findOneByLabel(CategorieCL::MVT_TRACA);
        $category = CategoryType::MOUVEMENT_TRACA;
        $freeFields = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categoryFF);

        $trackingPack = $movement->getPack();
        $packCode = $trackingPack->getCode();

        if(!$movement->getAttachments()->isEmpty()) {
            $attachmentsCounter = $movement->getAttachments()->count();
            $sAttachments = $attachmentsCounter > 1 ? 's' : '';
            $attachments = "<i class=\"fas fa-paperclip\" title=\"{$attachmentsCounter} pièce{$sAttachments} jointe{$sAttachments}\"></i>";
        }

        $rows = [
            'id' => $movement->getId(),
            'date' => $movement->getDatetime() ? $movement->getDatetime()->format('d/m/Y H:i') : '',
            'code' => $packCode,
            'origin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'location' => $movement->getEmplacement() ? $movement->getEmplacement()->getLabel() : '',
            'reference' => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getReference()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : ''),
            "label" => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getLibelle()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getLabel()
                    : ''),
            "quantity" => $movement->getQuantity() ? $movement->getQuantity() : '',
            "type" => $movement->getType() ? $movement->getType()->getNom() : '',
            "operator" => $movement->getOperateur() ? $movement->getOperateur()->getUsername() : '',
            "attachments" => $attachments ?? "",
            "actions" => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $movement,
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

    /**
     * @param string|Pack $packOrCode
     * @param Emplacement|null $location
     * @param Utilisateur $user
     * @param DateTime $date
     * @param bool $fromNomade
     * @param bool|null $finished
     * @param string|int $trackingType label ou id du mouvement traca
     * @param array $options = [
     *      'commentaire' => string|null,
     *      'quantity' => int|null,
     *      'natureId' => int|null,
     *      'mouvementStock' => MouvementStock|null,
     *      'fileBag' => FileBag|null, from => Arrivage|Reception|null],
     *      'entityManager' => EntityManagerInterface|null
     * @return TrackingMovement
     * @throws Exception
     */
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

        $pack = $this->getPack($entityManager, $packOrCode, $quantity, $natureId);

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

        $this->managePackLinksWithTracking($entityManager, $tracking);
        $this->manageTrackingLinks($entityManager, $tracking, $from, $receptionReferenceArticle);
        $this->manageTrackingFiles($tracking, $fileBag);

        return $tracking;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Pack|string $packOrCode
     * @param $quantity
     * @param $natureId
     * @return Pack
     */
    private function getPack(EntityManagerInterface $entityManager,
                             $packOrCode,
                             $quantity,
                             $natureId): Pack {
        $packRepository = $entityManager->getRepository(Pack::class);

        $codePack = $packOrCode instanceof Pack ? $packOrCode->getCode() : $packOrCode;

        $pack = ($packOrCode instanceof Pack)
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);

        if (!isset($pack)) {
            $pack = new Pack();
            $pack
                ->setQuantity($quantity)
                ->setCode($codePack);
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

        $alreadyLinkedArticle = $pack->getArticle();
        $alreadyLinkedReferenceArticle = $pack->getReferenceArticle();
        if (!isset($alreadyLinkedArticle) && !isset($alreadyLinkedReferenceArticle)) {
            $refOrArticle = (
                $referenceArticleRepository->findOneBy(['barCode' => $packCode])
                ?: $articleRepository->findOneBy(['barCode' => $packCode])
            );

            if ($refOrArticle instanceof ReferenceArticle) {
                $pack->setReferenceArticle($refOrArticle);
            } else if ($refOrArticle instanceof Article) {
                $pack->setArticle($refOrArticle);
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

    /**
     * @param TrackingMovement $tracking
     * @param $fileBag
     */
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

        $uniqueId = null;
        //same format as moment.defaultFormat
        $dateStr = $date->format(DateTime::ATOM);
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

    /**
     * @param EntityManagerInterface $entityManager
     * @param TrackingMovement $tracking
     */
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

        if ($tracking->isDrop()) {
            $pack->setLastDrop($tracking);
        }

        $location = $tracking->getEmplacement();
        if ($location) {
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
        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::MVT_TRACA);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::MOUVEMENT_TRACA, $categorieCL);

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['hiddenTitle' => 'Pièces jointes', 'name' => 'attachments', 'orderable' => false],
            ['title' => 'Issu de', 'name' => 'origin', 'orderable' => false],
            ['title' => 'Date', 'name' => 'date'],
            ['title' => 'mouvement de traçabilité.Colis', 'name' => 'code', 'translated' => true],
            ['title' => 'Référence', 'name' => 'reference'],
            ['title' => 'Libellé',  'name' => 'label'],
            ['title' => 'Quantité', 'name' => 'quantity'],
            ['title' => 'Emplacement', 'name' => 'location'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Opérateur', 'name' => 'operator']
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Pack $pack
     * @param Emplacement $location
     * @param Utilisateur $user
     * @param DateTime $date
     * @param Arrivage $arrivage
     * @throws Exception
     */
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
}
