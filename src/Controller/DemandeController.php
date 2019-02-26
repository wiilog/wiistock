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
use App\Entity\LigneArticle;
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

    /* Mise en place d'un constructeur pour les données qui reviennent dans toutes(ou presque) les fonctions du controller */
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

            $statut = $this->statutsRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_NOUVELLE);
            $preparation->setStatut($statut);

            $demande->setPreparation($preparation);

            $statut = $this->statutsRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $demande->setStatut($statut);

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
     * @Route("demande-livraison/voir/ajoutLigneArticle/{id}", name="ajoutLigneArticle", methods="GET|POST")
     */
    public function ajoutLigneArticle(Demande $demande, FournisseursRepository $fournisseursRepository, Request $request) : Response
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if (count($data) >= 2) {

                $em = $this->getDoctrine()->getEntityManager();
                $referenceArticle = $this->referencesArticlesRepository->find($data[0]["reference"]);

                $LigneArticle = new LigneArticle();
                $LigneArticle->setQuantite($data[1]["quantite"])
                             ->setReference($referenceArticle);

                $quantiteReservee = intval($data[1]["quantite"]);
                $quantiteArticleReservee = $referenceArticle->getQuantiteReservee();
                $referenceArticle->setQuantiteReservee($quantiteReservee + $quantiteArticleReservee);

                $demande->addLigneArticle($LigneArticle);
                $em->persist($referenceArticle);
                $em->persist($LigneArticle);
                $em->persist($demande);
                $em->flush();

                return new JsonResponse($data);
            }
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/demande-livraison/voir/modifierLigneArticle/{id}", options={"expose"=true}, name="modifyLigneArticle", methods={"GET", "POST"})
     */
    public function modifyLigneArticle(LigneArticle $ligneArticle, Request $request) : Response
    {
        if ($data = json_decode($request->getContent(), true)) 
        {
            $ligneArticle->setQuantite($data[0]["quantity"]); 
            $data['redirect'] = $this->generateUrl('demande_show', [ 'id' => $ligneArticle->getDemande()->getId()]); 
            $this->getDoctrine()->getEntityManager()->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/demande-livraison/voir/supprimeLigneArticle/{id}", name="deleteLigneArticle", methods={"GET", "POST"})
     */
    public function deleteLigneArticle(LigneArticle $ligneArticle, Request $request) : Response
    {
        $em = $this->getDoctrine()->getEntityManager();
        $em->remove($ligneArticle);
        $em->flush();
        return $this->redirectToRoute('demande_show', [ 'id' => $ligneArticle->getDemande()->getId()]);
    }



    /**
     * @Route("/modifDemande/{id}", name="modifDemande", methods="GET|POST")
     */
    public function modifDemande(Demande $demande, Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (count($data) >= 3) {
                $em = $this->getDoctrine()->getEntityManager();
                $utilisateur = $this->utilisateursRepository->find(intval($data[0]["demandeur"]));
                $statut = $this->statutsRepository->find($data[2]["statut"]);
                $demande
                    ->setUtilisateur($utilisateur)
                    ->setDateAttendu(new \Datetime($data[1]["date-attendu"]))
                    ->setStatut($statut);
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
    public function creationDemande(Request $request, ArticlesRepository $articlesRepository) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $userId = $data[0];
            $utilisateur = $this->utilisateursRepository->find($userId["demandeur"]);
            $date = new \DateTime('now');
            $statut = $this->statutsRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
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
            'statuts' => $this->statutsRepository->findByCategorieName('Demandes'),
            'emplacements' => $this->emplacementRepository->findLocGetIdName()
        ]);
    }



    /**
     * @Route("/voir/{id}", name="demande_show", methods={"GET", "POST"})
     */
    public function show(Demande $demande) : Response
    {
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'utilisateurs' => $this->utilisateursRepository->findUserGetIdUser(),
            'statuts' => $this->statutsRepository->findByCategorieName(Demande::CATEGORIE),
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
                    'Actions' => "<a href='" . $urlShow . " ' class='btn btn-xs btn-default command-edit'><i class='fas fa-eye fa-2x'></i></a>",
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
                $id = $LigneArticle->getId();
                //$urlModify = $this->generateUrl('modifyLigneArticle', ['id' => $LigneArticle->getId()]);
                $urlDelete = $this->generateUrl('deleteLigneArticle', ['id' => $LigneArticle->getId()]);
                $row = [
                    "Références CEA" => ($LigneArticle->getReference()->getReference() ? $LigneArticle->getReference()->getReference() : ''),
                    "Libellé" => ($LigneArticle->getReference()->getLibelle() ? $LigneArticle->getReference()->getLibelle() : ''),
                    "Quantité" => ($LigneArticle->getQuantite() ? $LigneArticle->getQuantite() : ''),
                    "Actions" => "<div onclick='editRow($(this))' data-toggle='modal' data-target='#modalModifyLigneArticle' data-name='". $LigneArticle->getReference()->getLibelle()."' data-quantity='" . $LigneArticle->getQuantite(). "' data-id='" . $LigneArticle->getId() . "' class='btn btn-xs btn-default demand-edit '><i class='fas fa-pencil-alt fa-2x'></i></div>"
                    . "<a href='$urlDelete' class='btn btn-xs btn-default delete '><i class='fas fa-trash fa-2x'></i></a>"
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
