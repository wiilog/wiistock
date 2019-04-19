<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Demande;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Repository\DemandeRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\LigneArticle;

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
     * @var LigneArticleRepository
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

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(LigneArticleRepository $ligneArticleRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, UserService $userService)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->userService = $userService;
    }
    /**
     * @Route("/compareStock", name="compare_stock", options={"expose"=true}, methods="GET|POST")
     */
    public function compareStock(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data['demande']);

            $quantiteReservee = $stock = 0;

            foreach ($demande->getLigneArticle() as $ligne) {
                $articleRef = $ligne->getReference();
                $stock = $articleRef->getQuantiteStock();
                $quantiteReservee = $ligne->getQuantite();

                $listLigneArticleByRefArticle = $this->ligneArticleRepository->findByRefArticle($articleRef);

                foreach($listLigneArticleByRefArticle as $ligneArticle) { /** @var LigneArticle $ligneArticle */
                    $status = $ligneArticle->getDemande()->getStatut()->getNom();
                    if ($status === Demande::STATUT_A_TRAITER || $status=== Demande::STATUT_PREPARE) {
                        $quantiteReservee += $ligneArticle->getQuantite();
                    }
                }
            }
            if ($quantiteReservee > $stock) {
                return new JsonResponse('La quantité souhaitée dépasse la quantité en stock.', 250);
            } else {
                return $this->finish($request);
            }
        }
        throw new NotFoundHttpException('404');
    }
    /**
     * @Route("/finir", name="finish_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function finish(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $demande = $this->demandeRepository->find($data['demande']);
            $preparation = new Preparation();
            $date = new \DateTime('now');
            $preparation
                ->setNumero('P-'.$date->format('YmdHis'))
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
                'entete' => $this->renderView(
                    'demande/enteteDemandeLivraison.html.twig',
                    [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                    ]
                ),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404'); //TODO retour msg erreur (pas d'article dans la DL)
    }

    /**
     * @Route("/api-modifier", name="demandeLivraison_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demandeLivraison = $this->demandeRepository->find($data);

            $json = $this->renderView('demande/modalEditDemandeContent.html.twig', [
                'demande' => $demandeLivraison,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $utilisateur = $this->utilisateurRepository->find(intval($data['demandeur']));
            $emplacement = $this->emplacementRepository->find(intval($data['destination']));
            $demande = $this->demandeRepository->find($data['demandeId']);
            $demande
                ->setUtilisateur($utilisateur)
                ->setDestination($emplacement)
                ->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getEntityManager();
            $em->flush();

            $json = [
                'entete' => $this->renderView('demande/enteteDemandeLivraison.html.twig', [
                    'demande' => $demande,
                    'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                ]),
            ];

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="demande_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $utilisateur = $this->utilisateurRepository->find($data['demandeur']);
            $date = new \DateTime('now');
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $destination = $this->emplacementRepository->find($data['destination']);
            $demande = new Demande();
            $demande
                ->setStatut($statut)
                ->setUtilisateur($utilisateur)
                ->setdate($date)
                //                ->setDateAttendu(new \DateTime($data['dateAttendu']))
                ->setDestination($destination)
                ->setNumero('D-'.$date->format('YmdHis'))
                ->setCommentaire($data['commentaire']);
            $em->persist($demande);
            $em->flush();

            $data = [
                'redirect' => $this->generateUrl('demande_show', ['id' => $demande->getId()]),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="demande_index", methods={"GET"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('demande/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
        ]);
    }

    /**
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $this->demandeRepository->find($data['demandeId']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('demande_index'),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $demandes = $this->demandeRepository->findAll();
            $rows = [];
            foreach ($demandes as $demande) {
                $idDemande = $demande->getId();
                $url = $this->generateUrl('demande_show', ['id' => $idDemande]);
                $rows[] =
                    [
                        'Date' => ($demande->getDate() ? $demande->getDate()->format('d/m/Y') : ''),
                        'Demandeur' => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                        'Numéro' => ($demande->getNumero() ? $demande->getNumero() : ''),
                        'Statut' => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                        'Actions' => $this->renderView(
                            'demande/datatableDemandeRow.html.twig',
                            [
                                'idDemande' => $idDemande,
                                'url' => $url,
                            ]
                        ),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}", name="demande_show", methods={"GET", "POST"})
     */
    public function show(Demande $demande): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $this->referenceArticleRepository->getIdAndLibelle(),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'emplacements' => $this->emplacementRepository->findAll(),
        ]);
    }

    /**
     * @Route("/api/{id}", name="demande_article_api", options={"expose"=true},  methods="GET|POST")
     */
    public function articleApi(Request $request, Demande $demande): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticles = $demande->getLigneArticle();
            $rows = [];
            foreach ($ligneArticles as $ligneArticle) {
                $idArticle = $ligneArticle->getId();
                $url['delete'] = $this->generateUrl('demande_remove_article', ['id' => $ligneArticle->getId()]);
                $rows[] = [
                    'Référence CEA' => ($ligneArticle->getReference()->getReference() ? $ligneArticle->getReference()->getReference() : ''),
                    'Libellé' => ($ligneArticle->getReference()->getLibelle() ? $ligneArticle->getReference()->getLibelle() : ''),
                    'Quantité' => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ''),
                    'Actions' => $this->renderView(
                        'demande/datatableLigneArticleRow.html.twig',
                        [
                            'idArticle' => $idArticle,
                            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        ]
                    ),
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-article", name="demande_add_article", options={"expose"=true},  methods="GET|POST")
     */
    public function addArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $referenceArticle = $this->referenceArticleRepository->find($data['reference']);
            $demande = $this->demandeRepository->find($data['demande']);

            if ($this->ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                $ligneArticle = new LigneArticle();
                $ligneArticle
                    ->setQuantite($data['quantite'])
                    ->setReference($referenceArticle);
            } else {
                $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);

                $ligneArticle
                    ->setQuantite($ligneArticle->getQuantite() + $data['quantite']);
            }

            $quantiteReservee = intval($data['quantite']);
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/retirer-article", name="demande_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function removeArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneAricle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($ligneAricle);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-article", name="demande_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $reference = $this->referenceArticleRepository->find($data['reference']);
            $ligneArticle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $ligneArticle
                ->setReference($reference)
                ->setQuantite($data['quantite']);
            $this->getDoctrine()->getEntityManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-article", name="demande_article_api_edit", options={"expose"=true}, methods={"POST"})
     */
    public function articleEditApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->ligneArticleRepository->getQuantity($data);
            $json = $this->renderView('demande/modalEditArticleContent.html.twig', [
                'ligneArticle' => $ligneArticle,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


}
