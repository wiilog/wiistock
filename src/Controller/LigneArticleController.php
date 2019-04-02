<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\LigneArticle;

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
 * @Route("/ligne-article")
 */
class LigneArticleController extends AbstractController
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
     * @Route("/api/{id}", name="ligne_article_api", options={"expose"=true},  methods="GET|POST")
     */
    public function api(Request $request, Demande $demande): Response
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
                                'idArticle' => $idArticle,
                                'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
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
     * @Route("/creer", name="ligne_article_new", options={"expose"=true},  methods="GET|POST")
     */
    public function new(Request $request): Response
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

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="ligne_article_api_edit", options={"expose"=true}, methods={"POST"})
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $ligneArticle = $this->ligneArticleRepository->getQuantity($data);
            $json = $this->renderView('demande/modalEditArticleContent.html.twig', [
                'ligneArticle' => $ligneArticle,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="ligne_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $reference = $this->referenceArticleRepository->find($data['reference']);
            $ligneArticle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $ligneArticle
                ->setReference($reference)
                ->setQuantite($data["quantite"]);
            $this->getDoctrine()->getEntityManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="ligne_article_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
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
     * @Route("/voir/{id}", name="ligne_article_show", methods={"GET", "POST"})
     */
    public function show(Demande $demande): Response
    {
        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $this->referenceArticleRepository->getIdAndLibelle(),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'emplacements' => $this->emplacementRepository->findAll()
        ]);
    }

}
