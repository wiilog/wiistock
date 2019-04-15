<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\CollecteRepository;
use App\Repository\ReceptionRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\TypeRepository;

use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Article;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

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
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    public function __construct(ArticleDataService $articleDataService, TypeRepository $typeRepository, RefArticleDataService $refArticleDataService, ArticleFournisseurRepository $articleFournisseurRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository)
    {
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
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        return $this->render('article/index.html.twig');
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            $articles = $this->articleRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $url['edit'] = $this->generateUrl('ligne_article_edit', ['id' => $article->getId()]);

                $rows[] =
                        [
                            'id' => ($article->getId() ? $article->getId() : 'Non défini'),
                            'Référence' => ($article->getReference() ? $article->getReference() : 'Non défini'),
                            'Statut' => ($article->getStatut() ? $article->getStatut()->getNom() : 'Non défini'),
                            'Libellé' => ($article->getLabel() ? $article->getLabel() : 'Non défini'),
                            'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
                            'Quantité' => ($article->getQuantite() ? $article->getQuantite() : 'Non défini'),
                            'Actions' => $this->renderView('article/datatableArticleRow.html.twig', [
                                'url' => $url,
                                'articleId' => $article->getId(),
                            ]),
                        ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $article = $this->articleRepository->find($data);

            $json = $this->renderView('article/modalShowArticleContent.html.twig', [
                'article' => $article,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/get-article", name="get_article_by_refArticle", options={"expose"=true})
     */
    public function getArticleByRefArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            if ($refArticle) {
                $json = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, true);
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/get-article-collecte", name="get_collecte_article_by_refArticle", options={"expose"=true})
     */
    public function getCollecteArticleByRefArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            if ($refArticle) {
                $json = $this->articleDataService->getCollecteArticleOrNoByRefArticle($refArticle, true);
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }
}
