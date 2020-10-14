<?php


namespace App\Service;


use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\LigneArticlePreparation;
use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\PrefixeNomDemandeRepository;
use App\Repository\ReceptionRepository;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DemandeLivraisonService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var PrefixeNomDemandeRepository
     */
    private $prefixeNomDemandeRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var Utilisateur
     */
    private $user;

    private $entityManager;
    private $stringService;
    private $refArticleDataService;
    private $mailerService;
    private $translator;
    private $preparationsManager;
    private $freeFieldService;
    private $userService;
    private $appURL;

    public function __construct(ReceptionRepository $receptionRepository,
                                PrefixeNomDemandeRepository $prefixeNomDemandeRepository,
                                FreeFieldService $freeFieldService,
                                TokenStorageInterface $tokenStorage,
                                StringService $stringService,
                                PreparationsManagerService $preparationsManager,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                TranslatorInterface $translator,
                                MailerService $mailerService,
                                RefArticleDataService $refArticleDataService,
                                UserService $userService,
                                string $appURL,
                                Twig_Environment $templating)
    {
        $this->receptionRepository = $receptionRepository;
        $this->preparationsManager = $preparationsManager;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->templating = $templating;
        $this->stringService = $stringService;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->translator = $translator;
        $this->mailerService = $mailerService;
        $this->refArticleDataService = $refArticleDataService;
        $this->userService =$userService;
        $this->freeFieldService = $freeFieldService;
        $this->appURL = $appURL;
    }

    public function getDataForDatatable($params = null, $statusFilter = null, $receptionFilter = null)
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
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DEM_LIVRAISON, $this->user);
        }
        $queryResult = $demandeRepository->findByParamsAndFilters($params, $filters, $receptionFilter);

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

    public function dataRowDemande(Demande $demande)
    {
        $idDemande = $demande->getId();
        $url = $this->router->generate('demande_show', ['id' => $idDemande]);
        $row =
            [
                'Date' => $demande->getDate() ? $demande->getDate()->format('d/m/Y') : '',
                'Demandeur' => $demande->getUtilisateur() ? $demande->getUtilisateur()->getUsername() : '',
                'Numéro' => $demande->getNumero() ?? '',
                'Statut' => $demande->getStatut() ? $demande->getStatut()->getNom() : '',
                'Type' => $demande->getType() ? $demande->getType()->getLabel() : '',
                'Actions' => $this->templating->render('demande/datatableDemandeRow.html.twig',
                    [
                        'idDemande' => $idDemande,
                        'url' => $url,
                    ]
                ),
            ];
        return $row;
    }

    /**
     * @param Demande $demande
     * @param DateService $dateService
     * @param array $averageRequestTimesByType
     * @return array
     * @throws Exception
     */
    public function parseRequestForCard(Demande $demande,
                                        DateService $dateService,
                                        array $averageRequestTimesByType) {
        $hasRightToSeeRequest = $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR);
        $hasRightToSeePrepaOrders = $this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA);
        $hasRightToSeeDeliveryOrders = $this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR);

        $requestStatus = $demande->getStatut() ? $demande->getStatut()->getNom() : '';
        $demandeType = $demande->getType() ? $demande->getType()->getLabel() : '';

        if ($requestStatus === Demande::STATUT_A_TRAITER && $hasRightToSeePrepaOrders && !$demande->getPreparations()->isEmpty()) {
            $href = $this->router->generate('preparation_index', ['demandId' => $demande->getId()]);
        }
        else if (
            (
                $requestStatus === Demande::STATUT_LIVRE_INCOMPLETE ||
                $requestStatus === Demande::STATUT_INCOMPLETE ||
                $requestStatus === Demande::STATUT_PREPARE
            )
            && $hasRightToSeeDeliveryOrders && !$demande->getLivraisons()->isEmpty()
        ) {
            $href = $this->router->generate('livraison_index', ['demandId' => $demande->getId()]);
        }
        else if ($hasRightToSeeRequest) {
            $href = $this->router->generate('demande_show', ['id' => $demande->getId()]);
        }

        $articlesCounter = ($demande->getArticles()->count() + $demande->getLigneArticle()->count());
        $articlePlural = $articlesCounter > 1 ? 's' : '';
        $bodyTitle = $articlesCounter . ' article' . $articlePlural . ' - ' . $demandeType;

        $typeId = $demande->getType() ? $demande->getType()->getId() : null;
        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date de livraison non estimée';
        $today = new DateTime();

        if (isset($averageTime)) {
            $expectedDate = (clone $demande->getDate())
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

        $requestDate = $demande->getDate();
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
            'cardColor' => $requestStatus === Demande::STATUT_BROUILLON ? 'lightGrey' : 'white',
            'bodyColor' => $requestStatus === Demande::STATUT_BROUILLON ? 'white' : 'lightGrey',
            'topRightIcon' => 'fa-box',
            'progress' => $statusesToProgress[$requestStatus] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'emergencyText' => '',
            'progressBarBGColor' => $requestStatus === Demande::STATUT_BROUILLON ? 'white' : 'lightGrey',
        ];
    }

    /**
     * @param $data
     * @param EntityManagerInterface $entityManager
     * @param bool $fromNomade
     * @param FreeFieldService $champLibreService
     * @return Demande|array|JsonResponse
     * @throws NonUniqueResultException
     */
    public function newDemande($data, EntityManagerInterface $entityManager, bool $fromNomade = false, FreeFieldService $champLibreService)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
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
                return new JsonResponse(['success' => false, 'msg' => 'Veuillez renseigner les champs obligatoires : ' . $msgMissingCL]);
            }
        }
        $utilisateur = $data['demandeur'] instanceof Utilisateur ? $data['demandeur'] : $utilisateurRepository->find($data['demandeur']);
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $destination = $emplacementRepository->find($data['destination']);

        $numero = $this->generateNumeroForNewDL($this->entityManager);

        $demande = new Demande();
        $demande
            ->setStatut($statut)
            ->setUtilisateur($utilisateur)
            ->setDate($date)
            ->setType($type)
            ->setDestination($destination)
            ->setNumero($numero)
            ->setCommentaire($data['commentaire']);
        if (!$fromNomade) {
            // enregistrement des champs libres
            $champLibreService->manageFreeFields($demande, $data, $entityManager);
        }
        // cas où demande directement issue d'une réception
        if (isset($data['reception'])) {
            $reception = $this->receptionRepository->find(intval($data['reception']));
            $demande->setReception($reception);
            $demande->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER));
            if (isset($data['needPrepa']) && $data['needPrepa']) {
                $preparation = new Preparation();
                $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                $preparationNumber = $this->preparationsManager->generateNumber($date, $entityManager);
                $preparation
                    ->setNumero($preparationNumber)
                    ->setDate($date);
                $statutP = $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
                $preparation->setStatut($statutP);
                $this->entityManager->persist($preparation);
                $demande->addPreparation($preparation);
            }
        }
        return $demande;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @return string
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function generateNumeroForNewDL(EntityManagerInterface $entityManager)
    {
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $prefixeNomDemandeRepository = $entityManager->getRepository(PrefixeNomDemande::class);

        $prefixeExist = $prefixeNomDemandeRepository->findOneByTypeDemande(PrefixeNomDemande::TYPE_LIVRAISON);
        $prefixe = $prefixeExist ? $prefixeExist->getPrefixe() : '';
        $yearMonth = $date->format('ym');
        $lastNumero = $demandeRepository->getLastNumeroByPrefixeAndDate($prefixe, $yearMonth);
        $lastCpt = !empty($lastNumero) ? ((int)substr($lastNumero, -4, 4)) : 0;
        $i = $lastCpt + 1;
        $cpt = sprintf('%04u', $i);
        return ($prefixe . $yearMonth . $cpt);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param array $demandeArray
     * @param bool $fromNomade
     * @param FreeFieldService $champLibreService
     * @return array
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \App\Exceptions\ArticleNotAvailableException
     * @throws \App\Exceptions\RequestNeedToBeProcessedException
     */
    public function checkDLStockAndValidate(EntityManagerInterface $entityManager,
                                            array $demandeArray,
                                            bool $fromNomade = false,
                                            FreeFieldService $champLibreService): array
    {
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        if ($fromNomade) {
            /**
             * @var Demande $demande
             */
            $demande = $this->newDemande($demandeArray, $entityManager, $fromNomade, $champLibreService);
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
                $this->refArticleDataService->addRefToDemand(
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
            $demande = $demandeRepository->find($demandeArray['demande']);
        }
        $response = [];
        $response['success'] = true;
        $response['message'] = '';
        // pour réf gérées par articles
        $articles = $demande->getArticles();
        foreach ($articles as $article) {
            $statutArticle = $article->getStatut();
            if (isset($statutArticle)
                && $statutArticle->getNom() !== Article::STATUT_ACTIF) {
                $response['success'] = false;
                $response['nomadMessage'] = 'Erreur de quantité sur l\'article : ' . $article->getBarCode();
                $response['message'] = "Un article de votre demande n'est plus disponible. Assurez vous que chacun des articles soit en statut disponible pour valider votre demande.";
            } else {
                $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                $totalQuantity = $refArticle->getQuantiteDisponible();
                $treshHold = ($article->getQuantite() > $totalQuantity)
                    ? $totalQuantity
                    : $article->getQuantite();
                if ($article->getQuantiteAPrelever() > $treshHold) {
                    $response['success'] = false;
                    $response['nomadMessage'] = 'Erreur de quantité sur l\'article : ' . $article->getBarCode();
                    $response['message'] = "La quantité demandée d'un des articles excède la quantité disponible (" . $treshHold . ").";
                }
            }
        }

        // pour réf gérées par référence
        foreach ($demande->getLigneArticle() as $ligne) {
            $articleRef = $ligne->getReference();
            if ($ligne->getQuantite() > $articleRef->getQuantiteDisponible()) {
                $response['success'] = false;
                $response['nomadMessage'] = 'Erreur de quantité sur l\'article : ' . $articleRef->getBarCode();
                $response['message'] = "La quantité demandée d'un des articles excède la quantité disponible (" . $articleRef->getQuantiteDisponible() . ").";
            }
        }
        if ($response['success']) {
            $entityManager->persist($demande);
            $response = $this->validateDLAfterCheck($entityManager, $demande, $fromNomade);
        }
        return $response;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Demande $demande
     * @param bool $fromNomade
     * @return array
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function validateDLAfterCheck(EntityManagerInterface $entityManager,
                                          Demande $demande,
                                          bool $fromNomade = false): array
    {
        $response = [];
        $response['success'] = true;
        $response['message'] = '';
        $statutRepository = $entityManager->getRepository(Statut::class);

        // Creation d'une nouvelle preparation basée sur une selection de demandes
        $preparation = new Preparation();
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));

        $preparationNumber = $this->preparationsManager->generateNumber($date, $entityManager);

        $preparation
            ->setNumero($preparationNumber)
            ->setDate($date);

        $statutP = $statutRepository->findOneByCategorieNameAndStatutCode(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
        $preparation->setStatut($statutP);
        $entityManager->persist($preparation);
        $demande->addPreparation($preparation);
        $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
        $demande->setStatut($statutD);

        // modification du statut articles => en transit
        $articles = $demande->getArticles();
        foreach ($articles as $article) {
            $article->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
            $preparation->addArticle($article);
        }
        $lignesArticles = $demande->getLigneArticle();
        $refArticleToUpdateQuantities = [];
        foreach ($lignesArticles as $ligneArticle) {
            $referenceArticle = $ligneArticle->getReference();
            $lignesArticlePreparation = new LigneArticlePreparation();
            $lignesArticlePreparation
                ->setToSplit($ligneArticle->getToSplit())
                ->setQuantitePrelevee($ligneArticle->getQuantitePrelevee())
                ->setQuantite($ligneArticle->getQuantite())
                ->setReference($referenceArticle)
                ->setPreparation($preparation);
            $entityManager->persist($lignesArticlePreparation);
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle->setQuantiteReservee(($referenceArticle->getQuantiteReservee() ?? 0) + $ligneArticle->getQuantite());
            } else {
                $refArticleToUpdateQuantities[] = $referenceArticle;
            }
            $preparation->addLigneArticlePreparation($lignesArticlePreparation);
        }


        $requestPersisted = false;
        $tryCounter = 0;
        do {
            $tryCounter++;
            try {
                $entityManager->flush();
                $requestPersisted = true;
            }
                /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                if (!$entityManager->isOpen()) {
                    $entityManager = $entityManager->create($entityManager->getConnection(), $entityManager->getConfiguration());
                }
                // recalcul du num
                $number = $this->preparationsManager->generateNumber($preparation->getDate(), $entityManager);
                $preparation->setNumero($number);
            }
        }
        while (!$requestPersisted && $tryCounter < 5);

        if (!$requestPersisted) {
            $response['success'] = false;
            $response['message'] = $response['nomadMessage'] = 'Impossible de créer la préparation, veuillez rééssayer ultérieurement';
            return $response;
        }

        foreach ($refArticleToUpdateQuantities as $refArticle) {
            $this->refArticleDataService->updateRefArticleQuantities($refArticle);
        }

        if ($demande->getType()->getSendMail()) {
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
                $demande->getUtilisateur()->getMainAndSecondaryEmails()
            );
        }
        $entityManager->flush();
        if (!$fromNomade) {
            $response['message'] = $this->templating->render('demande/demande-show-header.html.twig', [
                'demande' => $demande,
                'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                'showDetails' => $this->createHeaderDetailsConfig($demande)
            ]);
            $response['demande'] = $demande;
        }
        $entityManager->flush();
        return $response;
    }

    public function createHeaderDetailsConfig(Demande $demande): array
    {
        $status = $demande->getStatut();
        $requester = $demande->getUtilisateur();
        $destination = $demande->getDestination();
        $date = $demande->getDate();
        $validationDate = $demande->getValidationDate();
        $type = $demande->getType();
        $comment = $demande->getCommentaire();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $demande,
            CategorieCL::DEMANDE_LIVRAISON,
            CategoryType::DEMANDE_LIVRAISON
        );

        return array_merge(
            [
                ['label' => 'Statut', 'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : ''],
                ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
                ['label' => 'Destination', 'value' => $destination ? $destination->getLabel() : ''],
                ['label' => 'Date de la demande', 'value' => $date ? $date->format('d/m/Y') : ''],
                ['label' => 'Date de validation', 'value' => $validationDate ? $validationDate->format('d/m/Y H:i') : ''],
                ['label' => 'Type', 'value' => $type ? $type->getLabel() : '']
            ],
            $freeFieldArray,
            [
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }

    /**
     * @param Demande $demande
     * @param EntityManagerInterface $entityManager
     */
    public function managePreRemoveDeliveryRequest(Demande $demande, EntityManagerInterface $entityManager) {
        foreach ($demande->getArticles() as $article) {
            $article->setDemande(null);
        }
        foreach ($demande->getLigneArticle() as $ligneArticle) {
            $entityManager->remove($ligneArticle);
        }
    }
}
