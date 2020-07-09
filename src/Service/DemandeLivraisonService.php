<?php


namespace App\Service;


use App\Entity\Article;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\LigneArticlePreparation;
use App\Entity\PrefixeNomDemande;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Repository\PrefixeNomDemandeRepository;
use App\Repository\ReceptionRepository;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
    private $valeurChampLibreService;
    private $preparationsManager;

    public function __construct(ReceptionRepository $receptionRepository,
                                PrefixeNomDemandeRepository $prefixeNomDemandeRepository,
                                TokenStorageInterface $tokenStorage,
                                StringService $stringService,
                                PreparationsManagerService $preparationsManager,
                                ValeurChampLibreService $valeurChampLibreService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                TranslatorInterface $translator,
                                MailerService $mailerService,
                                RefArticleDataService $refArticleDataService,
                                Twig_Environment $templating)
    {
        $this->receptionRepository = $receptionRepository;
        $this->preparationsManager = $preparationsManager;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->templating = $templating;
        $this->stringService = $stringService;
        $this->valeurChampLibreService = $valeurChampLibreService;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->translator = $translator;
        $this->mailerService = $mailerService;
        $this->refArticleDataService = $refArticleDataService;
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
     * @param $data
     * @param EntityManagerInterface $entityManager
     * @param bool $fromNomade
     * @return Demande|array|JsonResponse
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function newDemande($data, EntityManagerInterface $entityManager, bool $fromNomade = false)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
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
            ->setdate($date)
            ->setType($type)
            ->setDestination($destination)
            ->setNumero($numero)
            ->setCommentaire($data['commentaire']);
        if (!$fromNomade) {
            // enregistrement des champs libres
            $this->checkAndPersistIfClIsOkay($demande, $data);
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
        $lastCpt = (int)substr($lastNumero, -4, 4);
        $i = $lastCpt + 1;
        $cpt = sprintf('%04u', $i);
        return ($prefixe . $yearMonth . $cpt);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param array $demandeArray
     * @param bool $fromNomade
     * @return array
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NoResultException
     */
    public function checkDLStockAndValidate(EntityManagerInterface $entityManager, array $demandeArray, bool $fromNomade = false): array
    {
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        if ($fromNomade) {
            /**
             * @var Demande $demande
             */
            $demande = $this->newDemande($demandeArray, $entityManager, $fromNomade);
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
                $this->refArticleDataService->addRefToDemand($reference, $referenceArticle, $demandeArray['demandeur'], true, $entityManager, $demande);
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
            $entityManager->flush();
        }
        $response = $response['success'] ? $this->validateDLAfterCheck($entityManager, $demande, $fromNomade) : $response;
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
        $entityManager->flush();

        foreach ($refArticleToUpdateQuantities as $refArticle) {
            $this->refArticleDataService->updateRefArticleQuantities($refArticle);
        }

        if ($demande->getType()->getSendMail()) {
            $nowDate = new DateTime('now');
            $this->mailerService->sendMail(
                'FOLLOW GT // Validation d\'une demande vous concernant',
                $this->templating->render('mails/mailDemandeLivraisonValidate.html.twig', [
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

    /**
     * @param Demande $demande
     * @param array $data
     */
    public function checkAndPersistIfClIsOkay(Demande $demande, array $data)
    {
        $demande->getValeurChampLibre()->clear();
        $keys = array_keys($data);
        foreach ($keys as $champs) {
            $champExploded = explode('-', $champs);
            $champId = $champExploded[0] ?? -1;
            $typeId = isset($champExploded[1]) ? intval($champExploded[1]) : -1;
            $isChampLibre = (ctype_digit($champId) && $champId > 0);
            if ($isChampLibre && $typeId === $demande->getType()->getId()) {
                $value = $data[$champs];
                $valeurChampLibre = $this->valeurChampLibreService->createValeurChampLibre(intval($champId), $value);
                $this->entityManager->persist($demande);
                $valeurChampLibre->addDemandesLivraison($demande);
                $this->entityManager->persist($valeurChampLibre);
            }
        }
        $this->entityManager->flush();
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

        $detailsChampLibres = $demande
            ->getValeurChampLibre()
            ->map(function (ValeurChampLibre $valeurChampLibre) {
                $champLibre = $valeurChampLibre->getChampLibre();
                $value = $this->valeurChampLibreService->formatValeurChampLibreForShow($valeurChampLibre);
                return [
                    'label' => $this->stringService->mbUcfirst($champLibre->getLabel()),
                    'value' => $value
                ];
            })
            ->toArray();

        return array_merge(
            [
                ['label' => 'Statut', 'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : ''],
                ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
                ['label' => 'Destination', 'value' => $destination ? $destination->getLabel() : ''],
                ['label' => 'Date de la demande', 'value' => $date ? $date->format('d/m/Y') : ''],
                ['label' => 'Date de validation', 'value' => $validationDate ? $validationDate->format('d/m/Y H:i') : ''],
                ['label' => 'Type', 'value' => $type ? $type->getLabel() : '']
            ],
            $detailsChampLibres,
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
}
