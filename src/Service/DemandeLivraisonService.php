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
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\PreparationOrder\Preparation;
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
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;

class DemandeLivraisonService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public StringService $stringService;

    /** @Required */
    public RefArticleDataService $refArticleDataService;

    /** @Required */
    public MailerService $mailerService;

    /** @Required */
    public TranslationService $translation;

    /** @Required */
    public PreparationsManagerService $preparationsManager;

    /** @Required */
    public FreeFieldService $freeFieldService;

    /** @Required */
    public FieldsParamService $fieldsParamService;

    /** @Required */
    public NotificationService $notificationService;

    /** @Required */
    public VisibleColumnService $visibleColumnService;

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

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

        $requestStatus = $demande->getStatut() ? $demande->getStatut()->getNom() : '';
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
        $number = $this->uniqueNumberService->create(
            $entityManager,
            Demande::NUMBER_PREFIX,
            Demande::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT
        );

        $expectedAt = FormatHelper::parseDatetime($data['expectedAt'] ?? '');

        $demande = new Demande();
        $demande
            ->setStatut($statut)
            ->setUtilisateur($utilisateur)
            ->setCreatedAt($date)
            ->setExpectedAt($expectedAt)
            ->setType($type)
            ->setDestination($destination)
            ->setNumero($number)
            ->setManual($isManual)
            ->setCommentaire($data['commentaire']);

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
        if ($demande->getStatut() && $demande->getStatut()->getNom() === Demande::STATUT_BROUILLON) {
            $response = [];
            $response['success'] = true;
            $response['msg'] = '';
            // pour réf gérées par articles
            $articleLines = $demande->getArticleLines();
            /** @var DeliveryRequestArticleLine $articleLine */
            foreach ($articleLines as $articleLine) {
                $article = $articleLine->getArticle();
                $statutArticle = $article->getStatut();
                if (isset($statutArticle)
                    && $statutArticle->getNom() !== Article::STATUT_ACTIF) {
                    $response['success'] = false;
                    $response['nomadMessage'] = 'Erreur de quantité sur l\'article : ' . $articleLine->getBarCode();
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
                'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
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

        // Creation d'une nouvelle preparation basée sur une selection de demandes
        $preparation = new Preparation();
        $date = new DateTime('now');

        $preparationNumber = $this->preparationsManager->generateNumber($date, $entityManager);

        $preparation
            ->setExpectedAt($demande->getExpectedAt())
            ->setNumero($preparationNumber)
            ->setDate($date);

        if(!$demande->getValidatedAt()) {
            $demande->setValidatedAt($date);
        }

        $statutP = $needsQuantitiesCheck
            ? $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER)
            : $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_VALIDATED);
        $preparation->setStatut($statutP);
        $entityManager->persist($preparation);
        $demande->addPreparation($preparation);
        $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $demande->setStatut($statutD);

        if (!$needsQuantitiesCheck) {
            $preparation->setPlanned(true);
        }

        // modification du statut articles => en transit
        $articles = $demande->getArticleLines();
        $statutArticleIntransit = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
        foreach ($articles as $article) {
            $article->getArticle()->setStatut($statutArticleIntransit);
            $ligneArticlePreparation = new PreparationOrderArticleLine();
            $ligneArticlePreparation
                ->setPickedQuantity($article->getPickedQuantity())
                ->setQuantityToPick($article->getQuantityToPick())
                ->setTargetLocationPicking($article->getTargetLocationPicking())
                ->setArticle($article->getArticle())
                ->setPreparation($preparation);
            $entityManager->persist($ligneArticlePreparation);
            $preparation->addArticleLine($ligneArticlePreparation);
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
                'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                'showDetails' => $this->createHeaderDetailsConfig($demande)
            ]);
            $response['msg'] = 'Votre demande de livraison a bien été validée';
            $response['demande'] = $demande;
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
            ['label' => 'Statut', 'value' => $this->stringService->mbUcfirst(FormatHelper::status($demande->getStatut()))],
            ['label' => 'Demandeur', 'value' => FormatHelper::deliveryRequester($demande)],
            ['label' => 'Destination', 'value' => FormatHelper::location($demande->getDestination())],
            ['label' => 'Date de la demande', 'value' => FormatHelper::datetime($demande->getCreatedAt())],
            ['label' => 'Date de validation', 'value' => FormatHelper::datetime($demande->getValidatedAt())],
            ['label' => 'Type', 'value' => FormatHelper::type($demande->getType())],
            [
                'label' => 'Date attendue',
                'value' => FormatHelper::date($demande->getExpectedAt()),
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_EXPECTED_AT]
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
                                      int     $quantityToPick = 0,
                                      int     $pickedQuantity = 0): DeliveryRequestArticleLine
    {

        $articleLine = new DeliveryRequestArticleLine();
        $articleLine
            ->setQuantityToPick($quantityToPick)
            ->setPickedQuantity($pickedQuantity)
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
        $FFCategory = $manager->getRepository(CategorieCL::class)->findOneBy(['label' => CategorieCL::DEMANDE_LIVRAISON]);
        $freeFields = $manager->getRepository(FreeField::class)->getByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_LIVRAISON, $FFCategory);

        $columns = [
            ['name' => 'actions', 'orderable' => false, 'alwaysVisible' => true, 'class' => 'noVis', 'width' => '10px'],
            ['name' => 'pairing', 'class' => 'pairing-row', 'alwaysVisible' => true, 'orderable' => false],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Date de validation', 'name' => 'validatedAt'],
            ['title' => 'Demandeur', 'name' => 'requester'],
            ['title' => 'Numéro', 'name' => 'number'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Destination', 'name' => 'destination'],
            ['title' => 'Commentaire', 'name' => 'comment', 'orderable' => false],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

}
