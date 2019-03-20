<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Preparation;
use App\Form\PreparationType;
use App\Repository\PreparationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Tests\Compiler\D;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;

use App\Repository\ArticleRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;

use Doctrine\Common\Collections\ArrayCollection;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\StatutRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    public function __construct(PreparationRepository $preparationRepository, ArticleRepository $articleRepository, StatutRepository $statutRepository, DemandeRepository $demandeRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->preparationRepository = $preparationRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
    }

    /**
     * @Route("/creationpreparation", name="createPreparation", methods="POST") //INUTILE CEA
     */
    public function createPreparation(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
            {
                // creation d'une nouvelle preparation basée sur une selection de demandes
                $preparation = new Preparation();

                //declaration de la date pour remplir Date et Numero
                $date = new \DateTime('now');
                $preparation->setNumero('P-' . $date->format('YmdHis'));
                $preparation->setDate($date);
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_NOUVELLE);
                $preparation->setStatut($statut);
                //Plus de detail voir creation demande meme principe

                foreach ($data as $key) {
                        $demande = $this->demandeRepository->find($key);
                        // On avance dans le tableau
                        $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
                        $demande
                            ->setPreparation($preparation)
                            ->setStatut($statut);

                        $articles = $demande->getArticles();
                        foreach ($articles as $article) {
                                $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
                                $article
                                    ->setStatut($statut)
                                    ->setDirection($demande->getDestination());
                            }
                    }

                $em = $this->getDoctrine()->getManager();
                $em->persist($preparation);
                $em->flush();

                $data = [
                    "preparation" => [
                        "id" => $preparation->getId(),
                        "numero" => $preparation->getNumero(),
                        "date" => $preparation->getDate()->format("d/m/Y H:i:s"),
                        "Statut" => $preparation->getStatut()->getNom()
                    ],
                    "message" => "Votre préparation à été enregistrer"
                ];
                $data = json_encode($data);
                return new JsonResponse($data);
            }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="preparation_index", methods="GET|POST")
     */
    public function index(Request $request): Response
    {
        return $this->render('preparation/index.html.twig');
    }

    /**
     * @Route("/api", name="preparation_api", options={"expose"=true}, methods="GET|POST")
     */
    public function preparationApi(Request $request, PreparationRepository $preparationRepository): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $preparations = $preparationRepository->findAll();
                $rows = [];
                foreach ($preparations as $preparation) {
                        $url['show'] = $this->generateUrl('preparation_show', ['id' => $preparation->getId()]);
                        $rows[] = [
                            'Numéro' => ($preparation->getNumero() ? $preparation->getNumero() : ""),
                            'Date' => ($preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : ''),
                            'Statut' => ($preparation->getStatut() ? $preparation->getStatut()->getNom() : ""),
                            'Actions' => $this->renderView('preparation/datatablePreparationRow.html.twig', ['url' => $url]),
                        ];
                    }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/ajoutarticle", name="preparation_ajout_article", options={"expose"=true}, methods="GET|POST")
     */
    public function ajoutArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
                $article = $this->articleRepository->find($data['article']);
                $preparation = $this->preparationRepository->find($data['preparation']);
                $preparation->addArticle($article);
                $em = $this->getDoctrine()->getManager();
                $em->flush();


                return new JsonResponse();
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/articleDelete", name="preparation_ajout_article_delete", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function deleteArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
                $article = $this->articleRepository->find($data['article']);
                $preparation = $this->preparationRepository->find($data['preparation']);
                $preparation->removeArticle($article);
                $em = $this->getDoctrine()->getManager();
                $em->flush();
           
                return new JsonResponse();
            }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api_article/{id}", name="preparation_article_api", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function LignePreparationApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $articles = $this->articleRepository->getByPreparation($id);
                $rows = [];

                foreach ($articles as $article) {
                        $rows[] = [
                            "Référence" => ($article->getReference() ? $article->getReference() : ' '),
                            "Quantité" => ($article->getQuantite() ? $article->getQuantite() : ' '),
                            "Action" =>  $this->renderView('preparation/datatableArticleRow.html.twig', ['articleId' => $article->getId()]),
                        ];
                    }

                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/{id}", name="preparation_show", methods="GET|POST")
     */
    public function show(Preparation $preparation): Response
    {
        return $this->render('preparation/show.html.twig', [
            'preparation' => $preparation,
            'articles' => $this->articleRepository->getArticleByRefId(),
        ]);
    }
}
