<?php


namespace App\Service;


use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
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
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Service\TranslationService;
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
            'createdAt' => FormatHelper::datetime($demande->getCreatedAt()),
            'validatedAt' => FormatHelper::datetime($demande->getValidatedAt()),
            'destination' => FormatHelper::location($demande->getDestination()),
            'comment' => $demande->getCommentaire(),
            'requester' => FormatHelper::deliveryRequester($demande),
            'number' => $demande->getNumero() ?? '',
            'status' => FormatHelper::status($demande->getStatut()),
            'type' => FormatHelper::type($demande->getType()),
            'expectedAt' => FormatHelper::date($demande->getExpectedAt()),
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

    public function parseRequestForCard(Demande     $demande,
                                        DateService $dateService,
                                        array       $averageRequestTimesByType): array
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
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page d\'état actuel de la demande de livraison',
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
        $project = $projectRepository->find(isset($data['project']) ? intval($data['project']) : -1);
        $number = $this->uniqueNumberService->create(
            $entityManager,
            Demande::NUMBER_PREFIX,
            Demande::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT
        );

        $expectedAt = $this->formatService->parseDatetime($data['expectedAt'] ?? '');

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
            ->setCommentaire(StringHelper::cleanedComment($data['commentaire']));

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
        $needsQuantitiesCheck = !$settings->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING);

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
                        $demande,
                        $champLibreService
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
                if ($line->getQuantityToPick() > $articleRef->getQuantiteDisponible()) {
                    $response['success'] = false;
                    $response['nomadMessage'] = 'Erreur de quantité sur l\'article : ' . $articleRef->getBarCode();
                    $response['msg'] = "La quantité demandée d'un des articles excède la quantité disponible (" . $articleRef->getQuantiteDisponible() . ").";
                }
            }
            if ($response['success'] || (!$needsQuantitiesCheck && !$fromNomade)) {
                $entityManager->persist($demande);
                $response = $this->validateDLAfterCheck($entityManager, $demande, $fromNomade, $simple, $flush, $needsQuantitiesCheck);
            }
        } else {
            $response['entete'] = $this->templating->render('demande/demande-show-header.html.twig', [
                'demande' => $demande,
                'modifiable' => $demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
                'showDetails' => $this->createHeaderDetailsConfig($demande)
            ]);
            $response['msg'] = 'Votre demande de livraison a bien été validée';
            $response['demande'] = $demande;
        }
        return $response;
    }

    public function validateDLAfterCheck(EntityManagerInterface $entityManager,
                                         Demande $demande,
                                         bool $fromNomade = false,
                                         bool $simpleValidation = false,
                                         bool $flush = true,
                                         bool $needsQuantitiesCheck = true,
                                         array $options = []): array
    {
        $response = [];
        $response['success'] = true;
        $response['msg'] = '';
        $statutRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $date = new DateTime('now');

        $preparedUponValidationSetting = $settingRepository->getOneParamByLabel(Setting::SET_PREPARED_UPON_DELIVERY_VALIDATION);
        if($preparedUponValidationSetting) {
            $locations = [];
            foreach($demande->getArticleLines() as $article) {
                $locations[$article->getArticle()->getEmplacement()->getId()] = true;
            }

            foreach($demande->getReferenceLines() as $reference) {
                if($reference->getReference()->getEmplacement()) {
                    $locations[$reference->getReference()->getEmplacement()->getId()] = true;
                }
            }

            $preparedUponValidation = count($locations) === 1;
        } else {
            $preparedUponValidation = false;
        }

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
            $needsQuantitiesCheck ? Preparation::STATUT_A_TRAITER : Preparation::STATUT_VALIDATED,
        );

        $preparation->setStatut($preparationStatus);

        $demande->addPreparation($preparation);
        $entityManager->persist($preparation);


        $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $demande->setStatut($statutD);

        if (!$needsQuantitiesCheck) {
            $preparation->setPlanned(true);
        }

        // modification du statut articles => en transit
        $statutArticleIntransit = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        $requestLines = $demande->getArticleLines();
        foreach ($requestLines as $requestArticleLine) {
            $article = $requestArticleLine->getArticle();
            $article->setStatut($statutArticleIntransit);

            $preparationArticleLine = $requestArticleLine->createPreparationOrderLine();
            $preparationArticleLine
                ->setPreparation($preparation)
                ->setPickedQuantity($requestArticleLine->getPickedQuantity());
            $entityManager->persist($preparationArticleLine);
        }

        $lignesArticles = $demande->getReferenceLines();
        $refArticleToUpdateQuantities = [];
        foreach ($lignesArticles as $ligneArticle) {
            $referenceArticle = $ligneArticle->getReference();
            $lignesArticlePreparation = new PreparationOrderReferenceLine();
            $lignesArticlePreparation
                ->setPickedQuantity($ligneArticle->getPickedQuantity())
                ->setQuantityToPick($ligneArticle->getQuantityToPick())
                ->setTargetLocationPicking($ligneArticle->getTargetLocationPicking())
                ->setReference($referenceArticle)
                ->setPreparation($preparation);
            $entityManager->persist($lignesArticlePreparation);
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE && $needsQuantitiesCheck) {
                $referenceArticle->setQuantiteReservee(($referenceArticle->getQuantiteReservee() ?? 0) + $ligneArticle->getQuantityToPick());
            } else {
                $refArticleToUpdateQuantities[] = $referenceArticle;
            }
            $preparation->addReferenceLine($lignesArticlePreparation);
        }

        $sendNotification = $options['sendNotification']??true;
        try {
            if ($flush) $entityManager->flush();
            if ($demande->getType()->isNotificationsEnabled()
                && !$demande->isManual()
                && $sendNotification) {
                $this->notificationService->toTreat($preparation);
            }
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            $response['success'] = false;
            $response['msg'] = 'Une autre préparation est en cours de création, veuillez réessayer.';
            return $response;
        }
        if ($needsQuantitiesCheck) {
            foreach ($refArticleToUpdateQuantities as $refArticle) {
                $this->refArticleDataService->updateRefArticleQuantities($entityManager, $refArticle);
            }
        }

        if (!$simpleValidation && $demande->getType()->getSendMail()) {
            $nowDate = new DateTime('now');
            $this->mailerService->sendMail(
                'FOLLOW GT // Validation d\'une demande vous concernant',
                $this->templating->render('mails/contents/mailDemandeLivraisonValidate.html.twig', [
                    'demande' => $demande,
                    'title' => 'Votre demande de livraison ' . $demande->getNumero() . ' de type '
                        . $demande->getType()->getLabel()
                        . ' a bien été validée le '
                        . $nowDate->format('d/m/Y \à H:i')
                        . '.',
                ]),
                $demande->getUtilisateur()
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
            $response['msg'] = 'Votre demande de livraison a bien été validée';
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
            if($demande->getArticleLines()->count()) {
                $locationEndPrepa = $demande->getArticleLines()->first()->getArticle()->getEmplacement();
            } else if($demande->getReferenceLines()->count()) {
                $locationEndPrepa = $demande->getReferenceLines()->first()->getReference()->getEmplacement();
            } else {
                throw new \RuntimeException("Invalid state");
            }

            $livraison = $this->livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);

            $this->preparationsManager->treatPreparation($preparation, $user, $locationEndPrepa, []);
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
                'label' => 'Projet',
                'value' => $this->formatService->project($demande?->getProject()) ?? '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_PROJECT]
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
            ['title' => 'Numéro', 'name' => 'number'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Date attendue', 'name' => 'expectedAt'],
            ['title' => 'Projet', 'name' => 'project'],
            ['title' => 'Destination', 'name' => 'destination'],
            ['title' => 'Commentaire', 'name' => 'comment', 'orderable' => false],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

}
