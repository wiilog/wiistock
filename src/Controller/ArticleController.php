<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;

use App\Repository\ArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\CollecteRepository;
use App\Repository\ReceptionRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\DimensionsEtiquettesRepository;

use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\UserService;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/article")
 */
class ArticleController extends Controller
{

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

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
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    public function __construct(\Twig_Environment $templating, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, CategorieCLRepository $categorieCLRepository, FournisseurRepository $fournisseurRepository, ChampsLibreRepository $champsLibreRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ArticleDataService $articleDataService, TypeRepository $typeRepository, RefArticleDataService $refArticleDataService, ArticleFournisseurRepository $articleFournisseurRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UserService $userService)
    {
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->collecteRepository = $collecteRepository;
        $this->receptionRepository = $receptionRepository;
        $this->typeRepository = $typeRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->userService = $userService;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->templating = $templating;
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('article/index.html.twig', [
            'valeurChampsLibre' => null,
            'type' => $this->typeRepository->findOneByCategoryLabel(Article::CATEGORIE),
        ]);
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->articleDataService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $article = $this->articleRepository->find($data);

            $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
            $typeArticle = $refArticle->getType()->getLabel();
            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);

            $champsLibresComplet = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($typeArticle, $categorieCL);
            $champsLibres = [];
            foreach ($champsLibresComplet as $champLibre) {
                $valeurChampArticle = $this->valeurChampsLibreRepository->findOneByChampLibreAndArticle($champLibre->getId(), $article->getId());
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
                    'type' => $typeArticle,
                    'champsLibres' => $champsLibres,
                ];
            if ($article) {
                $view = $this->templating->render('article/modalShowArticleContent.html.twig', [
                    'typeChampsLibres' => $typeChampLibre,
                    'typeArticle' => $typeArticle,
                    'article' => $article,
                    'statut' => ($article->getStatut()->getNom() === Article::STATUT_ACTIF ? true : false),
                    // 'isADemand' => $isADemand
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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = $this->articleDataService->newArticle($data);

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="article_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $article = $this->articleRepository->find($data['article']);
            $rows = $article->getId();
            if (count($article->getCollectes()) > 0 || $article->getDemande() !== null) {
                return new JsonResponse(false, 250);
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
    public function getLivraisonArticleByRefArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $refArticle = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($refArticle);

            if ($refArticle) {
                $json = $this->articleDataService->getLivraisonArticleOrNoByRefArticle($refArticle);
            } else {
                $json = false; //TODO gérer erreur retour
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
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
                    'valeurChampsLibre' => null,
                    'type' => $this->typeRepository->findOneByCategoryLabel(Article::CATEGORIE)
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
                $json =  [
                    'error' => 'Aucune référence fournisseur trouvée.'
                ];
            } elseif (count($articleFournisseur) > 0) {
                $typeArticle = $refArticle->getType()->getLabel();
                $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);

                $champsLibres = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($typeArticle, $categorieCL);
                $json = [
                    'content' => $this->renderView(
                        'article/modalNewArticleContent.html.twig',
                        [
                            'typeArticle' => $typeArticle,
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
     * @Route("/ajax-fournisseur-by-refarticl-temp", name="ajax_fournisseur_by_refarticle_tmp", options={"expose"=true})
     */
    public function ajaxFournisseurByRefArticleTemp(Request $request): Response
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
                if ($refArticle) {
                    $json = $this->articleDataService->getCollecteArticleOrNoByRefArticle($refArticle);
                } else {
                    $json = false; //TODO gérer erreur retour
                }

                return new JsonResponse($json, 250);
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajax-article-depuis-id", name="get_article_from_id", options={"expose"=true}, methods="GET|POST")
     */
    public function getArticleRefFromId(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            $data = [];
            $data['articleRef'] = $this->articleRepository->find(intval($dataContent['article']))->getReference();
            $dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
            if ($dimension) {
                $data['height'] = $dimension->getHeight();
                $data['width'] = $dimension->getWidth();
                $data['exists'] = true;
            } else {
                $data['exists'] = false;
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/exporter/{min}/{max}", name="article_export", options={"expose"=true}, methods="GET|POST")
     */
    public function exportAll(Request $request, $max, $min): Response
    {
        if (!$request->isXmlHttpRequest()) {
            $data = [];
            $data['values'] = [];
            $headersCL = [];
            foreach ($this->champsLibreRepository->findAll() as $champLibre) {
                $headersCL[] = $champLibre->getLabel();
            }
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLES_ET_REF_CEA);
            $refs = $this->articleRepository->findAll();
            if ($max > count($refs)) $max = count($refs);
            for ($i = $min; $i < $max; $i++) {
                array_push($data['values'], $this->buildInfos($refs[$i], $listTypes, $headersCL));
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
            $data['headers'] = ['reference', 'libelle', 'quantitée', 'type', 'statut', 'commentaire', 'emplacement'];
            foreach ($this->champsLibreRepository->findAll() as $champLibre) {
                array_push($data['headers'], $champLibre->getLabel());
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    public function buildInfos(Article $ref, $listTypes, $headers)
    {
        $refData[] = $ref->getReference() ? $ref->getReference() : '';
        $refData[] = $ref->getLabel() ? $ref->getLabel() : '';
        $refData[] = $ref->getQuantite() ? $ref->getQuantite() : '';
        $refData[] = $ref->getType() ? ($ref->getType()->getLabel() ? $ref->getType()->getLabel() : '') : '';
        $refData[] = $ref->getStatut() ? $ref->getStatut()->getNom() : '';
        $refData[] = strip_tags($ref->getCommentaire());
        $refData[] = $ref->getEmplacement() ? $ref->getEmplacement()->getLabel() : '';
        $categorieCL = $this->categorieCLRepository->findOneByLabel('article');
        $champsLibres = [];
        foreach ($listTypes as $type) {
            $listChampsLibres = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($type['label'], $categorieCL);
            foreach ($listChampsLibres as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampsLibreRepository->findOneByArticleANDChampsLibre($ref->getId(), $champLibre);
                if ($valeurChampRefArticle) $champsLibres[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
            }
        }
        foreach ($headers as $type) {
            if (array_key_exists($type, $champsLibres)) {
                $refData[] = $champsLibres[$type];
            } else {
                $refData[] = '';
            }
        }
        return implode(';', $refData);
    }
}
