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

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                LocationClusterService $locationClusterService,
                                Twig_Environment $templating,
                                FreeFieldService $freeFieldService,
                                Security $security,
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

    /**
     * @param TrackingMovement $movement
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowMouvement(TrackingMovement $movement)
    {
        if ($movement->getArrivage()) {
            $fromPath = 'arrivage_show';
            $fromLabel = 'arrivage.arrivage';
            $fromEntityId = $movement->getArrivage()->getId();
            $originFrom = $movement->getArrivage()->getNumeroArrivage();
        } else if ($movement->getReception()) {
            $fromPath = 'reception_show';
            $fromLabel = 'réception.réception';
            $fromEntityId = $movement->getReception()->getId();
            $originFrom = $movement->getReception()->getNumeroReception();
        } else if ($movement->getDispatch()) {
            $fromPath = 'dispatch_show';
            $fromLabel = 'acheminement.Acheminement';
            $fromEntityId = $movement->getDispatch()->getId();
            $originFrom = $movement->getDispatch()->getNumber();
        } else {
            $fromPath = null;
            $fromEntityId = null;
            $fromLabel = null;
            $originFrom = '-';
        }

        $categoryFFRepository = $this->entityManager->getRepository(CategorieCL::class);
        $freeFieldsRepository = $this->entityManager->getRepository(FreeField::class);

        $categoryFF = $categoryFFRepository->findOneByLabel(CategorieCL::MVT_TRACA);
        $category = CategoryType::MOUVEMENT_TRACA;
        $freeFields = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categoryFF);

        $rowCL = [];
        /** @var FreeField $freeField */
        foreach ($freeFields as $freeField) {
            $rowCL[$freeField['label']] = $this->freeFieldService->serializeValue([
                'valeur' => $movement->getFreeFieldValue($freeField['id']),
                "typage" => $freeField['typage'],
            ]);
        }

        $trackingPack = $movement->getPack();
        $packCode = $trackingPack->getCode();

        $rows = [
            'id' => $movement->getId(),
            'date' => $movement->getDatetime() ? $movement->getDatetime()->format('d/m/Y H:i') : '',
            'code' => $packCode,
            'origin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', [
                'from' => $originFrom,
                'fromLabel' => $fromLabel,
                'entityPath' => $fromPath,
                'entityId' => $fromEntityId
            ]),
            'location' => $movement->getEmplacement() ? $movement->getEmplacement()->getLabel() : '',
            'reference' => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getReference()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : ''),
            'label' => $movement->getReferenceArticle()
                ? $movement->getReferenceArticle()->getLibelle()
                : ($movement->getArticle()
                    ? $movement->getArticle()->getLabel()
                    : ''),
            'quantity' => $movement->getQuantity() ? $movement->getQuantity() : '',
            'type' => $movement->getType() ? $movement->getType()->getNom() : '',
            'operateur' => $movement->getOperateur() ? $movement->getOperateur()->getUsername() : '',
            'actions' => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $movement,
            ])
        ];

        $rows = array_merge($rowCL, $rows);
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
                }
                else if (isset($record)) {
                    $record->setActive(false);
                }

                if (isset($record)) {
                    // set last tracking after check of drop
                    $record->setLastTracking($tracking);
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
        }
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $columnsVisible = $currentUser->getColumnsVisibleForTrackingMovement();
        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::MVT_TRACA);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::MOUVEMENT_TRACA, $categorieCL);

        $columns = [
            ['title' => 'Actions', 'name' => 'actions', 'class' => 'display', 'alwaysVisible' => true, 'orderable' => false],
            ['title' => 'Issu de', 'name' => 'origin', 'orderable' => false],
            ['title' => 'Date', 'name' => 'date'],
            ['title' => 'mouvement de traçabilité.Colis', 'name' => 'code', 'translated' => true],
            ['title' => 'Référence', 'name' => 'reference'],
            ['title' => 'Libellé',  'name' => 'label'],
            ['title' => 'Quantité', 'name' => 'quantity'],
            ['title' => 'Emplacement', 'name' => 'location'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Opérateur', 'name' => 'operateur'],
        ];

        return array_merge(
            array_map(function (array $column) use ($columnsVisible) {
                return [
                    'title' => $column['title'],
                    'alwaysVisible' => $column['alwaysVisible'] ?? false,
                    'orderable' => $column['orderable'] ?? true,
                    'data' => $column['name'],
                    'name' => $column['name'],
                    'translated' => $column['translated'] ?? false,
                    'class' => $column['class'] ?? (in_array($column['name'], $columnsVisible) ? 'display' : 'hide')
                ];
            }, $columns),
            array_map(function (array $freeField) use ($columnsVisible) {
                return [
                    'title' => ucfirst(mb_strtolower($freeField['label'])),
                    'data' => $freeField['label'],
                    'name' => $freeField['label'],
                    'class' => (in_array($freeField['label'], $columnsVisible) ? 'display' : 'hide'),
                ];
            }, $freeFields)
        );
    }

}
