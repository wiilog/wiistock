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
use App\Repository\LigneArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\UtilisateurRepository;

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
     * @var LignreArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    public function __construct(LigneArticleRepository $ligneArticleRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
    }



    /**
     * @Route("/finDemande/{id}", name="fin_demande") //TODOO
     */
    public function creationPreparationDepuisDemande(Demande $demande): Response
    {
        if ($demande->getPreparation() === null && count($demande->getLigneArticle()) > 0) {
            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();

            $date = new \DateTime('now');
            $preparation
                ->setNumero('P-' . $date->format('YmdHis'))
                ->setDate($date)
                ->setUtilisateur($this->getUser());

            $statut = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_NOUVELLE);
            $preparation->setStatut($statut);

            $demande->setPreparation($preparation);

            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $demande->setStatut($statut);

            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            return $this->redirectToRoute('preparation_show', ['id' => $preparation->getId()]);
        } else if ($demande->getPreparation() !== null) {
            return $this->redirectToRoute('preparation_show', ['id' => $demande->getPreparation()->getId()]);
        }
        // return $this->show($demande);
    }


    //LIGNE ARTICLE

    /**
     * @Route("/apiLigne/{id}", name="LigneArticle_api", options={"expose"=true},  methods="GET|POST")
     */
    public function LigneArticleApi(Request $request, Demande $demande): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $ligneArticles = $demande->getLigneArticle();
                $rows = [];
                foreach ($ligneArticles as $ligneArticle) {
                    $idArticle = $ligneArticle->getId();
                    $url['delete'] = $this->generateUrl('ligne_article_delete', ['id' => $ligneArticle->getId()]);
                    $rows[] = [
                        "Référence CEA" => ($ligneArticle->getReference()->getReference() ? $ligneArticle->getReference()->getReference() : ''),
                        "Libellé" => ($ligneArticle->getReference()->getLibelle() ? $ligneArticle->getReference()->getLibelle() : ''),
                        "Quantité" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ''),
                        "Actions" => $this->renderView(
                            'demande/datatableLigneArticleRow.html.twig',
                            [
                                'url' => $url,
                                'ligneArticle' => $ligneArticle,
                                'idArticle' => $idArticle
                            ]
                        )
                    ];
                }

                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/ajoutLigneArticle", name="ajoutLigneArticle", options={"expose"=true},  methods="GET|POST")
     */
    public function ajoutLigneArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $referenceArticle = $this->referenceArticleRepository->find($data["reference"]);
            $demande = $this->demandeRepository->find($data['demande']);
            if ($this->ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $ligneArticle = new LigneArticle();
                $ligneArticle
                    ->setQuantite($data["quantite"])
                    ->setReference($referenceArticle);
            } else {
                $ligneArticle = $this->ligneArticleRepository->getByRefArticle($referenceArticle);
                $ligneArticle
                    ->setQuantite($ligneArticle->getQuantite() + $data["quantite"]);
            }

            $quantiteReservee = intval($data["quantite"]);
            $quantiteArticleReservee = $referenceArticle->getQuantiteReservee();

            $referenceArticle
                ->setQuantiteReservee($quantiteReservee + $quantiteArticleReservee);

            $demande
                ->addLigneArticle($ligneArticle);

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($ligneArticle);
            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/apiEditArticle", options={"expose"=true}, name="article_edit_api", methods={"POST"})
     */
    public function articleApiEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $ligneArticle = $this->ligneArticleRepository->getQuantity($data);
            dump($ligneArticle);
            $json = $this->renderView('demande/modalEditArticleContent.html.twig', [
                'ligneArticle' => $ligneArticle,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifierLigneArticle", options={"expose"=true}, name="article_edit", methods={"GET", "POST"})
     */
    public function modifyLigneArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $ligneArticle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $ligneArticle->setQuantite($data["quantite"]);
            $this->getDoctrine()->getEntityManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/supprimeLigneArticle", options={"expose"=true}, name="ligne_article_delete", methods={"GET", "POST"})
     */
    public function deleteLigneArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $ligneAricle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($ligneAricle);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="demande_show_article", methods={"GET", "POST"})
     */
    public function showArticle(Demande $demande): Response
    {
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $this->referenceArticleRepository->getIdAndLibelle()
        ]);
    }

    //DEMANDE-LIVRAISON

    /**
     * @Route("/apiDemandeEdit", options={"expose"=true}, name="demande_edit_api", methods={"POST"})
     */
    public function demandeApiEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $demande = $this->demandeRepository->find($data);
            $emplacement = $this->emplacementRepository->getNoOne($demande->getDestination()->getId());
            $utilisateur = $this->utilisateurRepository->getNoOne($demande->getUtilisateur()->getId());
            $json = $this->renderView('demande/modalEditDemandeContent.html.twig', [
                'demande' => $demande,
                'utilisateurs' => $utilisateur,
                'emplacements' => $emplacement
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifDemande", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function demandeEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $utilisateur = $this->utilisateurRepository->find(intval($data["demandeur"]));
            $emplacement = $this->emplacementRepository->find($data['destination']);
            $demande = $this->demandeRepository->find($data['demande']);
            $demande
                ->setUtilisateur($utilisateur)
                ->setDateAttendu(new \DateTime($data['dateAttendu']))
                ->setDestination($emplacement);
            $em = $this->getDoctrine()->getEntityManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/new", name="demande_new", options={"expose"=true}, methods="GET|POST")
     */
    public function creationDemande(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $utilisateur = $this->utilisateurRepository->find($data["demandeur"]);
            $date = new \DateTime('now');
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $destination = $this->emplacementRepository->find($data["destination"]);
            $demande = new Demande();
            $demande
                ->setStatut($statut)
                ->setUtilisateur($utilisateur)
                ->setdate($date)
//                ->setDateAttendu(new \DateTime($data['dateAttendu']))
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
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data['demande']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     */
    public function demandeApi(Request $request, DemandeRepository $demandeRepository): Response
    {
        if ($request->isXmlHttpRequest()) {

            // $demandes = $demandeRepository->findByUserAndNotStatus($this->getUser(), Livraison::STATUT_TERMINE);
            $demandes = $this->demandeRepository->findAll();
            $rows = [];
            foreach ($demandes as $demande) {
                $idDemande = $demande->getId();
                $url = $this->generateUrl('demande_show_article', ['id' => $idDemande]);
                $rows[] =
                    [
                        "Date" => ($demande->getDate() ? $demande->getDate()->format('d-m-Y') : ''),
                        "Demandeur" => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                        "Numéro" => ($demande->getNumero() ? $demande->getNumero() : ''),
                        "Statut" => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                        'Actions' => $this->renderView(
                            'demande/datatableDemandeRow.html.twig',
                            [
                                'idDemande' => $idDemande,
                                'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_A_TRAITER) ? true : false),
                                'url' => $url
                            ]
                        ),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/detail", options={"expose"=true}, name="demande_show", methods={"GET", "POST"})
     */
    public function show(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data);
            $json = $this->renderView('demande/modalShowDemandeContent.html.twig', [
                'demande' => $demande,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }
}
