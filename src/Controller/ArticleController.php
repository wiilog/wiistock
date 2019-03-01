<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
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
    public function articleApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            
            $articles = $this->articleRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $url['edit'] = $this->generateUrl('article_edit', ['id' => $article->getId()] );
                $url['show'] = $this->generateUrl('article_show', ['id' => $article->getId()]);
                $rows[] =
                [
                    'id' => ($article->getId() ? $article->getId() : "Non défini"),
                    'Nom' => ($article->getNom() ? $article->getNom() : "Non défini"),
                    'Statut' => ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : "Non défini"),
                    'Reférence article' => ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : "Non défini"),
                    'Emplacement' => ($article->getPosition() ? $article->getPosition()->getNom() : "Non défini"),
                    'Destination' => ($article->getDirection() ? $article->getDirection()->getNom() : "Non défini"),
                    'Quantité' => ($article->getQuantite() ? $article->getQuantite() : "Non défini"),
                    'Actions' => $this->renderView('article/datatableArticleRow.html.twig', ['url' => $url, 'article' => $article]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    
    /**
     * @Route("/par-collecte", name="articles_by_collecte", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticlesByCollecte(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $collecteId = $request->get('collecteId');
            $collecte = $this->collecteRepository->find($collecteId);
            $articles = $collecte->getArticles();
            $rows = [];
            foreach ($articles as $article) {
                $rows[] = [
                    'Nom'=>( $article->getNom() ?  $article->getNom():""),
                    'Statut'=> ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : ""),
                    'Référence Article'=> ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : ""),
                    'Emplacement'=> ($article->getPosition() ? $article->getPosition()->getNom() : ""),
                    'Destination'=> ($article->getDirection() ? $article->getDirection()->getNom() : ""),
                    'Quantité à collecter'=>($article->getQuantiteCollectee() ? $article->getQuantiteCollectee() : ""),
                    'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', ['article' => $article])
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/nouveau", name="article_new", methods="GET|POST")  INUTILE
     */
    public function new(Request $request) : Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_RECEPTION_EN_COURS);
            $article->setStatut($statut);
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            return $this->redirectToRoute('article_index');
        }

        return $this->render('article/new.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/voir/{id}", name="article_show", methods="GET")
     */
    public function show(Article $article) : Response
    {
        $session = $_SERVER['HTTP_REFERER'];

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'session' => $session
        ]);
    }

    /**
     * @Route("/ajouter", name="modal_add_article")
     */
    public function displayModalAddArticle()
    {
        $articles = $this->articleRepository->findAllSortedByName();

        $html = $this->renderView('collecte/modalAddArticleContent.html.twig', [
            'articles' => $articles
        ]);

        return new JsonResponse(['html' => $html]);
    }

    /**
     * @Route("/modifier/{id}", name="article_edit", methods="GET|POST")
     */
    public function edit(Request $request, Article $article) : Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid())
        {
            $reception = $article->getReception()->getId();
            $reception = $this->receptionRepository->find($reception); //a modifier
            $articles = $reception->getArticles();
            foreach($articles as $article) {
                if($article->getStatut()->getId() == 5) {
                    $statut = $this->statutRepository->find(5); //a modifier
                    $reception->setStatut($statut);
                    break;
                }
                else {
                    $statut = $this->statutRepository->find(6); //a modifier
                    $reception->setStatut($statut);
                }
            }

            $this->getDoctrine()->getManager()->flush();

            return $this->redirect($_POST['url']);
        }

        return $this->render('article/edit.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
            'id' => $article->getReception()->getId(),

        ]);
    }

    /**
     * @Route("/{id}", name="article_delete", methods="DELETE")
     */
    public function delete(Request $request, Article $article) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();
        }
        return $this->redirectToRoute('article_index');
    }

    /**
     * @Route("/modifier-quantite", name="edit_quantity")
     */
    public function editQuantity(Request $request)
    {
        $articleId = $request->request->get('articleId');
        $quantity = $request->request->get('quantity');

        $article = $this->articleRepository->find($articleId);
        $article->setQuantiteCollectee($quantity);

        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();

        return new JsonResponse(true);
    }
}
