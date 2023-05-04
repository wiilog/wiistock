<?php


namespace App\Service;


use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Project;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\SubLineFieldsParam;
use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class DemandeLivraisonService
{
    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public StringService $stringService;

    #[Required]
    public RefArticleDataService $refArticleDataService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public PreparationsManagerService $preparationsManager;

    #[Required]
    public LivraisonsManagerService $livraisonsManager;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public FieldsParamService $fieldsParamService;

    #[Required]
    public NotificationService $notificationService;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public Security $security;

    #[Required]
    public ArticleDataService $articleService;

    #[Required]
    public MouvementStockService $stockMovementService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    private ?array $freeFieldsConfig = null;

    public function getDataForDatatable($params = null, $statusFilter = null, $receptionFilter = null, Utilisateur $user)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $demandeRepository = $this->entityManager->getRepository(Demande::class);

        if ($statusFilter) {
            $filters = [
                [
                    'field' => 'statut',
                    'value' => $statusFilter
                ]
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DEM_LIVRAISON, $user);
        }
        $queryResult = $demandeRepository->findByParamsAndFilters($params, $filters, $receptionFilter, $user, $this->visibleColumnService);

        $demandeArray = $queryResult['data'];

        $rows = [];
        foreach ($demandeArray as $demande) {
            $rows[] = $this->dataRowDemande($demande);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowDemande(Demande $demande): array {
        $idDemande = $demande->getId();
        $url = $this->router->generate('demande_show', ['id' => $idDemande]);

        $prepas = Stream::from($demande->getPreparations())
            ->filter(fn(Preparation $preparation) => $preparation->getPairings()->count() > 0)
            ->first();
        $pairing = $prepas ? $prepas->getPairings()->first() : null;
        $sensorCode = $pairing ? $pairing->getSensorWrapper()->getName() : null;

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::DEMANDE_LIVRAISON, CategoryType::DEMANDE_LIVRAISON);
        }
        $row = [
            'createdAt' => $this->formatService->datetime($demande->getCreatedAt()),
            'validatedAt' => $this->formatService->datetime($demande->getValidatedAt()),
            'destination' => $this->formatService->location($demande->getDestination()),
            'receiver' => $this->formatService->user($demande->getReceiver()),
            'comment' => $demande->getCommentaire(),
            'requester' => $this->formatService->deliveryRequester($demande),
            'number' => $demande->getNumero() ?? '',
            'status' => $this->formatService->status($demande->getStatut()),
            'type' => $this->formatService->type($demande->getType()),
            'expectedAt' => $this->formatService->date($demande->getExpectedAt()),
            'project' => $demande->getProject()?->getCode() ?? '',
            'actions' => $this->templating->render('demande/datatableDemandeRow.html.twig', [
                'idDemande' => $idDemande,
                'url' => $url,
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => (bool)$pairing,
            ]),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $demande->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = FormatHelper::freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

    public function parseRequestForCard(Demande             $demande,
                                        DateService         $dateService,
                                        array               $averageRequestTimesByType): array
    {

        $requestStatus = $demande->getStatut()?->getCode();
        $demandeType = $demande->getType() ? $demande->getType()->getLabel() : '';

        if ($requestStatus === Demande::STATUT_A_TRAITER && !$demande->getPreparations()->isEmpty()) {
            $href = $this->router->generate('preparation_index', ['demandId' => $demande->getId()]);
        } else if (
            (
                $requestStatus === Demande::STATUT_LIVRE_INCOMPLETE ||
                $requestStatus === Demande::STATUT_INCOMPLETE ||
                $requestStatus === Demande::STATUT_PREPARE
            )
            && !$demande->getLivraisons()->isEmpty()
        ) {
            $href = $this->router->generate('livraison_index', ['demandId' => $demande->getId()]);
        } else {
            $href = $this->router->generate('demande_show', ['id' => $demande->getId()]);
        }

        $articlesCounter = ($demande->getArticleLines()->count() + $demande->getReferenceLines()->count());
        $articlePlural = $articlesCounter > 1 ? 's' : '';
        $bodyTitle = $articlesCounter . ' article' . $articlePlural . ' - ' . $demandeType;

        $typeId = $demande->getType() ? $demande->getType()->getId() : null;
        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date de livraison non estimée';
        $today = new DateTime();

        if (isset($averageTime)) {
            $expectedDate = (clone $demande->getCreatedAt())
                ->add($dateService->secondsToDateInterval($averageTime->getAverage()));
            if ($expectedDate >= $today) {
                $estimatedFinishTimeLabel = 'Date et heure de livraison prévue';
                $deliveryDateEstimated = $expectedDate->format('d/m/Y H:i');
                if ($expectedDate->format('d/m/Y') === $today->format('d/m/Y')) {
                    $estimatedFinishTimeLabel = 'Heure de livraison estimée';
                    $deliveryDateEstimated = $expectedDate->format('H:i');
                }
            }
        }

        $requestDate = $demande->getCreatedAt();
        $requestDateStr = $requestDate
            ? (
                $requestDate->format('d ')
                . DateService::ENG_TO_FR_MONTHS[$requestDate->format('M')]
                . $requestDate->format(' (H\hi)')
            )
            : 'Non défini';

        $statusesToProgress = [
            Demande::STATUT_BROUILLON => 0,
            Demande::STATUT_A_TRAITER => 25,
            Demande::STATUT_PREPARE => 50,
            Demande::STATUT_INCOMPLETE => 50,
            Demande::STATUT_LIVRE_INCOMPLETE => 75,
            Demande::STATUT_LIVRE => 100
        ];

        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page d\'état actuel de la ' . mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false)),
            'estimatedFinishTime' => $deliveryDateEstimated,
            'estimatedFinishTimeLabel' => $estimatedFinishTimeLabel,
            'requestStatus' => $requestStatus,
            'requestBodyTitle' => $bodyTitle,
            'requestLocation' => $demande->getDestination() ? $demande->getDestination()->getLabel() : 'Non défini',
            'requestNumber' => $demande->getNumero(),
            'requestDate' => $requestDateStr,
            'requestUser' => $demande->getUtilisateur() ? $demande->getUtilisateur()->getUsername() : 'Non défini',
            'cardColor' => $requestStatus === Demande::STATUT_BROUILLON ? 'light-grey' : 'lightest-grey',
            'bodyColor' => $requestStatus === Demande::STATUT_BROUILLON ? 'white' : 'light-grey',
            'topRightIcon' => 'livreur.svg',
            'progress' => $statusesToProgress[$requestStatus] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'emergencyText' => '',
            'progressBarBGColor' => $requestStatus === Demande::STATUT_BROUILLON ? 'white' : 'light-grey',
        ];
    }

    public function newDemande($data,
                               EntityManagerInterface $entityManager,
                               FreeFieldService $champLibreService,
                               bool $fromNomade = false)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $receptionRepository = $entityManager->getRepository(Reception::class);
        $projectRepository = $entityManager->getRepository(Project::class);

        $isManual = $data['isManual'] ?? false;

        $requiredCreate = true;
        $type = $typeRepository->find($data['type']);
        if (!$fromNomade) {
            $CLRequired = $champLibreRepository->getByTypeAndRequiredCreate($type);
            $msgMissingCL = '';
            foreach ($CLRequired as $CL) {
                if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                    $requiredCreate = false;
                    if (!empty($msgMissingCL)) $msgMissingCL .= ', ';
                    $msgMissingCL .= $CL['label'];
                }
            }
            if (!$requiredCreate) {
                return [
                    'success' => false,
                    'msg' => 'Veuillez renseigner les champs obligatoires : ' . $msgMissingCL
                ];
            }
        }
        $utilisateur = $data['demandeur'] instanceof Utilisateur ? $data['demandeur'] : $utilisateurRepository->find($data['demandeur']);
        $date = new DateTime('now');
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $destination = $emplacementRepository->find($data['destination']);
        $project = isset($data['project']) ? $projectRepository->find($data['project']) : null;
        $receiver = isset($data['demandeReceiver']) ? $utilisateurRepository->find($data['demandeReceiver']) : null;
        $number = $this->uniqueNumberService->create(
            $entityManager,
            Demande::NUMBER_PREFIX,
            Demande::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT
        );

        $expectedAt = $this->formatService->parseDatetime($data['expectedAt'] ?? '');

        $visibleColumns = $utilisateur->getVisibleColumns()[Demande::VISIBLE_COLUMNS_SHOW_FIELD] ?? Demande::DEFAULT_VISIBLE_COLUMNS;

        $demande = new Demande();
        $demande
            ->setStatut($statut)
            ->setUtilisateur($utilisateur)
            ->setCreatedAt($date)
            ->setExpectedAt($expectedAt)
            ->setType($type)
            ->setProject($project)
            ->setDestination($destination)
            ->setNumero($number)
            ->setManual($isManual)
            ->setCommentaire(StringHelper::cleanedComment($data['commentaire'] ?? null))
            ->setReceiver($receiver)
            ->setVisibleColumns($visibleColumns);

        $champLibreService->manageFreeFields($demande, $data, $entityManager);

        // cas où demande directement issue d'une réception
        if (isset($data['reception'])) {
            $reception = $receptionRepository->find(intval($data['reception']));
            $demande->setReception($reception);
            $demande->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER));
            if (isset($data['needPrepa']) && $data['needPrepa']) {
                $entityManager->persist($demande);
                return $this->validateDLAfterCheck($entityManager, $demande);
            }
        }
        return $demande;
    }

    public function checkDLStockAndValidate(EntityManagerInterface $entityManager,
                                            array                  $demandeArray,
                                            bool                   $fromNomade = false,
                                            FreeFieldService       $champLibreService,
                                            bool $flush = true,
                                            bool $simple = false): array
    {
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $settings = $entityManager->getRepository(Setting::class);
        $settingNeedPlanningValidation = $settings->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING);
        $settingMangeDeliveriesWithoutStockQuantity = $settings->getOneParamByLabel(Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY);

        if ($fromNomade) {
            $demande = $this->newDemande($demandeArray, $entityManager, $champLibreService, $fromNomade);
            if ($demande instanceof Demande) {
                /**
                 * Liste des références sous le format :
                 * [
                 *    'barCode' => REF123456789,
                 *    'quantity-to-pick' => 12
                 * ]
                 */
                $references = $demandeArray['references'];
                foreach ($references as $reference) {
                    $referenceArticle = $referenceArticleRepository->findOneBy([
                        'barCode' => $reference['barCode']
                    ]);
                    $this->refArticleDataService->addReferenceToRequest(
                        $reference,
                        $referenceArticle,
                        $demandeArray['demandeur'],
                        true,
                        $entityManager,
                        $demande
                    );
                }
            } else {
                return $demande;
            }
        } else {
            $demande = $demandeRepository->find($demandeArray['demande']);
        }

        if ($demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON) {
            $response = [];
            $response['success'] = true;
            $response['msg'] = '';

            if ($demande->getArticleLines()->count() === 0 && $demande->getReferenceLines()->count() === 0) {
                $response['success'] = false;
                $response['msg'] = "La demande n'a pas d'article";
            }

            // pour réf gérées par articles
            $articleLines = $demande->getArticleLines();

            /** @var DeliveryRequestArticleLine $articleLine */
            foreach ($articleLines as $articleLine) {
                $article = $articleLine->getArticle();
                $statutArticle = $article->getStatut();
                if ($statutArticle?->getCode() !== Article::STATUT_ACTIF) {
                    $response['success'] = false;
                    $response['nomadMessage'] = "Erreur de quantité sur l\'article : {$article->getBarCode()}";
                    $response['msg'] = "Un article de votre demande n'est plus disponible. Assurez vous que chacun des articles soit en statut disponible pour valider votre demande.";
                } else {
                    $refArticle = $articleLine->getArticle()->getArticleFournisseur()->getReferenceArticle();
                    $totalQuantity = $refArticle->getQuantiteDisponible();
                    $treshHold = ($article->getQuantite() > $totalQuantity)
                        ? $totalQuantity
                        : $article->getQuantite();
                    if ($articleLine->getQuantityToPick() > $treshHold) {
                        $response['success'] = false;
                        $response['nomadMessage'] = 'Erreur de quantité sur l\'article : ' . $article->getBarCode();
                        $response['msg'] = "La quantité demandée d'un des articles excède la quantité disponible (" . $treshHold . ").";
                    }
                }
            }

            // pour réf gérées par référence
            /** @var DeliveryRequestReferenceLine $line */
            foreach ($demande->getReferenceLines() as $line) {
                $articleRef = $line->getReference();
                //verification des quantités dispo si ref est gérée par reference OU le paramétrage "ne pas gérer les quantités en stock" n'est pas coché
                $checkQuantity = (
                    $articleRef->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE
                    || !$settingMangeDeliveriesWithoutStockQuantity
                );

                if ($checkQuantity && $line->getQuantityToPick() > $articleRef->getQuantiteDisponible()) {
                    $response['success'] = false;
                    $response['nomadMessage'] = "Erreur de quantité sur l'article : " . $articleRef->getBarCode();
                    $response['msg'] = "La quantité demandée d'un des articles excède la quantité disponible (" . $articleRef->getQuantiteDisponible() . ").";
                }
            }
            if ($response['success'] || ($settingNeedPlanningValidation && !$fromNomade)) {
                $entityManager->persist($demande);
                $response = $this->validateDLAfterCheck($entityManager, $demande, $fromNomade, $simple, $flush, $settingNeedPlanningValidation);
            }
        } else {
            $response['entete'] = $this->templating->render('demande/demande-show-header.html.twig', [
                'demande' => $demande,
                'modifiable' => $demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
                'showDetails' => $this->createHeaderDetailsConfig($demande)
            ]);
            $response['msg'] = 'Votre ' . mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' a bien été validée';
            $response['demande'] = $demande;
        }
        return $response;
    }

    public function validateDLAfterCheck(EntityManagerInterface $entityManager,
                                         Demande                $demande,
                                         bool                   $fromNomade = false,
                                         bool                   $simpleValidation = false,
                                         bool                   $flush = true,
                                         bool                   $settingNeedPlanningValidation = true,
                                         array                  $options = []): array
    {
        $response = [];
        $response['success'] = true;
        $response['msg'] = '';
        $statutRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $date = new DateTime('now');

        $preparedUponValidation = $this->treatSetting_preparedUponValidation($entityManager, $demande);

        $preparation = new Preparation();
        $preparation
            ->setExpectedAt($demande->getExpectedAt())
            ->setNumero($this->preparationsManager->generateNumber($date, $entityManager))
            ->setDate($date);


        if(!$demande->getValidatedAt()) {
            $demande->setValidatedAt($date);
        }

        $preparationStatus = $statutRepository->findOneByCategorieNameAndStatutCode(
            Preparation::CATEGORIE,
            !$settingNeedPlanningValidation ? Preparation::STATUT_A_TRAITER : Preparation::STATUT_VALIDATED,
        );

        $preparation->setStatut($preparationStatus);

        $demande->addPreparation($preparation);
        $entityManager->persist($preparation);


        $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $demande->setStatut($statutD);

        if ($settingNeedPlanningValidation) {
            $preparation->setPlanned(true);
        }
        $refArticleToUpdateQuantities = [];
        $this->persistPreparationLine($entityManager, $preparation, !$settingNeedPlanningValidation, $refArticleToUpdateQuantities, $date);

        $sendNotification = $options['sendNotification']??true;
        try {
            if ($flush) $entityManager->flush();
            if ($demande->getType()->isNotificationsEnabled()
                && !$demande->isManual()
                && $sendNotification
                && !$settingRepository->getOneParamByLabel(Setting::SET_PREPARED_UPON_DELIVERY_VALIDATION)) {
                $this->notificationService->toTreat($preparation);
            }
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            $response['success'] = false;
            $response['msg'] = 'Une autre préparation est en cours de création, veuillez réessayer.';
            return $response;
        }
        if (!$settingNeedPlanningValidation) {
            foreach ($refArticleToUpdateQuantities as $refArticle) {
                $this->refArticleDataService->updateRefArticleQuantities($entityManager, $refArticle);
            }
        }

        if (!$simpleValidation && ($demande->getType()->getSendMailRequester() || $demande->getType()->getSendMailReceiver())) {
            $to = [];
            if ($demande->getType()->getSendMailRequester()) {
                $to[] = $demande->getUtilisateur();
            }
            if ($demande->getType()->getSendMailReceiver() && $demande->getReceiver()) {
                $to[] = $demande->getReceiver();
            }

            $nowDate = new DateTime('now');
            $this->mailerService->sendMail(
                'FOLLOW GT // Validation d\'une demande vous concernant',
                $this->templating->render('mails/contents/mailDemandeLivraisonValidate.html.twig', [
                    'demande' => $demande,
                    'title' => 'La '  . mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' ' . $demande->getNumero() . ' de type '
                        . $demande->getType()->getLabel()
                        . ' a bien été validée le '
                        . $nowDate->format('d/m/Y \à H:i')
                        . '.',
                ]),
                $to
            );
        }

        if ($flush) {
            $entityManager->flush();
        }
        if (!$simpleValidation && !$fromNomade) {
            $response['entete'] = $this->templating->render('demande/demande-show-header.html.twig', [
                'demande' => $demande,
                'modifiable' => $demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
                'showDetails' => $this->createHeaderDetailsConfig($demande)
            ]);
            $response['msg'] = 'Votre ' . mb_strtolower($this->translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' a bien été validée';
            $response['demande'] = $demande;
        }

        if($preparedUponValidation) {
            foreach($preparation->getArticleLines() as $articleLine) {
                $articleLine->setPickedQuantity($articleLine->getQuantityToPick());
            }

            foreach($preparation->getReferenceLines() as $referenceLine) {
                $referenceLine->setPickedQuantity($referenceLine->getQuantityToPick());
            }

            $dateEnd = new DateTime('now');
            $user = $this->security->getUser();
            if($preparation->getArticleLines()->count()) {
                $locationEndPrepa = $preparation->getArticleLines()->first()->getArticle()->getEmplacement();
            } else if($preparation->getReferenceLines()->count()) {
                $locationEndPrepa = $preparation->getReferenceLines()->first()->getReference()->getEmplacement();
            } else {
                throw new \RuntimeException("Invalid state");
            }

            $livraison = $this->livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);

            $this->preparationsManager->treatPreparation($preparation, $user, $locationEndPrepa);
            $this->preparationsManager->closePreparationMouvement($preparation, $dateEnd, $locationEndPrepa);

            $entityManager->flush();
            $this->preparationsManager->handlePreparationTreatMovements($entityManager, $preparation, $livraison, $locationEndPrepa, $user);
            $this->preparationsManager->updateRefArticlesQuantities($preparation);
            $response['entete'] = $this->templating->render('demande/demande-show-header.html.twig', [
                'demande' => $demande,
                'modifiable' => false,
                'showDetails' => $this->createHeaderDetailsConfig($demande)
            ]);
            $entityManager->flush();
            if ($livraison->getDemande()->getType()->isNotificationsEnabled()) {
                $this->notificationService->toTreat($livraison);
            }
        }

        return $response;
    }

    public function createHeaderDetailsConfig(Demande $demande): array
    {
        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $demande,
            ['type' => $demande->getType()],
        );

        $config = [
            ['label' => 'Statut', 'value' => $this->stringService->mbUcfirst($this->formatService->status($demande->getStatut()))],
            ['label' => 'Demandeur', 'value' => $this->formatService->deliveryRequester($demande)],
            ['label' => 'Destinataire', 'value' => $this->formatService->user($demande->getReceiver())],
            ['label' => 'Destination', 'value' => $this->formatService->location($demande->getDestination())],
            ['label' => 'Date de la demande', 'value' => $this->formatService->datetime($demande->getCreatedAt())],
            ['label' => 'Date de validation', 'value' => $this->formatService->datetime($demande->getValidatedAt())],
            ['label' => 'Type', 'value' => $this->formatService->type($demande->getType())],
            [
                'label' => 'Date attendue',
                'value' => $this->formatService->date($demande->getExpectedAt()),
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_EXPECTED_AT]
            ],
            [
                'label' => $this->translation->translate('Référentiel', 'Projet', 'Projet', false),
                'value' => $this->formatService->project($demande?->getProject()) ?? '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_DELIVERY_REQUEST_PROJECT]
            ],
        ];

        $configFiltered = $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_DEMANDE);
        return array_merge(
            $configFiltered,
            $freeFieldArray,
            [
                [
                    'label' => 'Commentaire',
                    'value' => $demande->getCommentaire() ?? '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }

    public function managePreRemoveDeliveryRequest(Demande $demande, EntityManagerInterface $entityManager)
    {
        foreach ($demande->getArticleLines() as $articleLine) {
            $entityManager->remove($articleLine);
        }
        foreach ($demande->getReferenceLines() as $ligneArticle) {
            $entityManager->remove($ligneArticle);
        }
    }

    public function createArticleLine(Article $article,
                                      Demande $request,
                                      array $options = []): DeliveryRequestArticleLine {
        $quantityToPick = $options['quantityToPick'] ?? 0;
        $pickedQuantity = $options['pickedQuantity'] ?? 0;
        $targetLocationPicking = $options['targetLocationPicking'] ?? null;
        $pack = $options['pack']
            ?? $article->getCurrentLogisticUnit(); // by default, we copy of the current logistic unit in line;

        $articleLine = new DeliveryRequestArticleLine();
        $articleLine
            ->setQuantityToPick($quantityToPick)
            ->setPickedQuantity($pickedQuantity)
            ->setTargetLocationPicking($targetLocationPicking)
            ->setPack($pack)
            ->setArticle($article)
            ->setRequest($request);

        return $articleLine;
    }

    public function getDataForReferencesDatatable($params = null)
    {
        $demande = $this->entityManager->find(Demande::class, $params);
        $referenceLines = $demande->getReferenceLines();

        $rows = [];
        /** @var DeliveryRequestArticleLine[] $referenceLine */
        foreach ($referenceLines as $referenceLine) {
            $rows[] = $this->dataRowReference($referenceLine);
        }

        return [
            'data' => $rows,
            'recordsTotal' => count($rows),
        ];
    }

    public function dataRowReference(DeliveryRequestReferenceLine $referenceArticle)
    {
        return [
            'reference' => $referenceArticle->getReference()->getReference(),
            'libelle' => $referenceArticle->getReference()->getLibelle(),
            'quantity' => $referenceArticle->getQuantityToPick(),

        ];
    }

    public function getVisibleColumnsConfig(EntityManagerInterface $manager, Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['deliveryRequest'];
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $freeFields = $freeFieldRepository->findByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_LIVRAISON, CategorieCL::DEMANDE_LIVRAISON);

        $columns = [
            ['name' => 'actions', 'orderable' => false, 'alwaysVisible' => true, 'class' => 'noVis', 'width' => '10px'],
            ['name' => 'pairing', 'class' => 'pairing-row', 'alwaysVisible' => true, 'orderable' => false],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Date de validation', 'name' => 'validatedAt'],
            ['title' => 'Demandeur', 'name' => 'requester'],
            ['title' => 'Destinataire', 'name' => 'receiver'],
            ['title' => 'Numéro', 'name' => 'number'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Date attendue', 'name' => 'expectedAt'],
            ['title' => $this->translation->translate('Référentiel', 'Projet', 'Projet', false), 'name' => 'project'],
            ['title' => 'Destination', 'name' => 'destination'],
            ['title' => 'Commentaire', 'name' => 'comment', 'orderable' => false],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function treatSetting_manageDeliveryWithoutStockQuantity(EntityManagerInterface       $entityManager,
                                                                    Preparation                  $preparation,
                                                                    DeliveryRequestReferenceLine $referenceLine,
                                                                    Emplacement                  $locationReception,
                                                                    DateTime                     $date,
                                                                    array                        &$createdBarcodes): void {
        $request = $preparation->getDemande();
        $reference = $referenceLine->getReference();

        if ($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $supplierArticle = $reference->getArticlesFournisseur()->first() ?: null;
            if ($supplierArticle) {
                $article = $this->articleService->newArticle($entityManager, [
                    'articleFournisseur' => $supplierArticle->getId(),
                    'conform' => true,
                    'quantite' => $referenceLine->getQuantityToPick(),
                    'emplacement' => $locationReception,
                    'statut' => Article::STATUT_ACTIF,
                    'refArticle' => $reference
                ], ["excludeBarcodes" => $createdBarcodes]);

                $createdBarcodes[] = $article->getBarcode();

                $articleLine = new PreparationOrderArticleLine();
                $articleLine
                    ->setQuantityToPick($article->getQuantite())
                    ->setArticle($article)
                    ->setAutoGenerated(true)
                    ->setPreparation($preparation)
                    ->setDeliveryRequestReferenceLine($referenceLine);
                $entityManager->persist($articleLine);

                $stockMovement = $this->stockMovementService->createMouvementStock(
                    $request->getUtilisateur(),
                    null,
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_ENTREE,
                    [
                        "from" => $request,
                        "date" => $date,
                        "locationTo" => $article->getEmplacement()
                    ]
                );

                $trackingTaking = $this->trackingMovementService->createTrackingMovement(
                    $article->getBarCode(),
                    $article->getEmplacement(),
                    $request->getUtilisateur(),
                    $date,
                    false,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        "quantity" => $article->getQuantite(),
                        "refOrArticle" => $article,
                        "from" => $request,
                        "mouvementStock" => $stockMovement
                    ]
                );

                $entityManager->persist($stockMovement);
                $entityManager->persist($trackingTaking);
                $entityManager->persist($articleLine);
            }
        }
    }

    public function treatSetting_preparedUponValidation(EntityManagerInterface $entityManager, Demande $request): bool {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $preparedUponValidationSetting = $settingRepository->getOneParamByLabel(Setting::SET_PREPARED_UPON_DELIVERY_VALIDATION);
        $defaultLocationReceptionSetting = $settingRepository->getOneParamByLabel(Setting::DEFAULT_LOCATION_RECEPTION);

        $defaultLocationReception = $defaultLocationReceptionSetting
            ? $locationRepository->find($defaultLocationReceptionSetting)
            : null;
        $manageDeliveryWithoutStockQuantitySetting = $defaultLocationReception && $settingRepository->getOneParamByLabel(Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY);

        if($preparedUponValidationSetting) {
            $locations = [];
            foreach($request->getArticleLines() as $article) {
                $locations[$article->getArticle()->getEmplacement()->getId()] = true;
            }

            foreach($request->getReferenceLines() as $referenceLine) {
                $reference = $referenceLine->getReference();
                if ($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE && $reference->getEmplacement()) {
                    $locations[$reference->getEmplacement()->getId()] = true;
                }
                else if ($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
                    if ($manageDeliveryWithoutStockQuantitySetting) {
                        $locations[$defaultLocationReception->getId()] = true;
                    }
                    else {
                        $preparedUponValidation = false;
                        break;
                    }
                }
            }

            $preparedUponValidation = $preparedUponValidation ?? (count($locations) === 1);
        } else {
            $preparedUponValidation = false;
        }

        return $preparedUponValidation;
    }

    private function persistPreparationLine(EntityManagerInterface $entityManager,
                                            Preparation            $preparation,
                                            bool                   $needsQuantitiesCheck,
                                            array                  &$refArticleToUpdateQuantities,
                                            DateTime               $date): void {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $defaultLocationReceptionSetting = $settingRepository->getOneParamByLabel(Setting::DEFAULT_LOCATION_RECEPTION);
        $defaultLocationReception = $defaultLocationReceptionSetting
            ? $locationRepository->find($defaultLocationReceptionSetting)
            : null;
        $manageDeliveryWithoutStockQuantitySetting = $defaultLocationReception && $settingRepository->getOneParamByLabel(Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY);

        $request = $preparation->getDemande();

        // modification du statut articles => en transit
        $statutArticleIntransit = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $deliveryRequestArticleLines = $request->getArticleLines();
        foreach ($deliveryRequestArticleLines as $requestArticleLine) {
            $article = $requestArticleLine->getArticle();
            $article->setStatut($statutArticleIntransit);

            $preparationArticleLine = $requestArticleLine->createPreparationOrderLine();
            $preparationArticleLine
                ->setPreparation($preparation)
                ->setPickedQuantity($requestArticleLine->getPickedQuantity());
            $entityManager->persist($preparationArticleLine);
        }

        $deliveryRequestReferenceLines = $request->getReferenceLines();
        $refArticleToUpdateQuantities = [];
        $createdBarcodes = [];
        foreach ($deliveryRequestReferenceLines as $requestReferenceLine) {
            $referenceArticle = $requestReferenceLine->getReference();

            // if we have $manageDeliveryWithoutStockQuantitySetting === true => we add to preparation
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE
                || !$manageDeliveryWithoutStockQuantitySetting) {
                $lignesArticlePreparation = new PreparationOrderReferenceLine();
                $lignesArticlePreparation
                    ->setPickedQuantity($requestReferenceLine->getPickedQuantity())
                    ->setQuantityToPick($requestReferenceLine->getQuantityToPick())
                    ->setTargetLocationPicking($requestReferenceLine->getTargetLocationPicking())
                    ->setReference($referenceArticle)
                    ->setPreparation($preparation)
                    ->setDeliveryRequestReferenceLine($requestReferenceLine);
                $entityManager->persist($lignesArticlePreparation);
                if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE && $needsQuantitiesCheck) {
                    $referenceArticle->setQuantiteReservee(($referenceArticle->getQuantiteReservee() ?? 0) + $requestReferenceLine->getQuantityToPick());
                }
                else {
                    $refArticleToUpdateQuantities[] = $referenceArticle;
                }
                $preparation->addReferenceLine($lignesArticlePreparation);
            }
            else {
                // create PreparationOrderArticleLine
                $this->treatSetting_manageDeliveryWithoutStockQuantity($entityManager, $preparation, $requestReferenceLine, $defaultLocationReception, $date, $createdBarcodes);
            }
        }
    }

    public function getVisibleColumnsTableArticleConfig(EntityManagerInterface $entityManager,
                                                        $request,
                                                        bool $editMode = false): array {
        $columnsVisible = $request->getVisibleColumns();
        if ($columnsVisible === null) {
            $request->setVisibleColumns(Demande::DEFAULT_VISIBLE_COLUMNS);
            $entityManager->flush();
            $columnsVisible = $request->getVisibleColumns();
        }

        $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFieldsParam::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $fieldParams = $subLineFieldsParamRepository->getByEntity(SubLineFieldsParam::ENTITY_CODE_DEMANDE_REF_ARTICLE);
        $isProjectDisplayed = $fieldParams[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT]['displayed'] ?? false;
        $isProjectRequired = $fieldParams[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT]['required'] ?? false;
        $isCommentDisplayed = $fieldParams[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT]['displayed'] ?? false;
        $isCommentRequired = $fieldParams[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT]['required'] ?? false;
        $isTargetLocationPickingDisplayed = $settingRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION);

        $columns = [
            ["name" => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Référence', 'required' => $editMode, 'name' => 'reference', 'alwaysVisible' => true],
            ['title' => 'Code barre', 'name' => 'barcode', 'alwaysVisible' => false],
            ['title' => 'Libellé', 'name' => 'label', 'alwaysVisible' => false],
            ['title' => 'Article', 'required' => $editMode, 'name' => 'article', 'removeColumn' => !$editMode, 'alwaysVisible' => true],
            ['title' => 'Quantité', 'required' => $editMode, 'name' => 'quantityToPick', 'alwaysVisible' => true],
            ['title' => 'Emplacement', 'name' => 'location', 'alwaysVisible' => false],
            ["name" => "error", "title" => "Erreur", "visible" => false, 'removeColumn' => $editMode, 'alwaysVisible' => true, 'class' => 'noVis'],

            // TODO WIIS-9646
            ['title' => 'Emplacement cible picking', 'name' => 'targetLocationPicking', 'alwaysVisible' => true, 'removeColumn' => !$isTargetLocationPickingDisplayed],
            ['title' => $this->translation->translate('Référentiel', 'Projet', 'Projet', false), 'required' => $editMode && $isProjectRequired, 'name' => 'project', 'alwaysVisible' => true, 'removeColumn' => !$isProjectDisplayed, 'data' => 'project'],
            ['title' => 'Commentaire', 'required' => $editMode && $isCommentRequired, 'data' => 'comment', 'name' => 'comment', 'alwaysVisible' => true, 'removeColumn' => !$isCommentDisplayed],
        ];

        $columns = Stream::from($columns)
            ->filter(fn (array $column) => !($column['removeColumn'] ?? false)) // display column if removeColumn not defined
            ->map(function (array $column) {
                unset($column['removeColumn']);
                return $column;
            })
            ->values();

        return $this->visibleColumnService->getArrayConfig($columns, [], $columnsVisible);
    }
}
