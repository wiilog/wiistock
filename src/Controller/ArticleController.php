<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\ChampLibre;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\CollecteRepository;
use App\Repository\ReceptionRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\CategorieCLRepository;
use App\Service\CSVExportService;
use App\Service\GlobalParamService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController
{

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var ArticleDataService
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
     * @var GlobalParamService
     */
    private $globalParamService;

    private $CSVExportService;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

	/**
	 * @var ParametrageGlobalRepository
	 */
	private $paramGlobalRepository;

    public function __construct(Twig_Environment $templating,
                                GlobalParamService $globalParamService,
                                CategorieCLRepository $categorieCLRepository,
                                FournisseurRepository $fournisseurRepository,
                                ChampLibreRepository $champLibreRepository,
                                ValeurChampLibreRepository $valeurChampsLibreRepository,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                ArticleFournisseurRepository $articleFournisseurRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ReceptionRepository $receptionRepository,
                                ArticleRepository $articleRepository,
                                EmplacementRepository $emplacementRepository,
                                CollecteRepository $collecteRepository,
                                UserService $userService,
                                FiltreSupRepository $filtreSupRepository,
                                ParametrageGlobalRepository $parametrageGlobalRepository,
                                CSVExportService $CSVExportService)
    {
        $this->paramGlobalRepository = $parametrageGlobalRepository;
        $this->globalParamService = $globalParamService;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->valeurChampLibreRepository = $valeurChampsLibreRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->collecteRepository = $collecteRepository;
        $this->receptionRepository = $receptionRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->userService = $userService;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->templating = $templating;
        $this->CSVExportService = $CSVExportService;
        $this->filtreSupRepository = $filtreSupRepository;
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
            return $this->redirectToRoute('access_denied');
        }
        /**
         * @var Utilisateur $user
         */
        $user = $this->getUser();
        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);
        $category = CategoryType::ARTICLE;
        $champL = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
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
//        $champF[] = [
//            'label' => 'Référence',
//            'id' => 0,
//            'typage' => 'text'
//
//        ];
        $champF[] = [
            'label' => 'Référence article',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Code barre',
            'id' => 0,
            'typage' => 'text'

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
            'label' => 'Quantité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Emplacement',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Date et heure',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Commentaire',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Prix unitaire',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Dernier inventaire',
            'id' => 0,
            'typage' => 'date'
        ];
        $champsFText = [];

        $champsFText[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];
//        $champsFText[] = [
//            'label' => 'Référence',
//            'id' => 0,
//            'typage' => 'text'
//
//        ];
		$champsFText[] = [
			'label' => 'Référence article',
			'id' => 0,
			'typage' => 'text'

		];
		$champsFText[] = [
            'label' => 'Code barre',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Type',
            'id' => 0,
            'typage' => 'list'
        ];
        $champsFText[] = [
            'label' => 'Statut',
            'id' => 0,
            'typage' => 'text'
        ];
        $champsFText[] = [
            'label' => 'Quantité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champsFText[] = [
            'label' => 'Emplacement',
            'id' => 0,
            'typage' => 'list'
        ];
        $champsFText[] = [
            'label' => 'Date et heure',
            'id' => 0,
            'typage' => 'text'
        ];
        $champsFText[] = [
            'label' => 'Commentaire',
            'id' => 0,
            'typage' => 'text'
        ];
        $champsFText[] = [
            'label' => 'Prix unitaire',
            'id' => 0,
            'typage' => 'number'
        ];
        $champsFText[] = [
            'label' => 'Dernier inventaire',
            'id' => 0,
            'typage' => 'date'
        ];
        $champsLText = $this->champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_TEXT);
        $champsLTList = $this->champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_LIST);
        $champs = array_merge($champF, $champL);
        $champsSearch = array_merge($champsFText, $champsLText, $champsLTList);
        $filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, FiltreSup::PAGE_ARTICLE, $this->getUser());
        return $this->render('article/index.html.twig', [
            'valeurChampLibre' => null,
            'champsSearch' => $champsSearch,
            'recherches' => $user->getRechercheForArticle(),
            'champs' => $champs,
            'columnsVisibles' => $user->getColumnsVisibleForArticle(),
            'activeOnly' => !empty($filter) && $filter->getValue() === Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT
        ]);
    }

    /**
     * @Route("/show-actif-inactif", name="article_actif_inactif", options={"expose"=true})
     */
    public function displayActifOrInactif(Request $request) : Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)){

            $user = $this->getUser();

            $filter = $this->filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, FiltreSup::PAGE_ARTICLE, $user);
            $em = $this->getDoctrine()->getManager();
            $activeOnly = $data['activeOnly'];

            if ($activeOnly) {
            	if (empty($filter)) {
					$filter = new FiltreSup();
					$filter
						->setUser($user)
						->setField(FiltreSup::FIELD_STATUT)
						->setValue(Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT)
						->setPage(FiltreSup::PAGE_ARTICLE);
					$em->persist($filter);
				} else {
					$filter->setValue(Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT);
				}
			} else {
            	if (!empty($filter)) {
            		$em->remove($filter);
				}
			}

            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->articleDataService->getDataForDatatable($request->request, $this->getUser());
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-columns", name="article_api_columns", options={"expose"=true}, methods="GET|POST")
     */
    public function apiColumns(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $currentUser = $this->getUser();
            /** @var Utilisateur $currentUser */
            $columnsVisible = $currentUser->getColumnsVisibleForArticle();
            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);
            $category = CategoryType::ARTICLE;
            $champs = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

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
//				[
//					"title" => 'Référence',
//					"data" => 'Référence',
//					'name' => 'Référence',
//					"class" => (in_array('Référence', $columnsVisible) ? 'display' : 'hide'),
//				],
				[
					"title" => 'Référence article',
					"data" => 'Référence article',
					'name' => 'Référence article',
					"class" => (in_array('Référence article', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Code barre',
					"data" => 'Code barre',
					'name' => 'Code barre',
					"class" => (in_array('Code barre', $columnsVisible) ? 'display' : 'hide'),

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
					"title" => 'Quantité',
					"data" => 'Quantité',
					'name' => 'Quantité',
					"class" => (in_array('Quantité', $columnsVisible) ? 'display' : 'hide'),
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
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $article = $this->articleRepository->find($data);

            $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
            $typeArticle = $refArticle->getType();
            $typeArticleLabel = $typeArticle->getLabel();

            $champsLibresComplet = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
            $champsLibres = [];
            foreach ($champsLibresComplet as $champLibre) {
                $valeurChampArticle = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);
                $champsLibres[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'requiredCreate' => $champLibre->getRequiredCreate(),
                    'requiredEdit' => $champLibre->getRequiredEdit(),
                    'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                    'defaultValue' => $champLibre->getDefaultValue(),
                    'valeurChampLibre' => $valeurChampArticle
                ];
            }

            $typeChampLibre =
                [
                    'type' => $typeArticleLabel,
                    'champsLibres' => $champsLibres,
                ];
            if ($article) {
                $view = $this->templating->render('article/modalArticleContent.html.twig', [
                    'typeChampsLibres' => $typeChampLibre,
                    'typeArticle' => $typeArticleLabel,
					'typeArticleId' => $typeArticle->getId(),
					'article' => $article,
                    'statut' => $article->getStatut() ? $article->getStatut()->getNom() : '',
					'isADemand' => false,
					'invCategory' => $refArticle->getCategory(),
                ]);
                $json = $view;
            } else {
                return $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/modifier", name="article_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $article = $this->articleRepository->find((int)$data['id']);
            if ($article) {
                $json = $this->articleDataService->getViewEditArticle($article, $data['isADemand']);
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/nouveau", name="article_new", options={"expose"=true},  methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = $this->articleDataService->newArticle($data);

            return new JsonResponse(!empty($response));
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="article_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($data['article']) {
                $this->articleDataService->editArticle($data);
                $json = true;
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="article_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $article = $this->articleRepository->find($data['article']);
            $rows = $article->getId();

            // on vérifie que l'article n'est plus utilisé
            $articleIsUsed = $this->isArticleUsed($article);

            if ($articleIsUsed) {
                return new JsonResponse(false);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($article);
            $entityManager->flush();

            $response['delete'] = $rows;
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="article_check_delete", options={"expose"=true})
     */
    public function checkArticleCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $articleId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }

            $article = $this->articleRepository->find($articleId);
            $articleIsUsed = $this->isArticleUsed($article);

            if (!$articleIsUsed) {
                $delete = true;
                $html = $this->renderView('article/modalDeleteArticleRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('article/modalDeleteArticleWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param Article $article
     * @return bool
     */
    private function isArticleUsed($article)
    {
        if (count($article->getCollectes()) > 0 || $article->getDemande() !== null) {
            return true;
        }
        return false;
    }

    /**
     * @Route("/autocompleteArticleFournisseur", name="get_articleRef_fournisseur", options={"expose"=true})
     */
    public function getRefArticles(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $articleFournisseur = $this->articleFournisseurRepository->findBySearch($search);
            return new JsonResponse(['results' => $articleFournisseur]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete-art/{activeOnly}", name="get_articles", options={"expose"=true}, methods="GET|POST")
     *
     * @param Request $request
     * @param bool $activeOnly
     * @return JsonResponse
     */
    public function getArticles(Request $request, $activeOnly = false)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $articles = $this->articleRepository->getIdAndRefBySearch($search, $activeOnly);

            return new JsonResponse(['results' => $articles]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/get-article-collecte", name="get_collecte_article_by_refArticle", options={"expose"=true})
     */
    public function getCollecteArticleByRefArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = null;
            if ($data['referenceArticle']) {
                $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            }
            if ($refArticle) {
                $json = $this->articleDataService->getCollecteArticleOrNoByRefArticle($refArticle);
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/get-article-demande", name="demande_article_by_refArticle", options={"expose"=true})
     */
    public function getLivraisonArticlesByRefArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $refArticle = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($refArticle);

            if ($refArticle) {
                $json = $this->articleDataService->getLivraisonArticlesByRefArticle($refArticle);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_article", options={"expose"=true}, methods="GET|POST")
     */
    public function saveColumnVisible(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ARTI)) {
                return $this->redirectToRoute('access_denied');
            }
            $champs = array_keys($data);
            $user = $this->getUser();
            /** @var $user Utilisateur */
            $user->setColumnsVisibleForArticle($champs);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/get-article-fournisseur", name="demande_reference_by_fournisseur", options={"expose"=true})
     */
    public function getRefArticleByFournisseur(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $fournisseur = json_decode($request->getContent(), true)) {
            $fournisseur = $this->fournisseurRepository->find($fournisseur);

            if ($fournisseur) {
                $json = $this->renderView('article/modalNewArticleContent.html.twig', [
                    'references' => $this->articleFournisseurRepository->getByFournisseur($fournisseur),
                    'valeurChampLibre' => null
                ]);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajax_article_new_content", name="ajax_article_new_content", options={"expose"=true})
     */
    public function ajaxArticleNewContent(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $articleFournisseur = $this->articleFournisseurRepository
                ->findByRefArticleAndFournisseur($data['referenceArticle'], $data['fournisseur']);

            if (count($articleFournisseur) === 0) {
                $json = [
                    'error' => 'Aucune référence fournisseur trouvée.'
                ];
            } elseif (count($articleFournisseur) > 0) {
                $typeArticle = $refArticle->getType();

                $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
                $json = [
                    'content' => $this->renderView(
                        'article/modalNewArticleContent.html.twig',
                        [
                            'typeArticle' => $typeArticle->getLabel(),
                            'champsLibres' => $champsLibres,
                            'references' => $articleFournisseur,
                        ]
                    ),
                ];
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajax-fournisseur-by-refarticle", name="ajax_fournisseur_by_refarticle", options={"expose"=true})
     */
    public function ajaxFournisseurByRefArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            if ($refArticle && $refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $articleFournisseurs = $refArticle->getArticlesFournisseur();
                $fournisseurs = [];
                foreach ($articleFournisseurs as $articleFournisseur) {
                    $fournisseurs[] = $articleFournisseur->getFournisseur();
                }
                $fournisseursUnique = array_unique($fournisseurs);
                $json = $this->renderView(
                    'article/optionFournisseurNewArticle.html.twig',
                    [
                        'fournisseurs' => $fournisseursUnique
                    ]
                );
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/exporter/{min}/{max}", name="article_export", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param $max
     * @param $min
     * @return Response
     */
    public function exportAll(Request $request,
                              EntityManagerInterface $entityManager,
                              $max,
                              $min): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = [];
            $data['values'] = [];
            $headersCL = [];
            foreach ($this->champLibreRepository->findAll() as $champLibre) {
                $headersCL[] = $champLibre->getLabel();
            }


            $typeRepository = $entityManager->getRepository(Type::class);
            $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLE);

            $refs = $this->articleRepository->findAll();
            if ($max > count($refs)) $max = count($refs);
            for ($i = $min; $i < $max; $i++) {
                array_push($data['values'], $this->buildInfos($typeRepository, $refs[$i], $listTypes, $headersCL));
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/total", name="get_total_and_headers_art", options={"expose"=true}, methods="GET|POST")
     */
    public function total(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data['total'] = $this->articleRepository->countAll();
            $data['headers'] = ['reference', 'libelle', 'quantité', 'type', 'statut', 'commentaire', 'emplacement', 'code barre', 'date dernier inventaire'];
            foreach ($this->champLibreRepository->findAll() as $champLibre) {
                array_push($data['headers'], $champLibre->getLabel());
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param TypeRepository $typeRepository
     * @param Article $article
     * @param array $listTypes
     * @param $headers
     * @return string
     */
    public function buildInfos(TypeRepository $typeRepository,
                               Article $article,
                               $listTypes,
                               $headers)
    {
        $refData[] = $this->CSVExportService->escapeCSV($article->getReference());
        $refData[] = $this->CSVExportService->escapeCSV($article->getLabel());
        $refData[] = $this->CSVExportService->escapeCSV($article->getQuantite());
        $refData[] = $article->getType() ? $this->CSVExportService->escapeCSV($article->getType()->getLabel()) : '';
        $refData[] = $article->getStatut() ? $this->CSVExportService->escapeCSV($article->getStatut()->getNom()) : '';
        $refData[] = $this->CSVExportService->escapeCSV(strip_tags($article->getCommentaire()));
        $refData[] = $article->getEmplacement() ? $this->CSVExportService->escapeCSV($article->getEmplacement()->getLabel()) : '';
        $refData[] = $this->CSVExportService->escapeCSV($article->getBarCode());
        $refData[] = $this->CSVExportService->escapeCSV($article->getDateLastInventory() ? $article->getDateLastInventory()->format('d/m/Y') : '');
        $champsLibres = [];
        foreach ($listTypes as $type) {
            $typeArticle = $typeRepository->find($type['id']);
            $listChampsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
            foreach ($listChampsLibres as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);
                if ($valeurChampRefArticle) $champsLibres[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
            }
        }
        foreach ($headers as $type) {
            if (array_key_exists($type, $champsLibres)) {
                $refData[] = $this->CSVExportService->escapeCSV($champsLibres[$type]);
            } else {
                $refData[] = '';
            }
        }
        return implode(';', $refData);
    }

    /**
     * @Route("/etiquettes", name="article_print_bar_codes", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @param ArticleDataService $articleDataService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArticlesBarCodes(Request $request,
                                          PDFGeneratorService $PDFGeneratorService,
                                          ArticleDataService $articleDataService): Response {
        $listArticles = explode(',', $request->query->get('listArticles') ?? '');
        $barcodeConfigs = array_slice(
            array_map(
                function (Article $article) use ($articleDataService) {
                    return $articleDataService->getBarcodeConfig($article);
                },
                $this->articleRepository->findByIds($listArticles)
            ),
            $request->query->get('start'),
            $request->query->get('length')
        );
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route("/{article}/etiquette", name="article_single_bar_code_print", options={"expose"=true})
     * @param Article $article
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSingleArticleBarCode(Article $article,
                                            ArticleDataService $articleDataService,
                                            PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$articleDataService->getBarcodeConfig($article)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }
}
