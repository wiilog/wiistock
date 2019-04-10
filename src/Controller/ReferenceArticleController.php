<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Entity\CollecteReference;
use App\Entity\LigneArticle;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\StatutRepository;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\ArticleRepository;

use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Service\FileUploader;
use App\Entity\Collecte;
use Proxies\__CG__\App\Entity\Livraison;
use App\Entity\Demande;

/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends Controller
{

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var FilterRepository
     */
    private $filterRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var articleDataService
     */
    private $articleDataService;


    public function __construct(ArticleRepository $articleRepository, ArticleDataService $articleDataService, LivraisonRepository $livraisonRepository, DemandeRepository $demandeRepository, CollecteRepository $collecteRepository, StatutRepository $statutRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, FilterRepository $filterRepository, RefArticleDataService $refArticleDataService)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->statutRepository = $statutRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->collecteRepository = $collecteRepository;
        $this->demandeRepository = $demandeRepository;
        $this->filterRepository = $filterRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->articleRepository = $articleRepository;
    }

    /**
     * @Route("/api", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $data['data'] = $this->refArticleDataService->getRefArticleData();

                $champs = $this->champsLibreRepository->getLabelAndIdAndTypage();;
                $column = [
                    [
                        "title" => 'Actions',
                        "data" => 'Actions',
                        "class" => 'fixe'
                    ],
                    [
                        "title" => 'Libellé',
                        "data" => 'Libellé',
                        "class" => 'fixe'
                    ],
                    [
                        "title" => 'Référence',
                        "data" => 'Référence',
                        "class" => 'fixe'
                    ],
                    [
                        "title" => 'Type',
                        "data" => 'Type',
                        "class" => 'fixe'
                    ],
                    [
                        "title" => 'Quantité',
                        "data" => 'Quantité',
                        "class" => 'fixe'
                    ],

                ];
                foreach ($champs as $champ) {
                    $column[] = [
                        "title" => ucfirst(mb_strtolower($champ['label'])),
                        "data" => $champ['label'],
                        "class" => 'libre'
                    ];
                }

                $data['column'] = $column;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $this->articleFournisseurRepository->countByReference($data['reference']);

            if ($refAlreadyExist) {
                return new JsonResponse(false);
            } else {
                $em = $this->getDoctrine()->getManager();
                $statut = ($data['statut'] === 'active' ? $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF) : $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF));
                $refArticle = new ReferenceArticle();
                $refArticle
                    ->setLibelle($data['libelle'])
                    ->setReference($data['reference'])
                    ->setCommentaire($data['commentaire'])
                    ->setQuantiteStock($data['quantite'] ?$data['quantite'] : 0)
                    ->setStatut($statut)
                    ->setTypeQuantite($data['type_quantite'] ?ReferenceArticle::TYPE_QUANTITE_REFERENCE : ReferenceArticle::TYPE_QUANTITE_ARTICLE)
                    ->setType($this->typeRepository->find($data['type']));
                $em->persist($refArticle);
                $em->flush();
                $champsLibreKey = array_keys($data);

                foreach ($champsLibreKey as $champs) {
                    if (gettype($champs) === 'integer') {
                        $valeurChampLibre = new ValeurChampsLibre();
                        $valeurChampLibre
                            ->setValeur($data[$champs])
                            ->addArticleReference($refArticle)
                            ->setChampLibre($this->champsLibreRepository->find($champs));
                        $em->persist($valeurChampLibre);
                        $em->flush();
                    }
                }

                $champsLibres = $this->champsLibreRepository->getLabelByCategory(ReferenceArticle::CATEGORIE);

                $rowCL = [];
                foreach ($champsLibres as $champLibre) {
                    $valeur = $this->valeurChampsLibreRepository->getByRefArticleANDChampsLibre($refArticle->getId(), $champLibre['id']);
                    $rowCL[$champLibre['label']] = ($valeur ?$valeur->getValeur() : "");
                }
                $rowDD = [
                    "id" => $refArticle->getId(),
                    "Libellé" => $refArticle->getLibelle(),
                    "Référence" => $refArticle->getReference(),
                    "Type" => ($refArticle->getType() ?$refArticle->getType()->getLabel() : ""),
                    "Quantité" => $refArticle->getQuantiteStock(),
                    'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                        'idRefArticle' => $refArticle->getId(),
                    ]),
                ];
                $rows = array_merge($rowCL, $rowDD);
                $response['new'] = $rows;

                return new JsonResponse($response);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reference_article_index",  methods="GET|POST")
     */
    public function index(): Response
    {
        $typeQuantite = [
            [
                'const' => 'QUANTITE_AR',
                'label' => 'référence',
            ],
            [
                'const' => 'QUANTITE_A',
                'label' => 'article',
            ]
        ];

        $champL = $this->champsLibreRepository->getLabelAndIdAndTypage();
        $champ[] = [
            'label' => 'Actions',
            'id' => 0,
            'typage' => ''
        ];
        $champ[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];
        $champ[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champ[] = [
            'label' => 'Type',
            'id' => 0,
            'typage' => 'list'
        ];
        $champ[] = [
            'label' => 'Quantité',
            'id' => 0,
            'typage' => 'number'
        ];

        $champs = array_merge($champ, $champL);

        return $this->render('reference_article/index.html.twig', [
            'champs' => $champs,
            'statuts' => $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE),
            'types' => $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE),
            'typeQuantite' => $typeQuantite,
            'filters' => $this->filterRepository->findBy(['utilisateur' => $this->getUser()]),
        ]);
    }

    /**
     * @Route("/api-modifier", name="reference_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $articleRef = $this->referenceArticleRepository->find($data);
            $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);

            if ($articleRef) {
                $data = $this->refArticleDataService->getDataEditForRefArticle($articleRef);

                $json = $this->renderView('reference_article/modalEditRefArticleContent.html.twig', [
                    'articleRef' => $articleRef,
                    'statut' => ($articleRef->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                    'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                    'types' => $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE),
                    'statuts' => $statuts,
                    'articlesFournisseur' => ($data['listArticlesFournisseur']),
                    'totalQuantity' => $data['totalQuantity']
                ]);
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reference_article_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find(intval($data['idRefArticle']));
            dump($data);
            if ($refArticle) {
                $response = $this->refArticleDataService->editRefArticle($refArticle, $data);
            } else {
                $response = false;
            }
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="reference_article_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            $rows = $refArticle->getId();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($refArticle);
            $entityManager->flush();

            $response['delete'] = $rows;
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/quantite", name="get_quantity_ref_article", options={"expose"=true})
     */
    public function getQuantityByRefArticleId(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $refArticleId = $request->request->get('refArticleId');
            $refArticle = $this->referenceArticleRepository->find($refArticleId);

            $quantity = $refArticle ? ($refArticle->getQuantiteStock() ?$refArticle->getQuantiteStock() : 0) : 0;

            return new JsonResponse($quantity);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_ref_articles", options={"expose"=true})
     */
    public function getRefArticles(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $refArticles = $this->referenceArticleRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $refArticles]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/plus-demande", name="plus_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function plusDemande(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            if (array_key_exists('livraison', $data) && $data['livraison']) {
                $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
                $demande = $this->demandeRepository->find($data['livraison']);
                if ($refArticle) {
                    $ligneArticle = new LigneArticle;
                    $ligneArticle
                        ->setReference($refArticle)
                        ->setDemande($demande)
                        ->setQuantite($data['quantitie']);
                    $em->persist($ligneArticle);
                } else {
                    $json = false;
                }
            } elseif (array_key_exists('collecte', $data) && $data['collecte']) {
                $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
                $collecte = $this->collecteRepository->find($data['collecte']);
                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                    $article = $this->articleRepository->find($data['article']);
                    $collecte->addArticle($article);
                } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $collecteReference = new CollecteReference;
                    $collecteReference
                        ->setCollecte($collecte)
                        ->setReferenceArticle($refArticle)
                        ->setQuantite($data['quantitie']);
                    $em->persist($collecteReference);
                } else {
                    $json = false;
                }
            } else {
                $json = false;
            }
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-plus-demande-content", name="ajax_plus_demande_content", options={"expose"=true}, methods="GET|POST")
     */
    public function ajaxPlusDemandeContent(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data);
            if ($refArticle) {
                $statutC = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_DEMANDE);
                $collectes = $this->collecteRepository->getByStatutAndUser($statutC, $this->getUser());

                $statutD = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
                $demandes = $this->demandeRepository->getByStatutAndUser($statutD, $this->getUser());

                $articleOrNo = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, false);
                $json = $this->renderView('reference_article/modalPlusDemandeContent.html.twig', [
                    'articleOrNo' => $articleOrNo,
                    'collectes' => $collectes,
                    'demandes' => $demandes
                ]);
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }
}
