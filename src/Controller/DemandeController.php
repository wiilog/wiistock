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
    public function creationPreparationDepuisDemande(Demande $demande) : Response
    {
        if ($demande->getPreparation() == null && count($demande->getLigneArticle()) > 0) {
            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            $date = new \DateTime('now');
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $preparation->setUtilisateur($this->getUser());

            $statut = $this->statutsRepository->findById(11); /* Statut : nouvelle préparation */
            $preparation->setStatut($statut[0]);

            $demande->setPreparation($preparation);

            $statut = $this->statutsRepository->findById(14); /* Statut : Demande de préparation */
            $demande->setStatut($statut[0]);

            $em = $this->getDoctrine()->getManager();
            $em->persist($demande);
            $em->persist($preparation);
            $em->flush();
            return $this->render('preparation/show.html.twig', ['preparation' => $demande->getPreparation(), 'demande' => $demande]);
        } else if ($demande->getPreparation() != null) {
            return $this->render('preparation/show.html.twig', ['preparation' => $demande->getPreparation(), 'demande' => $demande]);
        }
        return $this->show($demande);
    }


    /**
     * @Route("/ajoutArticle/{id}", name="ajoutArticle", methods="GET|POST")
     */
    public function ajoutRefArticle(Demande $demande, FournisseursRepository $fournisseursRepository, Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (count($data) >= 2) {
                $em = $this->getDoctrine()->getEntityManager();
                
                $json = [
                    "reference" => $data[0]["reference"],
                    "quantite" => $data[1]["quantite"],
                ];
                
                $referenceArticle = $this->referencesArticlesRepository->find([$data[0]["reference"]]);
                dump("hello");

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
     * @Route("/supprimeArticle/{id}", name="supprimeArticle", methods="GET|POST")
     */
    public function supprimeRefArticle(Demande $demande, Request $request) : Response
    {
        $json = [
            "reference" => $data[0]["reference"],
            "quantite" => $data[1]["quantite"],
        ];

        $demande->addLigneArticle($json);
        $em->persist($referenceArticle);
        $em->persist($demande);
        $em->flush();

        return $this->redirectToRoute();

    }


    /**
     * @Route("/modifDemande/{id}", name="modifDemande", methods="GET|POST")
     */
    public function modifDemande(Demande $demande, Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (count($data) >= 3) {
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
    public function creationDemande(LivraisonRepository $livraisonRepository, Request $request, ArticlesRepository $articlesRepository) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $userId = $data[0];
            $utilisateur = $this->utilisateursRepository->find($userId["demandeur"]);
            $date = new \DateTime('now');
            $statut = $this->statutsRepository->find(14);
            $destination = $this->emplacementRepository->find($data[1]["destination"]);
            
            $demande = new Demande();
            $demande
                ->setStatut($statut)
                ->setUtilisateur($utilisateur)
                ->setdate($date)
                ->setDestination($destination)
                ->setNumero("D-" . $date->format('YmdHis')); 
            $em->persist($demande);
            $em->flush();
            
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/", name="demande_index", methods={"GET"})
     */
    public function index(Request $request) : Response
    {
        return $this->render('demande/index.html.twig', [
            'utilisateurs' => $this->utilisateursRepository->findUserGetIdUser(),
            'statuts' => $this->statutsRepository->findByCategorie('Demandes'),
            'emplacements' => $this->emplacementRepository->findLocGetIdName()
        ]);
    }


    /**
     * @Route("/voir/{id}", name="demande_show", methods={"GET"})
     */
    public function show(Demande $demande) : Response
    {
        $ligneArticle = $demande->getLigneArticle();
        $lignes = [];

        foreach ($ligneArticle as $ligne) {
            $refArticle = $this->referencesArticlesRepository->find($ligne["reference"]);
            $data = [
                "Références CEA" => $ligne["reference"],
                "Quantité" => $ligne["quantite"],
                "Libellé" => $refArticle->getLibelle(),
            ];
            array_push($lignes, $data);
        }
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'lignesArticles' => $lignes,
            'utilisateurs' => $this->utilisateursRepository->findUserGetIdUser(),
            'statuts' => $this->statutsRepository->findByCategorie('Demandes'),
            'references' => $this->referencesArticlesRepository->findRefArticleGetIdLibelle()
        ]);
    }


    /**
     * @Route("/{id}", name="demande_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Demande $demande) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $demande->getId(), $request->request->get('_token'))) {
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
        if ($request->isXmlHttpRequest())
        {
            if ($request->request->get('utilisateur')) {
                $utilistaeur = $request->request->get('utilisateur');
                $statut = $request->request->get('statut');
                $dateDebut = $request->request->get('dateDebut');
                $dateFin = $request->request->get('dateFin');

                $demandes = $demandeRepository->findAll(); // a modifier pour filtre

            } else {
                $demandes = $demandeRepository->findAllByUserAndStatut($this->getUser());
            }
            $rows = [];
            foreach ($demandes as $demande) {
                $urlShow = $this->generateUrl('demande_show', ['id' => $demande->getId()]);
                $row =
                    [
                    "Date" => ($demande->getDate() ? $demande->getDate() : '')->format('d-m-Y'),
                    "Demandeur" => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                    "Numéro" => ($demande->getNumero() ? $demande->getNumero() : ''),
                    "Statut" => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                    'Actions' => "<a href='" . $urlShow . " ' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>",
                ];

                array_push($rows, $row);
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-ligne/{id}", name="LigneArticle_api", methods={"POST"})
     */
    public function LigneArticleApi(Request $request, Demande $demande) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $LigneArticles = $demande->getLigneArticle();
            $rows = [];

            foreach ($LigneArticles as $LigneArticle) {
                $refArticle = $this->referencesArticlesRepository->find($LigneArticle["reference"]);
                $urlShow = $this->generateUrl('supprimeArticle', ['id' => $demande->getId()]);
                $row = [
                    "Références CEA" => ($LigneArticle["reference"] ? $LigneArticle["reference"] : ''),
                    "Libellé" => ($refArticle->getLibelle() ? $refArticle->getLibelle() : ''),
                    "Quantité" => ($LigneArticle["quantite"] ? $LigneArticle["quantite"] : ''),
                    
                ];
                array_push($rows, $row);
            }
            
            //'Actions' => "<a href='" . $urlShow . " ' class='btn btn-xs btn-default command-edit '><i class='fas fa-trash fa-2x'></i></a>",
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


}
