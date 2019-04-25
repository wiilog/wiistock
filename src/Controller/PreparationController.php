<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Form\PreparationType;
use App\Repository\PreparationRepository;
use App\Service\UserService;
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

use App\Entity\Demande;
use App\Repository\DemandeRepository;

use Doctrine\Common\Collections\ArrayCollection;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\StatutRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\LigneArticleRepository;

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
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

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

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(PreparationRepository $preparationRepository, LigneArticleRepository $ligneArticleRepository, ArticleRepository $articleRepository, StatutRepository $statutRepository, DemandeRepository $demandeRepository, ReferenceArticleRepository $referenceArticleRepository, UserService $userService)
    {
        $this->statutRepository = $statutRepository;
        $this->preparationRepository = $preparationRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/creer", name="", methods="POST") //INUTILE CEA
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
            {
                if (!$this->userService->hasRightFunction(Menu::PREPA, Action::CREATE)) {
                    return $this->redirectToRoute('access_denied');
                }

                // creation d'une nouvelle preparation basée sur une selection de demandes
                $preparation = new Preparation();

                //declaration de la date pour remplir Date et Numero
                $date = new \DateTime('now');
                $preparation->setNumero('P-' . $date->format('YmdHis'));
                $preparation->setDate($date);
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
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
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }
        return $this->render('preparation/index.html.twig');
    }

    /**
     * @Route("/api", name="preparation_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
                    return $this->redirectToRoute('access_denied');
                }

                $preparations = $this->preparationRepository->findAll();
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


    //    /**
    //     * @Route("/ajoute-article", name="preparation_add_article", options={"expose"=true}, methods="GET|POST")
    //     */
    //    public function addArticle(Request $request): Response
    //    {
    //        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
    //            $article = $this->articleRepository->find($data['article']);
    //            $preparation = $this->preparationRepository->find($data['preparation']);
    //            $preparation->addArticle($article);
    //            $em = $this->getDoctrine()->getManager();
    //            $em->flush();
    //
    //
    //            return new JsonResponse();
    //        }
    //        throw new NotFoundHttpException("404");
    //    }

    //    /**
    //     * @Route("/supprime-article", name="preparation_delete_article", options={"expose"=true}, methods={"GET", "POST"})
    //     */
    //    public function deleteArticle(Request $request): Response
    //    {
    //        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
    //                $article = $this->articleRepository->find($data['article']);
    //                $preparation = $this->preparationRepository->find($data['preparation']);
    //                $preparation->removeArticle($article);
    //                $em = $this->getDoctrine()->getManager();
    //                $em->flush();
    //
    //                return new JsonResponse();
    //            }
    //        throw new NotFoundHttpException("404");
    //    }


    /**
     * @Route("/api_article/{id}", name="preparation_article_api", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function lignePreparationApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
                    return $this->redirectToRoute('access_denied');
                }

                $demande = $this->demandeRepository->find($id);
                if ($demande) {
                    $ligneArticles = $this->ligneArticleRepository->getByDemande($demande->getId());

                    $rows = [];
                    foreach ($ligneArticles as $article) {
                        $rows[] = [
                            "Référence CEA" => ($article->getReference() ? $article->getReference()->getReference() : ' '),
                            "Libellé" => ($article->getReference() ? $article->getReference()->getLibelle() : ' '),
                            "Quantité" => ($article->getQuantite() ? $article->getQuantite() : ' '),
                            "Actions" => "",
                        ];
                    }

                    $data['data'] = $rows;
                } else {
                    $data = false; //TODO gérer affichage erreur
                }
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="preparation_show", methods="GET|POST")
     */
    public function show(Preparation $preparation): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('preparation/show.html.twig', [
            'demande'=> $this->demandeRepository->findOneByPreparation($preparation),
            'preparation' => $preparation,
            'finished' => ($preparation->getStatut()->getNom() === Preparation::STATUT_A_TRAITER),
            'articles' => $this->articleRepository->getArticleByRefId(),
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="preparation_delete", methods="GET|POST")
     */
    public function delete(Preparation $preparation): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $em = $this->getDoctrine()->getManager();
        foreach ($preparation->getDemandes() as $demande) {
            $demande->setPreparation(null);
        }
        $em->remove($preparation);
        $em->flush();
        return $this->redirectToRoute('preparation_index');
    }
}
