<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\Preparation;

use App\Form\LivraisonType;

use App\Repository\DemandeRepository;
use App\Repository\ReferenceArticleRepository;
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
     * @Route("/finir", name="finish_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function finish(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $demande = $this->demandeRepository->find($data['demande']);
            $preparation = new Preparation();
            $date = new \DateTime('now');
            $preparation
                ->setNumero('P-' . $date->format('YmdHis'))
                ->setDate($date)
                ->setUtilisateur($this->getUser());

            $statutP = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
            $preparation->setStatut($statutP);

            $demande->setPreparation($preparation);

            $statutD = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $demande->setStatut($statutD);

            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            $data = [
                'entete'=> $this->renderView(
                    'demande/enteteDemandeLivraison.html.twig',
                    [
                        'demande' => $demande,
                    ]
                ),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $utilisateur = $this->utilisateurRepository->find(intval($data["demandeur"]));
            $emplacement = $this->emplacementRepository->find(intval($data['destination']));
            $demande = $this->demandeRepository->find($data['demandeId']);
            $demande
                ->setUtilisateur($utilisateur)
                ->setDestination($emplacement);
            $em = $this->getDoctrine()->getEntityManager();
            $em->flush();

            $json = [
                'entete' => $this->renderView('demande/enteteDemandeLivraison.html.twig', [
                    'demande' => $demande,
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="demande_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $utilisateur = $this->utilisateurRepository->find($data["demandeur"]);
            $date = new \DateTime('now');
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $destination = $this->emplacementRepository->find($data["destination"]);
            $demande = new Demande();
            $demande
                ->setStatut($statut)
                ->setUtilisateur($utilisateur)
                ->setdate($date)
                //                ->setDateAttendu(new \DateTime($data['dateAttendu']))
                ->setDestination($destination)
                ->setNumero("D-" . $date->format('YmdHis'))
                ->setCommentaire($data["commentaire"]);
            $em->persist($demande);
            $em->flush();

            $data = [
                "redirect" => $this->generateUrl('ligne_article_show', ['id' => $demande->getId()])
            ];

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
            $demande = $this->demandeRepository->find($data['demandeId']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
            $data = [
                "redirect" => $this->generateUrl('demande_index')
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {

            // $demandes = $demandeRepository->findByUserAndNotStatus($this->getUser(), Livraison::STATUT_TERMINE);
            $demandes = $this->demandeRepository->findAll();
            $rows = [];
            foreach ($demandes as $demande) {
                $idDemande = $demande->getId();
                $url = $this->generateUrl('ligne_article_show', ['id' => $idDemande]);
                $rows[] =
                    [
                        "Date" => ($demande->getDate() ? $demande->getDate()->format('d/m/Y') : ''),
                        "Demandeur" => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                        "Numéro" => ($demande->getNumero() ? $demande->getNumero() : ''),
                        "Statut" => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                        'Actions' => $this->renderView(
                            'demande/datatableDemandeRow.html.twig',
                            [
                                'idDemande' => $idDemande,
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
     * @Route("/voir", name="demande_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data);
            $json = $this->renderView('demande/modalEditDemand.html.twig', [
                'demande' => $demande,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }
}
