<?php

namespace App\Controller;

use App\Entity\Collecte;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\Utilisateur;
use App\Entity\Article;
use App\Form\ArticleType;

use App\Service\RefArticleDataService;

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

use Hoa\Compiler\Visitor\Dump;

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

    public function __construct(RefArticleDataService $refArticleDataService, CollecteReferenceRepository $collecteReferenceRepository, ReferenceArticleRepository $referenceArticleRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UtilisateurRepository $utilisateurRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->collecteRepository = $collecteRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->collecteReferenceRepository = $collecteReferenceRepository;
        $this->refArticleDataService = $refArticleDataService;
    }

    /**
     * @Route("/", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        return $this->render('collecte/index.html.twig', [
            'emplacements' => $this->emplacementRepository->findAll(),
            'collecte' => $this->collecteRepository->findAll(),
            'statuts' => $this->statutRepository->findAll(),
        ]);
    }

    /**
     * @Route("/voir/{id}", name="collecte_show", methods={"GET", "POST"})
     */
    public function show(Collecte $collecte): Response
    {
        return $this->render('collecte/show.html.twig', [
            'collecte' => $collecte,
            'articles' => $this->articleRepository->findAll(),
            'modifiable' => ($collecte->getStatut()->getNom() !== Collecte::STATUS_EN_COURS ? true : false)
        ]);
    }

    /**
     * @Route("/api", name="collecte_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $collectes = $this->collecteRepository->findAll();

                $rows = [];
                foreach ($collectes as $collecte) {
                    $url = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
                    $rows[] = [
                        'id' => ($collecte->getId() ? $collecte->getId() : "Non défini"),
                        'Date' => ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                        'Demandeur' => ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null),
                        'Objet' => ($collecte->getObjet() ? $collecte->getObjet() : null),
                        'Statut' => ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                        'Actions' => $this->renderView('collecte/datatableCollecteRow.html.twig', [
                            'url' => $url,
                        ])

                    ];
                }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/article/api/{id}", name="collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function articleApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $collecte = $this->collecteRepository->find($id);
                $articles = $this->articleRepository->getByCollecte($collecte->getId());
                $referenceCollectes = $this->collecteReferenceRepository->getByCollecte($collecte);
                $rowsRC = [];
                foreach ($referenceCollectes as $referenceCollecte) {
                    $rowsRC[] = [
                        'Référence CEA' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getReference() : ""),
                        'Libellé' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getLibelle() : ""),
                        'Emplacement' => $collecte->getPointCollecte()->getLabel(),
                        'Quantité' => ($referenceCollecte->getQuantite() ? $referenceCollecte->getQuantite() : ""),
                        'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', [
                            'data' => [
                                'id' => $referenceCollecte->getId(),
                                'name' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getTypeQuantite() : ReferenceArticle::TYPE_QUANTITE_REFERENCE),
                            ],
                            'collecteId' => $collecte->getid(),
                            'modifiable' => ($collecte->getStatut()->getNom() !== Collecte::STATUS_EN_COURS ? true : false)
                        ])
                    ];
                }
                $rowsCA = [];
                foreach ($articles as $article) {
                    $rowsCA[] = [
                        'Référence CEA' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ""),
                        'Libellé' => $article->getLabel(),
                        'Emplacement' => ($collecte->getPointCollecte() ? $collecte->getPointCollecte()->getLabel() : "" ),
                        'Quantité' => $article->getQuantite(),
                        'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', [
                            'data' => [
                                'id' => $article->getId(),
                                'name' => (ReferenceArticle::TYPE_QUANTITE_ARTICLE),
                            ],
                            'collecteId' => $collecte->getid(),
                            'modifiable' => ($collecte->getStatut()->getNom() !== Collecte::STATUS_EN_COURS ? true : false)
                        ])

                    ];
                }
                $data['data'] = array_merge($rowsCA, $rowsRC);
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="collecte_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getEntityManager();
            $date = new \DateTime('now');
            $status = $this->statutRepository->findOneByNom(Collecte::STATUS_DEMANDE);
            $numero = "C-" . $date->format('YmdHis');
            $collecte = new Collecte;
            $collecte
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setNumero($numero)
                ->setDate($date)
                ->setStatut($status)
                ->setPointCollecte($this->emplacementRepository->find($data['Pcollecte']))
                ->setObjet($data['Objet'])
                ->setCommentaire($data['commentaire']);
            $em->persist($collecte);
            $em->flush();
            return new JsonResponse($data);
        }
        throw new XmlHttpException("404 not found");
    }


    /**
     * @Route("/ajouter-article", name="collecte_add_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $em = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $collecte = $this->collecteRepository->find($data['collecte']);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $collecteReference = new CollecteReference;
                $collecteReference
                    ->setCollecte($collecte)
                    ->setReferenceArticle($refArticle)
                    ->setQuantite($data['quantitie']);

                $response = $this->refArticleDataService->editRefArticle($refArticle, $data);

                $em->persist($collecteReference);
            } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $article = $this->articleRepository->find($data['article']);
                $collecte->addArticle($article);
            }
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/retirer-article", name="collecte_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function removeArticle(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $collecteReference = $this->collecteReferenceRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($collecteReference);
            }elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $this->articleRepository->find($data['article']);
                $collecte = $this->collecteRepository->find($data['collecte']);
                $entityManager = $this->getDoctrine()->getManager();
                $article->removeCollecte($collecte);
            }
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/finir", name="finish_collecte", options={"expose"=true}, methods={"GET", "POST"}))
     */
    public function finish(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $collecte = $this->collecteRepository->find($data['collecte']);

            // changement statut collecte
            $statusFinCollecte = $this->statutRepository->findOneBy(['nom' => Collecte::STATUS_EN_COURS]);
            $collecte->setStatut($statusFinCollecte);

            // changement statut article
            // $statusEnStock = $this->statutRepository->findOneBy(['nom' => Articles::STATUS_EN_STOCK]);
            // $article = $collecte->getArticles();
            // foreach ($article as $article) {
            //     $article->setStatut($statusEnStock);
            // }
            $em->flush();
            $response =  [
                'entete' => $this->renderView('collecte/enteteCollecte.html.twig', [
                    'collecte' => $collecte,
                    'modifiable' => ($collecte->getStatut()->getNom() !== Collecte::STATUS_EN_COURS ? true : false)
                ])
            ];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="collecte_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $collecte = $this->collecteRepository->find($data);

            $json = $this->renderView('collecte/modalEditCollecteContent.html.twig', [
                'collecte' => $collecte,
                "statuts" => $this->statutRepository->findAll(),
                "emplacements" => $this->emplacementRepository->findAll(),
                // 'utilisateurs'=>$this->utilisateurRepository->findAll(),
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="collecte_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $collecte = $this->collecteRepository->find($data['collecte']);
            $pointCollecte = $this->emplacementRepository->find($data['Pcollecte']);

            $collecte
                ->setNumero($data["NumeroCollecte"])
                ->setDate(new \DateTime($data["date-collecte"]))
                ->setCommentaire($data["commentaire"])
                ->setObjet($data["objet"])
                ->setPointCollecte($pointCollecte);

            $em = $this->getDoctrine()->getManager();
            $em->flush();
            $json = [
                'entete' => $this->renderView('collecte/enteteCollecte.html.twig', [
                    'collecte' => $collecte,
                    'modifiable' => ($collecte->getStatut()->getNom() !== Collecte::STATUS_EN_COURS ? true : false)
                ])
            ];

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="collecte_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $collecte = $this->collecteRepository->find($data['collecte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
            $data = [
                "redirect" => $this->generateUrl('collecte_index')
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
