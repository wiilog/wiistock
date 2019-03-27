<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\StatutRepository;

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

    public function __construct(StatutRepository $statutRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->statutRepository = $statutRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
    }

    /**
     * @Route("/refArticleAPI", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function refArticleApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $refs = $this->referenceArticleRepository->findAll();
                $rows = [];
                foreach ($refs as $refArticle) {
                    $url['edit'] = $this->generateUrl('reference_article_edit', ['id' => $refArticle->getId()]);

                    $rows[] = [
                        "id" => $refArticle->getId(),
                        "Libellé" => $refArticle->getLibelle(),
                        "Référence" => $refArticle->getReference(),
                        "Quantité" => $refArticle->getQuantiteStock(),
                        'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                            'url' => $url,
                            'idRefArticle' => $refArticle->getId()
                        ]),
                    ];
                }
                $data['data'] = $rows;
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
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setQuantiteStock($data['quantite'])
                ->setType($this->typeRepository->find($data['type']));
            // TODO récupérer statut
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
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reference_article_index",  methods="GET")
     */
    public function index(Request $request): Response
    {
        $typeQuantite =[
            [
                'const'=> 'QUANTITE_AR',
                'label'=> 'référence',
            ],
            [
                'const'=> 'QUANTITE_A',
                'label'=> 'article',
            ]
        ];

        return $this->render('reference_article/index.html.twig', [
            'types' => $this->typeRepository->getByCategoryLabel('référence article'),
            'typeQuantite'=> $typeQuantite,
            'statuts'=> $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE),
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
                    $valeurChampLibre = $this->valeurChampsLibreRepository->getByArticleType($articleRef->getId(), $type->getId());
                }
                $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);

                // construction du tableau des articles fournisseurs
                $listArticlesFournisseur = [];
                $articlesFournisseurs = $articleRef->getArticlesFournisseur();
                $totalQuantity = 0;
                foreach($articlesFournisseurs as $articleFournisseur) {
                    $quantity = 0;
                    foreach($articleFournisseur->getArticles() as $article) {
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
                    'types' => $this->typeRepository->getByCategoryLabel('référence article'),
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

            if($refArticle) {
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
                        if(!$valeurChampLibre) {
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
                $response = true;
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
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($refArticle);
            $entityManager->flush();
            return new JsonResponse();
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
