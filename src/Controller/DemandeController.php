<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Form\DemandeType;
use App\Repository\DemandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Articles;
use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;
use App\Repository\FournisseursRepository;
use App\Repository\StatutsRepository;

use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Entity\Preparation;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\UtilisateursRepository;

use App\Entity\Livraison;
use App\Form\LivraisonType;
use App\Repository\LivraisonRepository;

use Knp\Component\Pager\PaginatorInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
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

    public function __construct(StatutsRepository $statutsRepository, ReferencesArticlesRepository $referencesArticlesRepository, UtilisateursRepository $utilisateursRepository, EmplacementRepository $emplacementRepository)
    {
        $this->statutsRepository = $statutsRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateursRepository = $utilisateursRepository;
        $this->referencesArticlesRepository = $referencesArticlesRepository;
    }

    /**
     * @Route("/preparation/{id}", name="preparationFromDemande")
     */
    public function creationPreparationDepuisDemande(Demande $demande): Response
    {  
        if($demande->getPreparation() == null && count($demande->getLigneArticle()) > 0)
        {
            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            $date = new \DateTime('now');
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $preparation->setUtilisateur($this->getUser());

            $statut = $this->statutsRepository->findById(11); /* Statut : nouvelle préparation */
            $preparation->setStatut($statut[0]);

            $demande->setPreparation($preparation);
            
            $statut = $this->statutsRepository->findById(15); /* Statut : Demande de préparation */
            $demande->setStatut($statut[0]);

            $em = $this->getDoctrine()->getManager();
            $em->persist($demande);
            $em->persist($preparation);
            $em->flush();
            return $this->render('preparation/show.html.twig', ['preparation' => $demande->getPreparation()]);
        }
        return $this->show($demande);
    }

    /**
     * @Route("/ajoutArticle/{id}", name="ajoutArticle", methods="GET|POST")
     */
    public function ajoutRefArticle(Demande $demande, FournisseursRepository $fournisseursRepository, Request $request): Response 
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            if(count($data) >= 2)
            {
                $em = $this->getDoctrine()->getEntityManager();

                $json = [
                    "reference" => $data[0]["reference"],
                    "quantite" => $data[1]["quantite"],
                ];

                $referenceArticle = $refArticlesRepository->findOneBy(["id" => $data[0]["reference"]]);

                $quantiteReservee = intval($data[1]["quantite"]);
                $quantiteArticleReservee = $referenceArticle->getQuantiteReservee();
                $referenceArticle->setQuantiteReservee($quantiteReservee + $quantiteArticleReservee);

                $demande->addLigneArticle($json);
                $em->persist($referenceArticle);
                $em->persist($demande);
                $em->flush();

                return new JsonResponse($json);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifDemande/{id}", name="modifDemande", methods="GET|POST")
     */
    public function modifDemande(Demande $demande, Request $request): Response 
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            if(count($data) >= 3)
            {
                $em = $this->getDoctrine()->getEntityManager();
                $utilisateur = $this->utilisateursRepository->findById(intval($data[0]["demandeur"]));
                $statut = $this->statutsRepository->findById($data[2]["statut"]);
                $demande->setUtilisateur($utilisateur[0]);
                $demande->setDateAttendu(new \Datetime($data[1]["date-attendu"]));
                $demande->setStatut($statut[0]);
                $em->persist($demande);
                $em->flush();

                $data = json_encode($data);
                return new JsonResponse($data);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creationDemande", name="creation_demande", methods="GET|POST")
     */
    public function creationDemande(LivraisonRepository $livraisonRepository, Request $request, ArticlesRepository $articlesRepository): Response 
    {   
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
        {
            $em = $this->getDoctrine()->getManager();
            $demandeur = $data[0];
            $demande = new Demande();
            $statut = $this->statutsRepository->findById(14);
            $demande->setStatut($statut[0]);
            $utilisateur = $this->utilisateursRepository->findOneById($demandeur["demandeur"]);
            $demande->setUtilisateur($utilisateur);
            $date =  new \DateTime('now');
            $demande->setdate($date);
            $destination = $this->emplacementRepository->findOneById($data[1]["destination"]);
            $demande->setDestination($destination);
            $demande->setNumero("D-" . $date->format('YmdHis')); // On recupere un array sous la forme ['id de l'article de réference' => 'quantite de l'article de réference voulu', ....]
            $em->persist($demande);
            $em->flush();
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/{history}/index", name="demande_index", methods={"GET"})
     */
    public function index(DemandeRepository $demandeRepository, PaginatorInterface $paginator, Request $request, ArticlesRepository $articlesRepository, $history): Response
    {
        
        $demandeQuery = ($history === 'true') ? $demandeRepository->findAll() : $demandeRepository->findAllByUserAndStatut($this->getUser());

        $pagination = $paginator->paginate(
            $demandeQuery, /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            10
        );

        if ($history === 'true') 
        {
            return $this->render('demande/index.html.twig', [
                'demandes' => $pagination,
                'history' => 'false',
                'utilisateurs' => $this->utilisateursRepository->findAll(),
                'emplacements' => $this->emplacementRepository->findEptBy(),
            ]);
        }
        else
        {
            return $this->render('demande/index.html.twig', [
                'demandes' => $pagination,
                'utilisateurs' => $this->utilisateursRepository->findAll(),
                'emplacements' => $this->emplacementRepository->findEptBy(),
            ]);
        }
    }


    /**
     * @Route("/show/{id}", name="demande_show", methods={"GET"})
     */
    public function show(Demande $demande): Response
    {
        $ligneArticle = $demande->getLigneArticle();
        $lignes = [];
        
        foreach ($ligneArticle as $ligne) 
        {
            $refArticle = $this->referencesArticlesRepository->findById($ligne["reference"]);
            $data = [
                "reference" => $ligne["reference"],
                "quantite" =>$ligne["quantite"],
                "libelle" => $refArticle[0]->getLibelle(), 
            ];
            array_push($lignes, $data);
        }

        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'lignesArticles' => $lignes,
            'utilisateurs' => $this->utilisateursRepository->findAll(),
            'statuts' => $this->statutsRepository->findAll(),
            'references' => $this->referencesArticlesRepository->findAll()
        ]);
    }

    /**
     * @Route("/{id}", name="demande_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Demande $demande): Response
    {
        if ($this->isCsrfTokenValid('delete'.$demande->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
        }

        return $this->redirectToRoute('demande_index');
    }

    /**
     * @Route("/api", name="demande_api", methods={"POST"}) 
     */
    public function demandeApi(Request $request, DemandeRepository $demandeRepository) : Response
    {   
        if($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $demandes = $demandeRepository->findAll();
            $rows = [];
            foreach ($demandes as $demande) 
            {
                $url = $this->generateUrl("demande_show", [ "id" => $demande->getId()]);
                $row =
                [ 
                    "Date"=> ($demande->getDate() ? $demande->getDate() : 'null')->format('d-m-Y'),
                    "Demandeur"=> ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : 'null'),
                    "Numero"=> ($demande->getNumero() ? $demande->getNumero() : 'null'),
                    "Statut"=> ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : 'null'),
                    'Actions'=> "<a href='". $url ." ' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>", 
                ];
                
                array_push($rows, $row);
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api/{id}", name="LigneArticle_api", methods={"POST"}) 
     */
    public function LigneArticleApi(Request $request, Demande $demande) : Response
    {
        if($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $LigneArticles = $demande->getLigneArticle();
            $rows = [];
        
            foreach ($LigneArticles as $LigneArticle) 
            {
                $refArticle = $this->referencesArticlesRepository->findOneById($LigneArticle["reference"]);
                $row = [ 
                    "References CEA" => ($LigneArticle["reference"] ? $LigneArticle["reference"] : 'null'),
                    "Libelle" => ($refArticle->getLibelle() ? $refArticle->getLibelle() : 'null'),
                    "Quantite" => ($LigneArticle["quantite"] ? $LigneArticle["quantite"] : 'null'),
                    "Actions" => "actions", 
                ];
                array_push($rows, $row);
            }
    
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
