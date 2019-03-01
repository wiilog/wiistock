<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\LigneArticle;
use App\Entity\Preparation;

use App\Form\LivraisonType;

use App\Repository\DemandeRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\FournisseurRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\UtilisateurRepository;

use Knp\Component\Pager\PaginatorInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
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

    public function __construct(StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    

    /**
     * @Route("/preparation/{id}", name="preparationFromDemande")
     */
    public function creationPreparationDepuisDemande(Demande $demande): Response
    {
        if ($demande->getPreparation() == null && count($demande->getLigneArticle()) > 0) {
            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            $date = new \DateTime('now');
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $preparation->setUtilisateur($this->getUser());

            $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_NOUVELLE);
            $preparation->setStatut($statut);

            $demande->setPreparation($preparation);

            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
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
     * @Route("demande-livraison/voir/ajoutLigneArticle/{id}", options={"expose"=true}, name="ajoutLigneArticle", methods="GET|POST")
     */
    public function ajoutLigneArticle(Demande $demande, FournisseurRepository $fournisseurRepository, Request $request) : Response
    {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if (count($data) >= 2) {

                $em = $this->getDoctrine()->getEntityManager();
                $referenceArticle = $this->referenceArticleRepository->find($data["reference"]);

                $LigneArticle = new LigneArticle();
                $LigneArticle->setQuantite($data["quantite"])
                             ->setReference($referenceArticle);

                $quantiteReservee = intval($data["quantite"]);
                $quantiteArticleReservee = $referenceArticle->getQuantiteReservee();
                $referenceArticle->setQuantiteReservee($quantiteReservee + $quantiteArticleReservee);

                $demande->addLigneArticle($LigneArticle);
                $em->persist($referenceArticle);
                $em->persist($LigneArticle);
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
            $ligneArticle->setQuantite($data["quantity"]); 
            //$data['redirect'] = $this->generateUrl('demande_show', [ 'id' => $ligneArticle->getDemande()->getId()]); 
            $this->getDoctrine()->getEntityManager()->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/demande-livraison/voir/supprimeLigneArticle/{id}", options={"expose"=true}, name="deleteLigneArticle", methods={"GET", "POST"})
     */
    public function deleteLigneArticle(LigneArticle $ligneArticle, Request $request) : Response
    {
        $em = $this->getDoctrine()->getEntityManager();
        $em->remove($ligneArticle);
        $em->flush();
        return $this->redirectToRoute('demande_show', [ 'id' => $ligneArticle->getDemande()->getId()]);
    }



    /**
     * @Route("/modifDemande/{id}", name="modifDemande", options={"expose"=true}, methods="GET|POST")
     */
    public function modifDemande(Demande $demande, Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (count($data) >= 3) {
                $em = $this->getDoctrine()->getEntityManager();
                $utilisateur = $this->utilisateurRepository->find(intval($data["demandeur"]));
                $statut = $this->statutRepository->find($data["statut"]);
                $demande
                    ->setUtilisateur($utilisateur)
                    ->setDateAttendu(new \Datetime($data["date-attendu"]))
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
     * @Route("/creationDemande", name="creation_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function creationDemande(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $userId = $data;
            dump($data);
            $utilisateur = $this->utilisateurRepository->find($data["demandeur"]);
            $date = new \DateTime('now');
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            dump($statut);
            $destination = $this->emplacementRepository->find($data["destination"]);
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
    public function index(Request $request): Response
    {
        return $this->render('demande/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'emplacements' => $this->emplacementRepository->getIdAndNom()
        ]);
    }



    /**
     * @Route("/voir/{id}", name="demande_show", methods={"GET", "POST"})
     */
    public function show(Demande $demande) : Response
    {
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $this->referenceArticleRepository->getIdAndLibelle()
        ]);
    }



    /**
     * @Route("/{id}", name="demande_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Demande $demande): Response
    {
        if ($this->isCsrfTokenValid('delete' . $demande->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
        }

        return $this->redirectToRoute('demande_index');
    }



    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     */
    public function demandeApi(Request $request, DemandeRepository $demandeRepository): Response
    {
        if ($request->isXmlHttpRequest()) {
//            if ($request->request->get('utilisateur')) {
//                $utilistaeur = $request->request->get('utilisateur');
//                $statut = $request->request->get('statut');
//                $dateDebut = $request->request->get('dateDebut');
//                $dateFin = $request->request->get('dateFin');
//
//                $demandes = $demandeRepository->findAll(); // a modifier pour filtre

//            } else {
                $demandes = $demandeRepository->findByUserAndNotStatus($this->getUser(), Livraison::STATUT_TERMINE);
//            }
            $rows = [];
            foreach ($demandes as $demande) {
                $url['show'] = $this->generateUrl('demande_show', ['id' => $demande->getId()]);
                $rows[] =
                    [
                        "Date" => ($demande->getDate() ? $demande->getDate() : '')->format('d-m-Y'),
                        "Demandeur" => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                        "Numéro" => ($demande->getNumero() ? $demande->getNumero() : ''),
                        "Statut" => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                        'Actions' => $this->renderView('demande/datatabledemandeRow.html.twig', ['url' => $url]),
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/api-ligne/{id}", name="LigneArticle_api", options={"expose"=true}, methods={"POST"})
     */
    public function LigneArticleApi(Request $request, Demande $demande) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $ligneArticles = $demande->getLigneArticle();
            $rows = [];

            foreach ($ligneArticles as $ligneArticle) {
                $url['delete'] = $this->generateUrl('deleteLigneArticle', ['id' => $ligneArticle->getId()]);
                $rows[] = [
                    "Référence CEA" => ($ligneArticle->getReference()->getReference() ? $ligneArticle->getReference()->getReference() : ''),
                    "Libellé" => ($ligneArticle->getReference()->getLibelle() ? $ligneArticle->getReference()->getLibelle() : ''),
                    "Quantité" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ''),
                    "Actions" => $this->renderView('demande/datatableLigneArticleRow.html.twig', ['url' => $url, 'ligneArticle' => $ligneArticle])
                ];
            }

            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


}
