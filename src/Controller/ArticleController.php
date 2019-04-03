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

use App\Entity\ReferenceArticle;

use App\Service\RefArticleDataService;

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

    public function __construct(TypeRepository $typeRepository ,RefArticleDataService $refArticleDataService, ArticleFournisseurRepository $articleFournisseurRepository,ReferenceArticleRepository $referenceArticleRepository,ReceptionRepository $receptionRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository)
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
     * @Route("/show", name="article_show", options={"expose"=true},  methods="GET|POST")
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

    /**
     * @Route("/get-article", name="get_article_by_refArticle", options={"expose"=true})
     */
    public function getArticleByRefArticle(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);

            if ($refArticle) {
                $articleFournisseur = $this->articleFournisseurRepository->getByRefArticle($refArticle);

                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
                    $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);
                    $json = $this->renderView('collecte/newRefArticleByQuantiteRefContent.html.twig', [
                        'articleRef' => $refArticle,
                        'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                        'valeurChampsLibre' => isset($valeurChampLibre) ? $data['valeurChampLibre'] : null,
                        'types' => $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE),
                        'statuts' => $statuts,
                        'articlesFournisseur' => $data['listArticlesFournisseur'],
                        'totalQuantity' => $data['totalQuantity']
                    ]);

                } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                    $articles = $this->articleRepository->getIdAndLibelleByRefArticle($articleFournisseur);
                    $json = $this->renderView('collecte/newRefArticleByQuantiteArticleContent.html.twig', [
                        "articles" => $articles,
                    ]);
                } else {
                    $json = false; //TODO gérer erreur retour
                }
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");

        
    }



    
    // /**
    //  * @Route("/par-collecte", name="articles_by_collecte", options={"expose"=true}, methods={"GET", "POST"})
    //  */
    // public function getArticlesByCollecte(Request $request): Response
    // {
    //     if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
    //     {
    //         $collecteId = $request->get('collecteId');
    //         $collecte = $this->collecteRepository->find($collecteId);
    //         $articles = $collecte->getArticles();
    //         $rows = [];
    //         foreach ($articles as $article) {
    //             $rows[] = [
    //                 'référence'=>( $article->Reference() ?  $article->getReference():""),
    //                 'Statut'=> ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : ""),
    //                 'Référence Article'=> ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : ""),
    //                 'Quantité à collecter'=>($article->getQuantiteCollectee() ? $article->getQuantiteCollectee() : ""),
    //                 'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', ['article' => $article])
    //             ];
    //         }
    //         $data['data'] = $rows;
    //         return new JsonResponse($data);
    //     }
    //     throw new NotFoundHttpException("404");
    // }

    // /**
    //  * @Route("/nouveau", name="article_new", methods="GET|POST")  INUTILE
    //  */
    // public function new(Request $request) : Response
    // {
    //     $article = new Article();
    //     $form = $this->createForm(ArticleType::class, $article);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_RECEPTION_EN_COURS);
    //         $article->setStatut($statut);
    //         $em = $this->getDoctrine()->getManager();
    //         $em->persist($article);
    //         $em->flush();
    //         return $this->redirectToRoute('article_index');
    //     }

    //     return $this->render('article/new.html.twig', [
    //         'article' => $article,
    //         'form' => $form->createView(),
    //     ]);
    // }

   

    // /**
    //  * @Route("/ajouter", name="modal_add_article")
    //  */
    // public function displayModalAddArticle()
    // {
    //     $articles = $this->articleRepository->findAllSortedByName();

    //     $html = $this->renderView('collecte/modalAddArticleContent.html.twig', [
    //         'articles' => $articles
    //     ]);

    //     return new JsonResponse(['html' => $html]);
    // }

    // /**
    //  * @Route("/modifier/{id}", name="article_edit", methods="GET|POST")
    //  */
    // public function edit(Request $request, Article $article) : Response
    // {
    //     $form = $this->createForm(ArticleType::class, $article);
    //     $form->handleRequest($request);
    //     if ($form->isSubmitted() && $form->isValid())
    //     {
    //         $reception = $article->getReception()->getId();
    //         $reception = $this->receptionRepository->find($reception); //a modifier
    //         $articles = $reception->getArticles();
    //         foreach($articles as $article) {
    //             if($article->getStatut()->getId() == 5) {
    //                 $statut = $this->statutRepository->find(5); //a modifier
    //                 $reception->setStatut($statut);
    //                 break;
    //             }
    //             else {
    //                 $statut = $this->statutRepository->find(6); //a modifier
    //                 $reception->setStatut($statut);
    //             }
    //         }

    //         $this->getDoctrine()->getManager()->flush();

    //         return $this->redirect($_POST['url']);
    //     }

    //     return $this->render('article/edit.html.twig', [
    //         'article' => $article,
    //         'form' => $form->createView(),
    //         'id' => $article->getReception()->getId(),

    //     ]);
    // }

    // /**
    //  * @Route("/{id}", name="article_delete", methods="DELETE")
    //  */
    // public function delete(Request $request, Article $article) : Response
    // {
    //     if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
    //         $em = $this->getDoctrine()->getManager();
    //         $em->remove($article);
    //         $em->flush();
    //     }
    //     return $this->redirectToRoute('article_index');
    // }

//     /**
//      * @Route("/modifier-quantite", name="edit_quantity")
//      */
//     public function editQuantity(Request $request)
//     {
//         $articleId = $request->request->get('articleId');
//         $quantity = $request->request->get('quantity');

//         $article = $this->articleRepository->find($articleId);
//         $article->setQuantiteCollectee($quantity);

//         $em = $this->getDoctrine()->getManager();
//         $em->persist($article);
//         $em->flush();

//         return new JsonResponse(true);
//     }
}
