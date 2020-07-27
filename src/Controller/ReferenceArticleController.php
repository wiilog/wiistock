<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\InventoryCategory;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Entity\CollecteReference;
use App\Entity\CategorieCL;
use App\Entity\Fournisseur;
use App\Entity\Collecte;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\DemandeRepository;
use App\Service\DemandeCollecteService;
use App\Service\MouvementStockService;
use App\Service\ValeurChampLibreService;
use App\Service\ArticleFournisseurService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Twig\Environment as Twig_Environment;
use App\Repository\FiltreRefRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Service\CSVExportService;
use App\Service\GlobalParamService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use App\Entity\Demande;
use App\Entity\ArticleFournisseur;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;


/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends AbstractController
{

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var articleDataService
     */
    private $articleDataService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var Twig_Environment
     */
    private $templating;

	/**
	 * @var SpecificService
	 */
    private $specificService;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var object|string
     */
    private $user;

    private $CSVExportService;
    private $valeurChampLibreService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                GlobalParamService $globalParamService,
                                SpecificService $specificService,
                                Twig_Environment $templating,
                                ArticleDataService $articleDataService,
                                FiltreRefRepository $filtreRefRepository,
                                RefArticleDataService $refArticleDataService,
                                UserService $userService,
                                InventoryFrequencyRepository $inventoryFrequencyRepository,
                                CSVExportService $CSVExportService,
                                ValeurChampLibreService $valeurChampLibreService)
    {
        $this->filtreRefRepository = $filtreRefRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->userService = $userService;
        $this->templating = $templating;
        $this->specificService = $specificService;
        $this->globalParamService = $globalParamService;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->CSVExportService = $CSVExportService;
        $this->valeurChampLibreService = $valeurChampLibreService;
    }

    /**
     * @Route("/api-columns", name="ref_article_api_columns", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiColumns(Request $request,
                               EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }

            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

            $currentUser = $this->getUser(); /** @var Utilisateur $currentUser */
            $columnsVisible = $currentUser->getColumnVisible();
            $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
            $category = CategoryType::ARTICLE;
            $champs = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

			$columns = [
				[
					"title" => 'Actions',
					"data" => 'Actions',
					'name' => 'Actions',
					"class" => (in_array('Actions', $columnsVisible) ? 'display' : 'hide'),

				],
				[
					"title" => 'Libellé',
					"data" => 'Libellé',
					'name' => 'Libellé',
					"class" => (in_array('Libellé', $columnsVisible) ? 'display' : 'hide'),

				],
				[
					"title" => 'Référence',
					"data" => 'Référence',
					'name' => 'Référence',
					"class" => (in_array('Référence', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Type',
					"data" => 'Type',
					'name' => 'Type',
					"class" => (in_array('Type', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Statut',
					"data" => 'Statut',
					'name' => 'Statut',
					"class" => (in_array('Statut', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Quantité disponible',
					"data" => 'Quantité disponible',
					'name' => 'Quantité disponible',
					"class" => (in_array('Quantité disponible', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Quantité en stock',
					"data" => 'Quantité stock',
					'name' => 'Quantité stock',
					"class" => (in_array('Quantité stock', $columnsVisible) ? 'display' : 'hide'),
				],
                [
                    "title" => 'Code barre',
                    "data" => 'Code barre',
                    'name' => 'Code barre',
                    "class" => (in_array('Code barre', $columnsVisible) ? 'display' : 'hide'),

                ],
				[
					"title" => 'Emplacement',
					"data" => 'Emplacement',
					'name' => 'Emplacement',
					"class" => (in_array('Emplacement', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Commentaire',
					"data" => 'Commentaire',
					'name' => 'Commentaire',
					"class" => (in_array('Commentaire', $columnsVisible) ? 'display' : 'hide'),
				],
                [
                    "title" => 'Commentaire d\'urgence',
                    "data" => 'Commentaire d\'urgence',
                    'name' => 'Commentaire d\'urgence',
                    "class" => (in_array('Commentaire d\'urgence', $columnsVisible) ? 'display' : 'hide'),
                ],
				[
					"title" => 'Seuil d\'alerte',
					"data" => 'Seuil d\'alerte',
					'name' => 'Seuil d\'alerte',
					"class" => (in_array('Seuil d\'alerte', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Seuil de sécurité',
					"data" => 'Seuil de sécurité',
					'name' => 'Seuil de sécurité',
					"class" => (in_array('Seuil de sécurité', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Prix unitaire',
					"data" => 'Prix unitaire',
					'name' => 'Prix unitaire',
					"class" => (in_array('Prix unitaire', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Dernier inventaire',
					"data" => 'Dernier inventaire',
					'name' => 'Dernier inventaire',
					"class" => (in_array('Dernier inventaire', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Urgence',
					"data" => 'Urgence',
					'name' => 'Urgence',
					"class" => (in_array('Urgence', $columnsVisible) ? 'display' : 'hide'),
				],
                [
                    "title" => 'Synchronisation nomade',
                    "data" => 'Synchronisation nomade',
                    'name' => 'Synchronisation nomade',
                    "class" => (in_array('Synchronisation nomade', $columnsVisible) ? 'display' : 'hide'),
                ],
			];
			foreach ($champs as $champ) {
				$columns[] = [
					"title" => ucfirst(mb_strtolower($champ['label'])),
					"data" => $champ['label'],
					'name' => $champ['label'],
					"class" => (in_array($champ['label'], $columnsVisible) ? 'display' : 'hide'),
				];
			}

            return new JsonResponse($columns);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws DBALException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->refArticleDataService->getRefArticleDataByParams($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ValeurChampLibreService $valeurChampLibreService
     * @param ArticleFournisseurService $articleFournisseurService
     * @return Response
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function new(Request $request,
                        ValeurChampLibreService $valeurChampLibreService,
                        ArticleFournisseurService $articleFournisseurService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);


            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $referenceArticleRepository->countByReference($data['reference']);

            if ($refAlreadyExist) {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
					'codeError' => 'DOUBLON-REF'
				]);
            }
            $requiredCreate = true;

            $type = $typeRepository->find($data['type']);

            if ($data['emplacement'] !== null) {
                $emplacement = $emplacementRepository->find($data['emplacement']);
            } else {
                $emplacement = null; //TODO gérer message erreur (faire un return avec msg erreur adapté -> à ce jour un return false correspond forcément à une réf déjà utilisée)
            };
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

            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);

            switch($data['type_quantite']) {
                case 'article':
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                    break;
                default:
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_REFERENCE;
                    break;
            }
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setCommentaire($data['commentaire'])
                ->setTypeQuantite($typeArticle)
                ->setPrixUnitaire(max(0, $data['prix']))
                ->setType($type)
                ->setIsUrgent($data['urgence'])
                ->setEmplacement($emplacement)
				->setBarCode($this->refArticleDataService->generateBarCode());


            if ($refArticle->getIsUrgent()) {
                $refArticle->setUserThatTriggeredEmergency($this->getUser());
            }

            if ($data['limitSecurity']) {
            	$refArticle->setLimitSecurity($data['limitSecurity']);
			}
            if ($data['limitWarning']) {
            	$refArticle->setLimitWarning($data['limitWarning']);
			}
            if ($data['emergency-comment-input']) {
                $refArticle->setEmergencyComment($data['emergency-comment-input']);
            }
            if ($data['categorie']) {
            	$category = $inventoryCategoryRepository->find($data['categorie']);
            	if ($category) $refArticle->setCategory($category);
			}
            if ($statut) $refArticle->setStatut($statut);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $refArticle->setQuantiteStock($data['quantite'] ? max($data['quantite'], 0) : 0); // protection contre quantités négatives
            } else {
                $refArticle->setQuantiteStock(0);
            }
            $refArticle->setQuantiteReservee(0);

            foreach ($data['frl'] as $frl) {
                $articleFournisseurData = explode(';', $frl);
                $fournisseurArticleFournisseur = $articleFournisseurData[0];
                $referenceArticleFournisseur = $articleFournisseurData[1];
                $labelArticleFournisseur = $articleFournisseurData[2];

                try {
                    $articleFournisseur = $articleFournisseurService->createArticleFournisseur([
                        'fournisseur' => $fournisseurArticleFournisseur,
                        'article-reference' => $refArticle,
                        'label' => $labelArticleFournisseur,
                        'reference' => $referenceArticleFournisseur
                    ]);

                    $entityManager->persist($articleFournisseur);
                }
                catch (Exception $exception) {
                    if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => "La référence '$referenceArticleFournisseur' existe déjà pour un article fournisseur."
                        ]);
                    }
                }
            }

            $entityManager->persist($refArticle);
            $entityManager->flush();

            $champsLibresKey = array_keys($data);

            foreach ($champsLibresKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = $valeurChampLibreService->createValeurChampLibre($champs, $data[$champs]);
                    $valeurChampLibre->addArticleReference($refArticle);
                    $entityManager->persist($valeurChampLibre);
                    $entityManager->flush();
                }
            }
            return new JsonResponse([
                'success' => true,
                'new' => $this->refArticleDataService->dataRowRefArticle($refArticle),
                'id' => $refArticle->getId(),
                'text' => $refArticle->getReference(),
            ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reference_article_index",  methods="GET|POST", options={"expose"=true})
     * @return Response
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
            return $this->redirectToRoute('access_denied');
        }

        $entityManager = $this->getDoctrine()->getManager();

        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $typeQuantite = [
            [
                'const' => 'QUANTITE_AR',
                'label' => 'référence',
            ],
            [
                'const' => 'QUANTITE_A',
                'label' => 'article',
            ]
        ];

        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
        $category = CategoryType::ARTICLE;
        $champL = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        $champF[] = [
            'label' => 'Actions',
            'id' => 0,
            'typage' => ''
        ];
        $champF[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Code barre',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Urgence',
            'id' => 0,
            'typage' => 'booleen'

        ];
        $champF[] = [
            'label' => 'Type',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Statut',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Quantité stock',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Quantité disponible',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Emplacement',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Commentaire',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Commentaire d\'urgence',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Seuil de sécurité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Seuil d\'alerte',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Prix unitaire',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Synchronisation nomade',
            'id' => 0,
            'typage' => 'booleen'
        ];
        $champF[] = [
            'label' => 'Dernier inventaire',
            'id' => 0,
            'typage' => 'date'
        ];

        // champs pour recherche personnalisée (uniquement de type texte ou liste)
		$champsLText = $champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_TEXT);
		$champsLTList = $champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_LIST);

		$champsFText[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];

        $champsFText[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Code barre',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Fournisseur',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Synchronisation nomade',
            'id' => 0,
            'typage' => 'sync'

        ];
        $champsFText[] = [
            'label' => 'Référence Article Fournisseur',
            'id' => 0,
            'typage' => 'text'

        ];

        $champs = array_merge($champF, $champL);
        $champsSearch = array_merge($champsFText, $champsLText, $champsLTList);

        usort($champs, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
        });

		usort($champsSearch, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});

        $types = $typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
        $inventoryCategories = $inventoryCategoryRepository->findAll();
        $emplacements = $emplacementRepository->findAll();
        $typeChampLibre =  [];
        $search = $this->getUser()->getRecherche();
        foreach ($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' =>  $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }
        $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($this->getUser(), FiltreRef::CHAMP_FIXE_STATUT);

        return $this->render('reference_article/index.html.twig', [
            'champs' => $champs,
            'champsSearch' => $champsSearch,
            'recherches' => $search,
            'columnsVisibles' => $this->getUser()->getColumnVisible(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'emplacements' => $emplacements,
            'typeQuantite' => $typeQuantite,
            'filters' => $this->filtreRefRepository->findByUserExceptChampFixe($this->getUser(), FiltreRef::CHAMP_FIXE_STATUT),
            'categories' => $inventoryCategories,
            'wantInactif' => !empty($filter) && $filter->getValue() === Article::STATUT_INACTIF
        ]);
    }

    /**
     * @Route("/api-modifier", name="reference_article_edit_api", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = $referenceArticleRepository->find((int)$data['id']);

            if ($refArticle) {
                $json = $this->refArticleDataService->getViewEditRefArticle($refArticle, $data['isADemand']);
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reference_article_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws DBALException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refId = intval($data['idRefArticle']);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($refId);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $referenceArticleRepository->countByReference($data['reference'], $refId);

            if ($refAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
                    'codeError' => 'DOUBLON-REF'
                ]);
            }
            if ($refArticle) {
                try {
                    $response = $this->refArticleDataService->editRefArticle($refArticle, $data, $this->getUser());
                }
                catch (ArticleNotAvailableException $exception) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas modifier la quantité d'une référence inactive."
                    ];
                }
                catch (RequestNeedToBeProcessedException $exception) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas modifier la quantité d'une référence qui est dans un ordre de livraison en cours."
                    ];
                }
            } else {
                $response = ['success' => false, 'msg' => "Une erreur s'est produite lors de la modification de la référence."];
            }
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="reference_article_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            $rows = $refArticle->getId();
            $entityManager = $this->getDoctrine()->getManager();
            if (count($refArticle->getCollecteReferences()) > 0
                || count($refArticle->getLigneArticles()) > 0
                || count($refArticle->getReceptionReferenceArticles()) > 0
                || count($refArticle->getMouvements()) > 0
                || count($refArticle->getMouvementTracas()) > 0
                || count($refArticle->getArticlesFournisseur()) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La référence est liée à d\'autres données, vous pouvez passer son statut en inactif pour ne plus l\'utiliser.'
                ]);
            }
            $entityManager->remove($refArticle);
            $entityManager->flush();

            $response['delete'] = $rows;
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/addFournisseur", name="ajax_render_add_fournisseur", options={"expose"=true}, methods="GET|POST")
     */
    public function addFournisseur(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $json = $this->renderView('reference_article/fournisseurArticle.html.twig');
        return new JsonResponse($json);
    }

    /**
     * @Route("/removeFournisseur", name="ajax_render_remove_fournisseur", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function removeFournisseur(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $entityManager->remove($articleFournisseurRepository->find($data['articleF']));
            $entityManager->flush();
            $json =  $this->renderView('reference_article/fournisseurArticleContent.html.twig', [
                'articles' => $articleFournisseurRepository->findByRefArticle($data['articleRef']),
                'articleRef' => $referenceArticleRepository->find($data['articleRef'])
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/quantite", name="get_quantity_ref_article", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse|RedirectResponse
     */
    public function getQuantityByRefArticleId(Request $request, EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $quantity = false;

            $refArticleId = $request->request->get('refArticleId');
            $refArticle = $referenceArticleRepository->find($refArticleId);

            if ($refArticle) {
				if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
					$quantity = $refArticle->getQuantiteStock();
				}
			}

            return new JsonResponse($quantity);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete-ref/{activeOnly}/type/{field}/{typeQuantity}", name="get_ref_articles", options={"expose"=true}, methods="GET|POST")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param bool $activeOnly
     * @param null $typeQuantity
     * @param string $field
     * @return JsonResponse
     */
    public function getRefArticles(Request $request, EntityManagerInterface $entityManager, $activeOnly = false, $typeQuantity = null, $field = 'reference')
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticles = $referenceArticleRepository->getIdAndRefBySearch($search, $activeOnly, $typeQuantity, $field);

            return new JsonResponse(['results' => $refArticles]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete-ref-and-article/{activeOnly}", name="get_ref_and_articles", options={"expose"=true}, methods="GET|POST")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param bool $activeOnly
     * @return JsonResponse
     */
	public function getRefAndArticles(Request $request, EntityManagerInterface $entityManager, $activeOnly = false)
	{
		if ($request->isXmlHttpRequest()) {
			$search = $request->query->get('term');
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $articleRepository = $entityManager->getRepository(Article::class);

			$refArticles = $referenceArticleRepository->getIdAndRefBySearch($search, $activeOnly);
			$articles = $articleRepository->getIdAndRefBySearch($search, $activeOnly);

			return new JsonResponse(['results' => array_merge($articles, $refArticles)]);
		}
		throw new NotFoundHttpException("404");
	}

    /**
     * @Route("/plus-demande", name="plus_demande", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param DemandeRepository $demandeRepository
     * @param DemandeCollecteService $demandeCollecteService
     * @return Response
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function plusDemande(EntityManagerInterface $entityManager,
                                Request $request,
                                DemandeCollecteService $demandeCollecteService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);

            $json = true;

            $refArticle = (isset($data['refArticle']) ? $referenceArticleRepository->find($data['refArticle']) : '');
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $statusName = $refArticle->getStatut() ? $refArticle->getStatut()->getNom() : '';
            if ($statusName == ReferenceArticle::STATUT_ACTIF) {
				if (array_key_exists('livraison', $data) && $data['livraison']) {
				    $demande = $demandeRepository->find($data['livraison']);
                    $json = $this->refArticleDataService->addRefToDemand($data, $refArticle, $this->getUser(), false, $entityManager, $demande);
                    if ($json === 'article') {
                        try {
                            $this->articleDataService->editArticle($data);
                            $json = true;
                        }
                        catch(ArticleNotAvailableException $exception) {
                            $json = [
                                'success' => false,
                                'msg' => "Vous ne pouvez pas modifier un article qui n'est pas disponible."
                            ];
                        }
                        catch(RequestNeedToBeProcessedException $exception) {
                            $json = [
                                'success' => false,
                                'msg' => "Vous ne pouvez pas modifier un article qui est dans une demande de livraison."
                            ];
                        }
					}

				} elseif (array_key_exists('collecte', $data) && $data['collecte']) {
					$collecte = $collecteRepository->find($data['collecte']);
					if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
						//TODO patch temporaire CEA
                        $data['quantity-to-pick'] = $data['quantite'];
                        $demandeCollecteService->persistArticleInDemand($data, $refArticle, $collecte);
						//TODO fin patch temporaire CEA (à remplacer par lignes suivantes)
						//                    $article = $this->articleRepository->find($data['article']);
						//                    $collecte->addArticle($article);
					}
					elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
						$collecteReference = new CollecteReference();
						$collecteReference
							->setCollecte($collecte)
							->setReferenceArticle($refArticle)
							->setQuantite(max((int)$data['quantity-to-pick'], 0)); // protection contre quantités négatives
                        $entityManager->persist($collecteReference);
					} else {
						$json = false; //TOOD gérer message erreur
					}
				} else {
					$json = false; //TOOD gérer message erreur
				}
                $entityManager->flush();
			} else {
            	$json = false;
			}

            return new JsonResponse($json);

        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-plus-demande-content", name="ajax_plus_demande_content", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function ajaxPlusDemandeContent(EntityManagerInterface $entityManager,
                                           Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $refArticle = $referenceArticleRepository->find($data['id']);
            if ($refArticle) {
                $collectes = $collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

                $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
                $demandes = $demandeRepository->findByStatutAndUser($statutD, $this->getUser());

                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    if ($refArticle) {
                        $editChampLibre  = $this->refArticleDataService->getViewEditRefArticle($refArticle, true);
                    } else {
                        $editChampLibre = false;
                    }
                } else {
                    //TODO patch temporaire CEA
					$isCea = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);
                    if ($isCea && $refArticle->getStatut()->getNom() === ReferenceArticle::STATUT_INACTIF && $data['demande'] === 'collecte') {
                        $response = [
                            'plusContent' => $this->renderView('reference_article/modalPlusDemandeTemp.html.twig', [
                                'collectes' => $collectes
                            ]),
                            'temp' => true
                        ];
                        return new JsonResponse($response);
                    }
                    //TODO fin de patch temporaire CEA
                    $editChampLibre = false;
                }

				$byRef = $this->userService->hasParamQuantityByRef();
                $articleOrNo  = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, $data['demande'], $byRef);

                $json = [
                    'plusContent' => $this->renderView(
                        'reference_article/modalPlusDemandeContent.html.twig',
                        [
                            'articleOrNo' => $articleOrNo,
                            'collectes' => $collectes,
                            'demandes' => $demandes
                        ]
                    ),
                    'editChampLibre' => $editChampLibre,
					'byRef' => $byRef && $refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE
				];
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible", options={"expose"=true}, methods="GET|POST")
     */
    public function saveColumnVisible(Request $request): Response
    {
        if ($request->isXmlHttpRequest() ) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = json_decode($request->getContent(), true);
            $champs = array_keys($data);
            $user  = $this->getUser();
            /** @var $user Utilisateur */
            $user->setColumnVisible($champs);
            $em  = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse(['success' => true]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir", name="reference_article_show", options={"expose"=true})
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function show(Request $request,
                         RefArticleDataService $refArticleDataService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle  = $referenceArticleRepository->find($data);
            $json = $refArticle
                ? $refArticleDataService->getViewEditRefArticle($refArticle, false, false)
                : false;
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/exporter/{min}/{max}", name="reference_article_export", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param $max
     * @param $min
     * @return Response
     */
    public function exportAll(EntityManagerInterface $entityManager,
                              Request $request,
                              $max,
                              $min): Response
    {
        if ($request->isXmlHttpRequest()) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);

            $data = [];
            $data['values'] = [];
            $headersCL = [];
            foreach ($champLibreRepository->findAll() as $champLibre) {
                $headersCL[] = $champLibre->getLabel();
            }
            $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLE);

            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $references = $referenceArticleRepository->getBetweenLimits($min, $max-$min);
            foreach ($references as $reference) {
                $data['values'][] = $this->buildInfos($reference, $listTypes, $headersCL);
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/export-donnees", name="exports_params")
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
    public function renderParams(UserService $userService)
    {
        if (!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_EXPO)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('exports/exportsMenu.html.twig');
    }

    /**
     * @Route("/total", name="get_total_and_headers_ref", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function total(Request $request,
                          EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $data['total'] = $referenceArticleRepository->countAll();
            $data['headers'] = [
                'reference',
                'libellé',
                'quantité',
                'type',
                'type quantité',
                'statut',
                'commentaire',
                'emplacement',
                'fournisseurs',
                'articles fournisseurs',
                'seuil sécurite',
                'seuil alerte',
                'prix unitaire',
                'code barre',
				'catégorie inventaire',
				'date dernier inventaire',
                'synchronisation nomade'
            ];

            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            foreach ($champLibreRepository->findAll() as $champLibre) {
                $data['headers'][] = $champLibre->getLabel();
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param ReferenceArticle $ref
     * @param array $listTypes
     * @param string[] $headersCL
     * @return string
     */
    public function buildInfos(ReferenceArticle $ref,
                               $listTypes,
                               $headersCL) {
        $entityManager = $this->getDoctrine()->getManager();
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);

        $listFournisseurAndAF = $fournisseurRepository->getNameAndRefArticleFournisseur($ref);

    	$arrayAF = $arrayF = [];

    	foreach ($listFournisseurAndAF as $fournisseurAndAF) {
    		$arrayAF[] = $fournisseurAndAF['reference'];
    		$arrayF[] = $fournisseurAndAF['nom'];
		}

    	$stringArticlesFournisseur = implode(' / ', $arrayAF);
    	$stringFournisseurs = implode(' / ', $arrayF);

        $refData[] = $this->CSVExportService->escapeCSV($ref->getReference());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLibelle());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getQuantiteStock());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getType() ? $ref->getType()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($ref->getTypeQuantite());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getStatut() ? $ref->getStatut()->getNom() : '');
        $refData[] = $this->CSVExportService->escapeCSV(strip_tags($ref->getCommentaire()));
        $refData[] = $this->CSVExportService->escapeCSV($ref->getEmplacement() ? $ref->getEmplacement()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($stringFournisseurs);
        $refData[] = $this->CSVExportService->escapeCSV($stringArticlesFournisseur);
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLimitSecurity());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLimitWarning());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getPrixUnitaire());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getBarCode());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getCategory() ? $ref->getCategory()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($ref->getDateLastInventory() ? $ref->getDateLastInventory()->format('d/m/Y') : '');
        $refData[] = $this->CSVExportService->escapeCSV($ref->getNeedsMobileSync() ? 'Oui' : 'Non');

        $champsLibres = [];
        foreach ($listTypes as $typeArray) {
        	$type = $typeRepository->find($typeArray['id']);
            $listChampsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            foreach ($listChampsLibres as $champLibre) {
                $valeurChampRefArticle = $valeurChampLibreRepository->findOneByRefArticleAndChampLibre($ref->getId(), $champLibre);
                if ($valeurChampRefArticle) {
                    $champsLibres[$champLibre->getLabel()] = $this->valeurChampLibreService->formatValeurChampLibreForExport($valeurChampRefArticle);
                }
            }
        }
        foreach ($headersCL as $type) {
            if (array_key_exists($type, $champsLibres)) {
                $refData[] = $champsLibres[$type];
            } else {
                $refData[] = '';
            }
        }
        return implode(';', $refData);
    }

    /**
     * @Route("/type-quantite", name="get_quantity_type", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getQuantityType(Request $request, EntityManagerInterface $entityManager)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $referenceArticleRepository->find($data['id']);

			$quantityType = $reference ? $reference->getTypeQuantite() : '';

			return new JsonResponse($quantityType);
		}
		throw new NotFoundHttpException('404');
	}

    /**
     * @Route("/get-demande", name="demande", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getDemande(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data= json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $statutDemande = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $demandes = $demandeRepository->findByStatutAndUser($statutDemande, $this->getUser());
            $collectes = $collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

            if ($data['typeDemande'] === 'livraison' && $demandes) {
                $json = $demandes;
            } elseif ($data['typeDemande'] === 'collecte' && $collectes) {
                $json = $collectes;
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/etiquettes", name="reference_article_bar_codes_print", options={"expose"=true})
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getBarCodes(Request $request,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                PDFGeneratorService $PDFGeneratorService): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $request->query, $this->user);
        $refs = $queryResult['data'];
        $refs = array_map(function($refArticle) {
            return is_array($refArticle) ? $refArticle[0] : $refArticle;
        }, $refs);
        $barcodeConfigs = array_map(
            function (ReferenceArticle $reference) use ($refArticleDataService) {
                return $refArticleDataService->getBarcodeConfig($reference);
            },
            $refs
        );

        $barcodeCounter = count($barcodeConfigs);

        if ($barcodeCounter > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barcodeConfigs,
                'reference' . ($barcodeCounter > 1 ? 's' : '')
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
                $fileName
            );
        }
        else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/{reference}/etiquette", name="reference_article_single_bar_code_print", options={"expose"=true})
     * @param ReferenceArticle $reference
     * @param RefArticleDataService $refArticleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSingleBarCodes(ReferenceArticle $reference,
                                      RefArticleDataService $refArticleDataService,
                                      PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($reference)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'reference');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route("/show-actif-inactif", name="reference_article_actif_inactif", options={"expose"=true})
     */
    public function displayActifOrInactif(Request $request) : Response
    {
        if ($request->isXmlHttpRequest() && $data= json_decode($request->getContent(), true)){

            $user = $this->getUser();
            $statutArticle = $data['donnees'];

            $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::CHAMP_FIXE_STATUT);

            $em = $this->getDoctrine()->getManager();
            if($filter == null){
                $filter = new FiltreRef();
                $filter
                    ->setUtilisateur($user)
                    ->setChampFixe('Statut')
                    ->setValue(ReferenceArticle::STATUT_ACTIF);
                $em->persist($filter);
            }

            if ($filter->getValue() != $statutArticle) {
                $filter->setValue($statutArticle);
            }
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/mouvements/lister", name="ref_mouvements_list", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function showMovements(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            if ($ref = $referenceArticleRepository->find($data)) {
                $name = $ref->getLibelle();
            }

           return new JsonResponse($this->renderView('reference_article/modalShowMouvementsContent.html.twig', [
               'refLabel' => $name?? ''
           ]));
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/mouvements/api/{referenceArticle}", name="ref_mouvements_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param ReferenceArticle $referenceArticle
     * @return Response
     */
    public function apiMouvements(EntityManagerInterface $entityManager,
                                  Request $request,
                                  MouvementStockService $mouvementStockService,
                                  ReferenceArticle $referenceArticle): Response
    {
        if ($request->isXmlHttpRequest()) {
            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
            $mouvements = $mouvementStockRepository->findByRef($referenceArticle);

            $data['data'] = array_map(
                function(MouvementStock $mouvement) use ($entityManager, $mouvementStockService) {
                    $fromColumnConfig = $mouvementStockService->getFromColumnConfig($entityManager, $mouvement);
                    $from = $fromColumnConfig['from'];
                    $orderPath = $fromColumnConfig['orderPath'];
                    $orderId = $fromColumnConfig['orderId'];

                    return [
                        'Date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : 'aucune',
                        'Quantity' => $mouvement->getQuantity(),
                        'Origin' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : 'aucun',
                        'Destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : 'aucun',
                        'Type' => $mouvement->getType(),
                        'Operator' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : 'aucun',
                        'from' => $this->templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
                            'from' => $from,
                            'mvt' => $mouvement,
                            'orderPath' => $orderPath,
                            'orderId' => $orderId
                        ]),
                        'ArticleCode' => $mouvement->getArticle() ? $mouvement->getArticle()->getBarCode() : $mouvement->getRefArticle()->getBarCode()
                    ];
                },
                $mouvements
            );
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route(
     *     "/{referenceArticle}/quantity",
     *     name="update_qte_refarticle",
     *     options={"expose"=true},
     *     methods="PATCH",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param EntityManagerInterface $entityManager
     * @param ReferenceArticle $referenceArticle
     * @param RefArticleDataService $refArticleDataService
     * @return JsonResponse
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function updateQuantity(EntityManagerInterface $entityManager,
                                   ReferenceArticle $referenceArticle,
                                   RefArticleDataService $refArticleDataService) {

        $refArticleDataService->updateRefArticleQuantities($referenceArticle, true);
        $entityManager->flush();
        $refArticleDataService->treatAlert($referenceArticle);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
