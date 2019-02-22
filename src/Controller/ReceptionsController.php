<?php

namespace App\Controller;

use App\Entity\Receptions;
use App\Form\ReceptionsType;
use App\Repository\ReceptionsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseursRepository;
use App\Repository\UtilisateursRepository;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;

use Knp\Component\Pager\PaginatorInterface;
use App\Repository\StatutsRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/receptions")
 */
class ReceptionsController extends AbstractController
{
    /**
     * @var StatutsRepository
     */
    private $statutsRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var UtilisateursRepository
     */
    private $utilisateursRepository;

    /**
     * @var ReferencesArticlesRepository
     */
    private $referencesArticlesRepository;

    /**
     * @var ReceptionsRepository
     */
    private $receptionsRepository;
    
    /**
     * @var FournisseursRepository
     */
    private $fournisseursRepository;

    public function __construct(FournisseursRepository $fournisseursRepository,StatutsRepository $statutsRepository, ReferencesArticlesRepository $referencesArticlesRepository, ReceptionsRepository $receptionsRepository, UtilisateursRepository $utilisateursRepository, EmplacementRepository $emplacementRepository)
    {
        $this->statutsRepository = $statutsRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->receptionsRepository = $receptionsRepository;
        $this->utilisateursRepository = $utilisateursRepository;
        $this->referencesArticlesRepository = $referencesArticlesRepository;
        $this->fournisseursRepository = $fournisseursRepository;
    }


    /**
     * @Route("/creationReception", name="createReception", methods="POST")
     */
    public function createReception(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            if (count($data) != 5)// On regarde si le nombre de données reçu est conforme et on envoi dans la base
            {
                $em = $this->getDoctrine()->getManager();
                $fournisseur = $this->fournisseursRepository->find(intval($data[3]['fournisseur']));
                $utilisateur = $this->utilisateursRepository->find(intval($data[4]['utilisateur']));

                $reception = new Receptions();
                $statut = $this->statutsRepository->find(1); //a modifier
                $reception
                    ->setStatut($statut)
                    ->setNumeroReception($data[0]['NumeroReception'])
                    ->setDate(new \DateTime($data[1]['date-commande']))
                    ->setDateAttendu(new \DateTime($data[2]['date-attendu']))
                    ->setFournisseur($fournisseur)
                    ->setUtilisateur($utilisateur)
                    ->setCommentaire($data[5]['commentaire']);
                $em->persist($reception);
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
    public function modifierReception(Request $request, FournisseursRepository $fournisseursRepository) : Response // SERT QUAND
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            if (count($data) != 5)// On regarde si le nombre de données reçu est conforme et on envoi dans la base
            {
                $fournisseur = $fournisseursRepository->find(intval($data[3]['fournisseur']));
                $utilisateur = $this->utilisateursRepository->find(intval($data[4]['utilisateur']));

                $reception = new Receptions();
                $statut = $this->statutsRepository->find(1);
                $reception
                    ->setStatut($statut[0])
                    ->setNumeroReception($data[0]['NumeroReception'])
                    ->setDate(new \DateTime($data[1]['date-commande']))
                    ->setDateAttendu(new \DateTime($data[2]['date-attendu']))
                    ->setFournisseur($fournisseur)
                    ->setUtilisateur($utilisateur)
                    ->setCommentaire($data[5]['commentaire']);
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
     * @Route("/api", name="reception_api", methods={"GET", "POST"}) 
     */
    public function receptionApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $receptions = $this->receptionsRepository->findAll();
            $rows = [];
            foreach ($receptions as $reception) {
                $urlShow = $this->generateUrl('reception_ajout_article', ['id' => $reception->getId(), 'k'=>'0'] );
                $row =
                    [
                        'id' => ($reception->getId()),
                        "Statut" => ($reception->getStatut() ? $reception->getStatut()->getNom() : ''),
                        "Date commande" => ($reception->getDate() ? $reception->getDate() : '')->format('d/m/Y'),
                        "Date attendue" => ($reception->getDateAttendu() ? $reception->getDateAttendu()->format('d/m/Y') : ''),
                        "Fournisseur" => ($reception->getFournisseur() ? $reception->getFournisseur()->getNom() : ''),
                        "Référence" => ($reception->getNumeroReception() ? $reception->getNumeroReception() : ''),
                    'Actions' => "<button  onclick='modifyReception($(this))' data-toggle='modal'
                                                                                data-target='#modalModifyReception' 
                                                                                data-id=".$reception->getId()." 
                            class='btn btn-xs btn-default command-edit '><i class='fas fa-pencil-alt fa-2x'></i></button>
                    <a href='" . $urlShow . "' class='btn btn-xs btn-default command-edit'><i class='fas fa-plus fa-2x'></i> Articles</a>",
                ];
                array_push($rows, $row);
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
            $refArticle= $this->referencesArticlesRepository->find($myJSON['refArticle']);
            $reception= $this->receptionsRepository->find($myJSON['reception']);
            // creation d'un nouvel objet article + set des donnees
            $article = new Articles();
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
                $statut = $this->statutsRepository->find(1);
                $article->setStatut($statut);

            }
            else 
            {
                $statut = $this->statutsRepository->find(5);
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
     * @Route("/", name="receptions_index", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {
        return $this->render('receptions/index.html.twig', [
            'fournisseurs' => $this->fournisseursRepository->findAll(), //a précisé avant modif
            'utilisateurs' => $this->utilisateursRepository->findUserGetIdUser(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="receptions_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Receptions $reception) : Response
    {

        if(isset($_POST["numeroReception"], $_POST["fournisseur"], $_POST["utilisateur"], $_POST["date-attendue"]))
        {
            $fournisseur = $this->fournisseursRepository->find($_POST["fournisseur"]);
            $utilisateur = $this->utilisateursRepository->find($_POST["utilisateur"]);
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

        return $this->render('receptions/edit.html.twig', [
            "reception" => $reception,
            "utilisateurs" => $this->utilisateursRepository->findUserGetIdUser(),
            "fournisseurs" => $this->fournisseursRepository->findAll(), //a précisé avant modif
        ]); 
    }


    /**
     * @Route("/{id}", name="receptions_delete", methods="DELETE")
     */
    public function delete(Request $request, Receptions $reception) : Response
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
        return $this->redirectToRoute('receptions_index');
    }


    /**
     * @Route("/article/{id}/{k}", name="reception_ajout_article", methods={"GET", "POST"})
     */
    public function ajoutArticle(Request $request, Receptions $reception, $id, $k): Response
    {
        //fin de reception/mise en stock des articles
        // k sert à vérifier et identifier la fin de la reception, en suite on modifie les "setStatut" des variables 
        if ($k) {
            $articles = $this->articlesRepository->findByReception($id);
            // modification du statut
            foreach ($articles as $article) 
            {
                $statut = $this->statutsRepository->findOneById(1);
                //vérifie si l'article est bien encore en reception

                if ($article->getStatut() === $statut  && $article->getEtat() === true)
                {
                    $statut = $this->statutsRepository->find(3);
                    $article->setStatut($statut);
                }
            }

            $statut = $this->statutsRepository->findOneById(7);
            $reception->setStatut($statut);
            $reception->setDateReception(new \DateTime('now'));

            //calcul de la quantite des stocks par artciles de reference
            $refArticles = $this->referencesArticlesRepository->findAll();
            foreach ($refArticles as $refArticle)
            {
                // requete Count en SQL dédié
                $quantityRef = $this->articlesRepository->findCountByRefArticle($refArticle);
                $quantity = $quantityRef[0];
                $refArticle->setQuantiteDisponible($quantity[1]);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('receptions_index', ['history' => 0]);
        }
        return $this->render("receptions/ajoutArticle.html.twig", [
            'reception' => $reception,
            'refArticle'=> $this->referencesArticlesRepository->findAll(),
            'id' => $id,
        ]);
    }


}

