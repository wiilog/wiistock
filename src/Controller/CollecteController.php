<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Collecte;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\Article;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\CollecteRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\StatutRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\CollecteReferenceRepository;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
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
     * @var CollecteReferenceRepository
     */
    private $collecteReferenceRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(RefArticleDataService $refArticleDataService, CollecteReferenceRepository $collecteReferenceRepository, ReferenceArticleRepository $referenceArticleRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UtilisateurRepository $utilisateurRepository, UserService $userService)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->collecteRepository = $collecteRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->collecteReferenceRepository = $collecteReferenceRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('collecte/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(Collecte::CATEGORIE),
            'utilisateurs' => $this->utilisateurRepository->findAll(),
        
        ]);
    }

    /**
     * @Route("/voir/{id}", name="collecte_show", methods={"GET", "POST"})
     */
    public function show(Collecte $collecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('collecte/show.html.twig', [
            'refCollecte' => $this->collecteReferenceRepository->getByCollecte($collecte),
            'collecte' => $collecte,
            'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON),
        ]);
    }

    /**
     * @Route("/api", name="collecte_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $collectes = $this->collecteRepository->findAll();
           
            $rows = [];
            foreach ($collectes as $collecte) {
                $url = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
                $rows[] = [
                        'id' => ($collecte->getId() ? $collecte->getId() : 'Non défini'),
                        'Date' => ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                        'Demandeur' => ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null),
                        'Objet' => ($collecte->getObjet() ? $collecte->getObjet() : null),
                        'Statut' => ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                        'Actions' => $this->renderView('collecte/datatableCollecteRow.html.twig', [
                            'url' => $url,
                        ]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/article/api/{id}", name="collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function articleApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecte = $this->collecteRepository->find($id);
            $articles = $this->articleRepository->getByCollecte($collecte->getId());
            $referenceCollectes = $this->collecteReferenceRepository->getByCollecte($collecte);
            $rowsRC = [];
            foreach ($referenceCollectes as $referenceCollecte) {
                $rowsRC[] = [
                    'Référence CEA' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getReference() : ''),
                    'Libellé' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getLibelle() : ''),
                    'Emplacement' => $collecte->getPointCollecte()->getLabel(),
                    'Quantité' => ($referenceCollecte->getQuantite() ? $referenceCollecte->getQuantite() : ''),
                    'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', [
                        'data' => [
                            'id' => $referenceCollecte->getId(),
                            'name' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getTypeQuantite() : ReferenceArticle::TYPE_QUANTITE_REFERENCE),
                        ],
                        'collecteId' => $collecte->getid(),
                        'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON),
                    ]),
                ];
            }
            $rowsCA = [];
            foreach ($articles as $article) {
                $rowsCA[] = [
                    'Référence CEA' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                    'Libellé' => $article->getLabel(),
                    'Emplacement' => ($collecte->getPointCollecte() ? $collecte->getPointCollecte()->getLabel() : ''),
                    'Quantité' => $article->getQuantite(),
                    'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', [
                        'data' => [
                            'id' => $article->getId(),
                            'name' => (ReferenceArticle::TYPE_QUANTITE_ARTICLE),
                        ],
                        'collecteId' => $collecte->getid(),
                        'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON ? true : false),
                    ]),
                ];
            }
            $data['data'] = array_merge($rowsCA, $rowsRC);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="collecte_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getEntityManager();
            $date = new \DateTime('now');
            $status = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_BROUILLON);
            $numero = 'C-'.$date->format('YmdHis');
            $collecte = new Collecte();
            $destination= ($data['destination'] == 0) ? false : true ; 
                
                $collecte
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setNumero($numero)
                ->setDate($date)
                ->setStatut($status)
                ->setPointCollecte($this->emplacementRepository->find($data['emplacement']))
                ->setObjet($data['Objet'])
                ->setCommentaire($data['commentaire'])
                ->setstockOrDestruct($destination);
            $em->persist($collecte);
            $em->flush();
            $data = [
                'redirect' => $this->generateUrl('collecte_show', ['id' => $collecte->getId()]),
            ];

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/ajouter-article", name="collecte_add_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $collecte = $this->collecteRepository->find($data['collecte']);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if ($this->collecteReferenceRepository->countByCollecteAndRA($collecte, $refArticle) > 0) {
                    $collecteReference = $this->collecteReferenceRepository->getByCollecteAndRA($collecte, $refArticle);
                    $collecteReference->setQuantite(intval($collecteReference->getQuantite()) + intval($data['quantitie']));
                } else {
                    $collecteReference = new CollecteReference();
                    $collecteReference
                        ->setCollecte($collecte)
                        ->setReferenceArticle($refArticle)
                        ->setQuantite($data['quantitie']);
                    $em->persist($collecteReference);
                }
                $response = $this->refArticleDataService->editRefArticle($refArticle, $data);
            } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $article = $this->articleRepository->find($data['article']);
                $collecte->addArticle($article);
            }
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-quantite-article", name="collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getManager();

            $collecteReference = $this->collecteReferenceRepository->find($data['collecteRef']);
            $collecteReference->setQuantite(intval($data['quantite']));
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-quantite-api-article", name="collecte_edit_api_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editApiArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            $json = $this->renderView('collecte/modalEditArticleContent.html.twig', [
                'collecteRef' => $this->collecteReferenceRepository->find($data),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/retirer-article", name="collecte_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function removeArticle(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();

            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $collecteReference = $this->collecteReferenceRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager->remove($collecteReference);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $this->articleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
                $collecte = $this->collecteRepository->find($data['collecte']);

                $article->removeCollecte($collecte);
            }
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/api-modifier", name="collecte_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecte = $this->collecteRepository->find($data);

            $json = $this->renderView('collecte/modalEditCollecteContent.html.twig', [
                'collecte' => $collecte,
                'statuts' => $this->statutRepository->findByCategorieName(Collecte::CATEGORIE),
                'emplacements' => $this->emplacementRepository->findAll(),
            ]);

            return new JsonResponse($json);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="collecte_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecte = $this->collecteRepository->find($data['collecte']);
            $pointCollecte = $this->emplacementRepository->find($data['Pcollecte']);
            $destination= ($data['destination'] == 0) ? false : true ; 
            $collecte
                ->setDate(new \DateTime($data['date-collecte']))
                ->setCommentaire($data['commentaire'])
                ->setObjet($data['objet'])
                ->setPointCollecte($pointCollecte)
                ->setstockOrDestruct($destination);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            $json = [
                'entete' => $this->renderView('collecte/enteteCollecte.html.twig', [
                    'collecte' => $collecte,
                    'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON),
                ]),
            ];

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="collecte_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            
            $collecte = $this->collecteRepository->find($data['collecte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('collecte_index'),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/non-vide", name="demande_collecte_has_articles", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function hasArticles(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $articles = $this->articleRepository->getByCollecte($data['id']);
            $referenceCollectes = $this->collecteReferenceRepository->getByCollecte($data['id']);
            $count = count($articles) + count($referenceCollectes);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }
}
