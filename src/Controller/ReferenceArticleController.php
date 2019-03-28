<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\StatutRepository;

use App\Service\RefArticleDataService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Service\FileUploader;

/**
 * @Route("/reference_article")
 */
class ReferenceArticleController extends Controller
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

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


    public function __construct(StatutRepository $statutRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, FilterRepository $filterRepository, RefArticleDataService $refArticleDataService)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->statutRepository = $statutRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->filterRepository = $filterRepository;
        $this->refArticleDataService = $refArticleDataService;
    }

    /**
     * @Route("/refArticleAPI", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function refArticleApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $data['data'] = $this->refArticleDataService->getRefArticleData();

                $champs = $this->champsLibreRepository->getLabelAndIdAndTypage();;
                $column = [
                    [
                        "title" => 'Libellé',
                        "data" => 'Libellé'
                    ],
                    [
                        "title" => 'Référence',
                        "data" => 'Référence'
                    ],
                    [
                        "title" => 'Type',
                        "data" => 'Type'
                    ],
                    [
                        "title" => 'Quantité',
                        "data" => 'Quantité'
                    ],
                    [
                        "title" => 'Actions',
                        "data" => 'Actions'
                    ],

                ];
                foreach ($champs as $champ) {
                    $column[] = [
                        "title" => $champ['label'],
                        "data" => $champ['label']
                    ];
                }

                $data['column'] = $column;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/nouveau", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $statut = ($data['statut'] === 'active' ? $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF) : $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF));
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setQuantiteStock($data['quantite']? $data['quantite'] : 0)
                ->setStatut($statut)
                ->setTypeQuantite($data['type_quantite'] ? ReferenceArticle::TYPE_QUANTITE_REFERENCE: ReferenceArticle::TYPE_QUANTITE_ARTICLE)
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
                        $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
                    }
                    $rowDD = [
                        "id" => $refArticle->getId(),
                        "Libellé" => $refArticle->getLibelle(),
                        "Référence" => $refArticle->getReference(),
                        "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
                        "Quantité" => $refArticle->getQuantiteStock(),
                        'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                            'idRefArticle' => $refArticle->getId()
                        ]),
                    ];
                    $rows = array_merge($rowCL, $rowDD);
                    $response['new'] = $rows;

            return new JsonResponse($response);
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
        $champ[]=[
            'label'=> 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];
        $champ[]=[
            'label'=> 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champ[]=[
            'label'=> 'Type',
            'id' => 0,
            'typage' => 'list'
        ];
        $champ[]=[
            'label'=> 'Quantité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champ[]=[
            'label'=> 'Actions',
            'id' => 0,
            'typage' => ''
        ];

        $champs = array_merge($champ, $champL);

        return $this->render('reference_article/index.html.twig', [
            'champs' => $champs,
            'statuts' => $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE),
            'types' => $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE),
            'typeQuantite' => $typeQuantite,
            'filters' => $this->filterRepository->findBy(['utilisateur' => $this->getUser()])
        ]);
    }

    /**
     * @Route("/editApi", name="reference_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $articleRef = $this->referenceArticleRepository->find($data);

            if ($articleRef) {
                $type = $articleRef->getType();
                if ($type) {
                    $valeurChampLibre = $this->valeurChampsLibreRepository->getByRefArticleAndType($articleRef->getId(), $type->getId());
                }

                $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);

                // construction du tableau des articles fournisseurs
                $listArticlesFournisseur = [];
                $articlesFournisseurs = $articleRef->getArticlesFournisseur();
                $totalQuantity = 0;
                foreach ($articlesFournisseurs as $articleFournisseur) {
                    $quantity = 0;
                    foreach ($articleFournisseur->getArticles() as $article) {
                        $quantity += $article->getQuantite();
                    }
                    $totalQuantity += $quantity;

                    $listArticlesFournisseur[] = [
                        'fournisseurRef' => $articleFournisseur->getFournisseur()->getCodeReference(),
                        'label' => $articleFournisseur->getLabel(),
                        'fournisseurName' => $articleFournisseur->getFournisseur()->getNom(),
                        'quantity' => $quantity
                    ];
                }

                $json = $this->renderView('reference_article/modalEditRefArticleContent.html.twig', [
                    'articleRef' => $articleRef,
                    'statut' => ($articleRef->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                    'valeurChampsLibre' => isset($valeurChampLibre) ? $valeurChampLibre : null,
                    'types' => $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE),
                    'statuts' => $statuts,
                    'articlesFournisseur' => $listArticlesFournisseur,
                    'totalQuantity' => $totalQuantity
                ]);
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="reference_article_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $entityManager = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find(intval($data['idRefArticle']));

            if ($refArticle) {
                if (isset($data['reference'])) $refArticle->setReference($data['reference']);
                if (isset($data['libelle'])) $refArticle->setLibelle($data['libelle']);
                if (isset($data['quantite'])) $refArticle->setQuantiteStock(intval($data['quantite']));
                if (isset($data['statut'])) {
                    $statutLabel = ($data['statut'] == 1) ? ReferenceArticle::STATUT_ACTIF : ReferenceArticle::STATUT_INACTIF;
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, $statutLabel);
                    $refArticle->setStatut($statut);
                }
                if (isset($data['type'])) {
                    $type = $this->typeRepository->find(intval($data['type']));
                    if ($type) $refArticle->setType($type);
                }
                if (isset($data['type_quantite'])) $refArticle->setTypeQuantite($data['type_quantite']);

                $entityManager->flush();

                $champsLibreKey = array_keys($data);
                foreach ($champsLibreKey as $champ) {
                    if (gettype($champ) === 'integer') {
                        $valeurChampLibre = $this->valeurChampsLibreRepository->getByRefArticleANDChampsLibre($data['idRefArticle'], $champ);
                        // si la valeur n'existe pas, on la crée
                        if (!$valeurChampLibre) {
                            $valeurChampLibre = new ValeurChampsLibre();
                            $valeurChampLibre
                                ->addArticleReference($refArticle)
                                ->setChampLibre($this->champsLibreRepository->find($champ));
                            $entityManager->persist($valeurChampLibre);
                        }
                        $valeurChampLibre->setValeur($data[$champ]);
                        $entityManager->flush();
                    }
                }
                
                $champsLibres = $this->champsLibreRepository->getLabelByCategory(ReferenceArticle::CATEGORIE);
                    $rowCL = [];
                    $rowDD = [];
                    foreach ($champsLibres as $champLibre) {
                        $valeur = $this->valeurChampsLibreRepository->getByRefArticleANDChampsLibre($refArticle->getId(), $champLibre['id']);
                        $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
                    }
                    $rowDD = [
                        "id" => $refArticle->getId(),
                        "Libellé" => $refArticle->getLibelle(),
                        "Référence" => $refArticle->getReference(),
                        "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
                        "Quantité" => $refArticle->getQuantiteStock(),
                        'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                            'idRefArticle' => $refArticle->getId()
                        ]),
                    ];
                    $rows = array_merge($rowCL, $rowDD);
                    $response['id'] = $refArticle->getId();
                    $response['edit'] = $rows;
                    dump($response);

            } else {
                $response = false;
            }
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimerRefArticle", name="reference_article_delete", options={"expose"=true}, methods="GET|POST")
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
            dump($response);

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

            $quantity = $refArticle ? ($refArticle->getQuantiteDisponible() ? $refArticle->getQuantiteDisponible() : 0) : 0;

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
}
