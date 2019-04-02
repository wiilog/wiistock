<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\CollecteRepository;
use App\Repository\ReceptionRepository;
use App\Repository\EmplacementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;
    
    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    public function __construct(ReceptionRepository $receptionRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository)
    {
       
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->collecteRepository = $collecteRepository;
        $this->receptionRepository = $receptionRepository;
        
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     */
    public function index(Request $request) : Response
    {
        return $this->render('article/index.html.twig');
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $articles = $this->articleRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $url['edit'] = $this->generateUrl('article_edit', ['id' => $article->getId()] );
                
                $rows[] =
                [
                    'id' => ($article->getId() ? $article->getId() : "Non défini"),
                    'Référence' => ($article->getReference() ? $article->getReference() : "Non défini"),
                    'Statut' => ($article->getStatut() ? $article->getStatut()->getNom() : "Non défini"),
                    'Libellé' => ($article->getLabel() ? $article->getLabel() : "Non défini"),
                    'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : "Non défini"),
                    'Quantité' => ($article->getQuantite() ? $article->getQuantite() : "Non défini"),
                    'Actions' => $this->renderView('article/datatableArticleRow.html.twig', [
                        'url' => $url, 
                        'articleId' => $article->getId(),
                        ]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
           
            $article = $this->articleRepository->find($data);         
          
            $json =$this->renderView('article/modalShowArticleContent.html.twig', [
                'article' => $article
                
                ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

}
