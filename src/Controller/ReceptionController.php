<?php

namespace App\Controller;

use App\Entity\Reception;
use App\Form\ReceptionType;
use App\Repository\ReceptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\UtilisateurRepository;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;

use Knp\Component\Pager\PaginatorInterface;
use App\Repository\StatutRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/reception")
 */
class ReceptionController extends AbstractController
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
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    public function __construct(FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, ArticleRepository $articleRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->receptionRepository = $receptionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleRepository = $articleRepository;
    }


    /**
     * @Route("/creationReception", name="createReception", options={"expose"=true}, methods="POST")
     */
    public function createReception(Request $request) : Response
    {
        if ($data = json_decode($request->getContent(), true)) //Si data est attribuée
        {
            if (count($data) != 5)// On regarde si le nombre de données reçu est conforme et on envoi dans la base
            {
                $em = $this->getDoctrine()->getManager();
                $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
                $utilisateur = $this->utilisateurRepository->find(intval($data['utilisateur']));

                $reception = new Reception();
                $statut = $this->statutRepository->find(1); // L'id correspondant au statut En cours de réception
                $reception
                    ->setStatut($statut)
                    ->setNumeroReception($data['NumeroReception'])
                    ->setDate(new \DateTime($data['date-commande']))
                    ->setDateAttendu(new \DateTime($data['date-attendu']))
                    ->setFournisseur($fournisseur)
                    ->setUtilisateur($utilisateur)
                    ->setCommentaire($data['commentaire']);
                $em->persist($reception);

                // On enregistre l'entité crée sur la bdd
                $em->flush();

                $data = [
                    "redirect" => $this->generateUrl('reception_ajout_article', ['id' => $reception->getId(), 'k' => "0"])
                ];
                
                return new JsonResponse($data);
            }
        }

        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/modifierReception", name="modifierReception", methods="POST")
     */
    public function modifierReception(Request $request, FournisseurRepository $fournisseurRepository) : Response // SERT QUAND
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            if (count($data) != 5)// On regarde si le nombre de données reçu est conforme et on envoi dans la base
            {
                $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
                $utilisateur = $this->utilisateurRepository->find(intval($data['utilisateur']));

                $reception = new Reception();
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_RECEPTION_EN_COURS);
                $reception
                    ->setStatut($statut)
                    ->setNumeroReception($data['NumeroReception'])
                    ->setDate(new \DateTime($data['date-commande']))
                    ->setDateAttendu(new \DateTime($data['date-attendu']))
                    ->setFournisseur($fournisseur)
                    ->setUtilisateur($utilisateur)
                    ->setCommentaire($data['commentaire']);
                $em = $this->getDoctrine()->getManager();
                $em->persist($reception);
                $em->flush();

                $data = json_encode($data);
                return new JsonResponse($data);
            }
        }

        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function receptionApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $receptions = $this->receptionRepository->findAll();
            $rows = [];
            foreach ($receptions as $reception) {
                $url['edit'] = $this->generateUrl('reception_edit', ['id' => $reception->getId()]);
                $url['show'] = $this->generateUrl('reception_ajout_article', ['id' => $reception->getId(), 'k'=>'0'] );
                $rows[] =
                    [
                    'id' => ($reception->getId()),
                    "Statut" => ($reception->getStatut() ? $reception->getStatut()->getNom() : ''),
                    "Date commande" => ($reception->getDate() ? $reception->getDate() : '')->format('d/m/Y'),
                    "Date attendue" => ($reception->getDateAttendu() ? $reception->getDateAttendu()->format('d/m/Y') : ''),
                    "Fournisseur" => ($reception->getFournisseur() ? $reception->getFournisseur()->getNom() : ''),
                    "Référence" => ($reception->getNumeroReception() ? $reception->getNumeroReception() : ''),
                    'Actions' => $this->renderView('reception/datatableReceptionRow.html.twig', ['url' => $url, 'reception' => $reception]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/json", name="reception_json", methods={"GET", "POST"}) 
     */
    public function receptionJson(Request $request) : Response
    {// recuperation du fichier JSON via la requete
        if (!$request->isXmlHttpRequest()) {
            // decodage en tableau php
            $myJSON = json_decode($request->getContent(), true);
            // traitement des données => récuperation des objets via leur id 
            $refArticle= $this->referenceArticleRepository->find($myJSON['refArticle']);
            $reception= $this->receptionRepository->find($myJSON['reception']);
            // creation d'un nouvel objet article + set des donnees
            $article = new Article();
            $article
                ->setNom($myJSON['nom'])
                ->setRefArticle($refArticle)
                ->setQuantite(intval($myJSON['quantite']))
                ->setQuantiteARecevoir(intval($myJSON['quantiteARecevoir']))
                ->setEtat($myJSON['etat'])
                ->setCommentaire($myJSON['commentaire'])
                ->setReception($reception);
            if ($article->getEtat())
            {
                $statut = $this->statutRepository->find(1);
                $article->setStatut($statut);

            }
            else 
            {
                $statut = $this->statutRepository->find(5);
                $article->setStatut($statut);
                $reception->setStatut($statut);
            }
            // flush du nouvel objet article dans la base
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            // contruction de la reponse =>recuperation de l'article cree + traitement des donnees
            $reponseJSON = [
                'id' => $article->getId(),
                'nom' => $article->getNom(),
                'statut' => $article->getStatut()->getNom(),
                'quantite' => $article->getQuantite(),
                'quantiteARecevoir' => $article->getQuantiteARecevoir(),

            ];
            // encodage de la reponse en JSON + envoie
            return new JsonResponse($reponseJSON);
        }
        throw new NotFoundHttpException('404 not found');
    }


    /**
     * @Route("/", name="reception_index", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {
        return $this->render('reception/index.html.twig', [
            'fournisseurs' => $this->fournisseurRepository->findAll(), //a précisé avant modif
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="reception_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Reception $reception) : Response
    {

        if(isset($_POST["numeroReception"], $_POST["fournisseur"], $_POST["utilisateur"], $_POST["date-attendue"]))
        {
            $fournisseur = $this->fournisseurRepository->find($_POST["fournisseur"]);
            $utilisateur = $this->utilisateurRepository->find($_POST["utilisateur"]);
            $reception
                ->setNumeroReception($_POST["numeroReception"])
                ->setDate(new \DateTime($_POST["date-commande"]))
                ->setFournisseur($fournisseur)
                ->setUtilisateur($utilisateur)
                ->setCommentaire($_POST["commentaire"])
                ->setDateAttendu(new \DateTime($_POST["date-attendue"]));
            $em = $this->getDoctrine()->getManager();
            $em->persist($reception);
            $em->flush();

            return $this->redirectToRoute('reception_ajout_article', [
                "id" => $reception->getId(),
                "k" => 0
            ]); 
        }

        return $this->render('reception/edit.html.twig', [
            "reception" => $reception,
            "utilisateurs" => $this->utilisateurRepository->getIdAndUsername(),
            "fournisseurs" => $this->fournisseurRepository->findAll(), //a précisé avant modif
        ]); 
    }


    /**
     * @Route("/{id}", name="reception_delete", methods="DELETE")
     */
    public function delete(Request $request, Reception $reception) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $reception->getId(), $request->request->get('_token'))) 
        {
            $em = $this->getDoctrine()->getManager();
            if(count($reception->getArticles()) > 0)
            {   
                $articles = $reception->getArticles();
                foreach($articles as $article)
                {
                    $em->remove($article);
                }
            }

            $em->remove($reception);
            $em->flush();
        }
        return $this->redirectToRoute('reception_index');
    }


    /**
     * @Route("/article/{id}/{finishReception}", name="reception_ajout_article", methods={"GET", "POST"})
     */
    public function ajoutArticle(Request $request, Reception $reception, $id, $finishReception = 0): Response
    {
        //fin de reception/mise en stock des article
        // k sert à vérifier et identifier la fin de la reception, en suite on modifie les "setStatut" des variables 
        if ($finishReception) {
            $articles = $this->articleRepository->findByReception($id);
            // modification du statut
            foreach ($articles as $article) 
            {
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_RECEPTION_EN_COURS);
                //vérifie si l'article est bien encore en reception

                if ($article->getStatut() === $statut  && $article->getEtat() === true)
                {
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_EN_STOCK);
                    $article->setStatut($statut);
                }
            }

            $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::TERMINE);
            $reception->setStatut($statut);
            $reception->setDateReception(new \DateTime('now'));

            //calcul de la quantite des stocks par articles de reference
            $refArticles = $this->referenceArticleRepository->findAll();
            foreach ($refArticles as $refArticle)
            {
                $quantity = $this->articleRepository->countByRefArticleAndStatut($refArticle, Article::STATUT_EN_STOCK);
                $refArticle->setQuantiteDisponible($quantity);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('reception_index');
        }
        return $this->render("reception/ajoutArticle.html.twig", [
            'reception' => $reception,
            'refArticle'=> $this->referenceArticleRepository->findAll(),
            'id' => $id,
        ]);
    }


}

