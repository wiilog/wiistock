<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\Filter;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampsLibre;
use App\Entity\CollecteReference;
use App\Entity\LigneArticle;
use App\Entity\CategorieCL;
use App\Entity\Fournisseur;

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
use App\Repository\LigneArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;

use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;

use App\Service\UserService;
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
use Symfony\Component\Serializer\Encoder\JsonEncode;
use App\Entity\ArticleFournisseur;
use App\Repository\FournisseurRepository;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends Controller
{
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;
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
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

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

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;


    public function __construct(\Twig_Environment $templating, EmplacementRepository $emplacementRepository, FournisseurRepository $fournisseurRepository, CategorieCLRepository $categorieCLRepository, LigneArticleRepository $ligneArticleRepository, ArticleRepository $articleRepository, ArticleDataService $articleDataService, LivraisonRepository $livraisonRepository, DemandeRepository $demandeRepository, CollecteRepository $collecteRepository, StatutRepository $statutRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, FilterRepository $filterRepository, RefArticleDataService $refArticleDataService, UserService $userService)
    {
        $this->emplacementRepository = $emplacementRepository;
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
        $this->userService = $userService;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->templating = $templating;
    }

    /**
     * @Route("/api-columns", name="ref_article_api_columns", options={"expose"=true}, methods="GET|POST")
     */
    public function apiColumns(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $columnsVisible = $this->getUser()->getColumnVisible();
            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_CEA);
            $category = CategoryType::ARTICLES_ET_REF_CEA;
            $champs = $this->champsLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

            $columns = [];
            if ($columnsVisible) {
                $columns = [
                    [
                        "title" => 'Actions',
                        "data" => 'Actions',
                        'name' => 'Actions',
                        "class" => (in_array('Actions', $columnsVisible) ? 'display' : 'hide'),

                    ],
                    [
                        "title" => 'Libellé',
                        "data" => 'Libellé',
                        'name' => 'Libellé',
                        "class" => (in_array('Libellé', $columnsVisible) ? 'display' : 'hide'),

                    ],
                    [
                        "title" => 'Référence',
                        "data" => 'Référence',
                        'name' => 'Référence',
                        "class" => (in_array('Référence', $columnsVisible) ? 'display' : 'hide'),
                    ],
                    [
                        "title" => 'Type',
                        "data" => 'Type',
                        'name' => 'Type',
                        "class" => (in_array('Type', $columnsVisible) ? 'display' : 'hide'),
                    ],
                    [
                        "title" => 'Quantité',
                        "data" => 'Quantité',
                        'name' => 'Quantité',
                        "class" => (in_array('Quantité', $columnsVisible) ? 'display' : 'hide'),
                    ],
                    [
                        "title" => 'Emplacement',
                        "data" => 'Emplacement',
                        'name' => 'Emplacement',
                        "class" => (in_array('Emplacement', $columnsVisible) ? 'display' : 'hide'),
                    ],
                    [
                        "title" => 'Commentaire',
                        "data" => 'Commentaire',
                        'name' => 'Commentaire',
                        "class" => (in_array('Commentaire', $columnsVisible) ? 'display' : 'hide'),
                    ],

                ];
                foreach ($champs as $champ) {
                    $columns[] = [
                        "title" => ucfirst(mb_strtolower($champ['label'])),
                        "data" => $champ['label'],
                        'name' => $champ['label'],
                        "class" => (in_array($champ['label'], $columnsVisible) ? 'display' : 'hide'),
                    ];
                }
            }

            return new JsonResponse($columns);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->refArticleDataService->getDataForDatatable($request->request);

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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $this->referenceArticleRepository->countByReference($data['reference']);

            if ($refAlreadyExist) {
                return new JsonResponse(['success' => false, 'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.']);
            }
            $requiredCreate = true;
            $type = $this->typeRepository->find($data['type']);

            if ($data['emplacement'] !== null) {
                $emplacement = $this->emplacementRepository->find($data['emplacement']);
            } else {
                $emplacement = null; //TODO gérer message erreur (faire un return avec msg erreur adapté -> à ce jour un return false correspond forcément à une réf déjà utilisée)
            };
            $CLRequired = $this->champsLibreRepository->getByTypeAndRequiredCreate($type);
            $msgMissingCL = '';
            foreach ($CLRequired as $CL) {
                if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                    $requiredCreate = false;
                    if (!empty($msgMissingCL)) $msgMissingCL .= ', ';
                    $msgMissingCL .= $CL['label'];
                }
            }

            if (!$requiredCreate) {
                return new JsonResponse(['success' => false, 'msg' => 'Veuillez renseigner les champs obligatoires : ' . $msgMissingCL]);
            }

            $em = $this->getDoctrine()->getManager();
            $statut = $this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, $data['statut']);

            switch($data['type_quantite']) {
                case 'article':
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                    break;
                default:
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_REFERENCE;
                    break;
            }
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setCommentaire($data['commentaire'])
                ->setTypeQuantite($typeArticle)
                ->setType($type)
                ->setEmplacement($emplacement);

            if ($statut) $refArticle->setStatut($statut);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $refArticle->setQuantiteStock($data['quantite'] ? max($data['quantite'], 0) : 0); // protection contre quantités négatives
            }
            foreach ($data['frl'] as $frl) {
                $fournisseurId = explode(';', $frl)[0];
                $ref = explode(';', $frl)[1];
                $label = explode(';', $frl)[2];
                $fournisseur = $this->fournisseurRepository->find(intval($fournisseurId));
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setReferenceArticle($refArticle)
                    ->setFournisseur($fournisseur)
                    ->setReference($ref)
                    ->setLabel($label);
                $em->persist($articleFournisseur);
            }
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

            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_CEA);
            $category = CategoryType::ARTICLES_ET_REF_CEA;
            $champsLibres = $this->champsLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

            $rowCL = [];
            foreach ($champsLibres as $champLibre) {
                $valeur = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($refArticle->getId(), $champLibre['id']);

                $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
            }
            $rowDD = [
                "id" => $refArticle->getId(),
                "Libellé" => $refArticle->getLibelle(),
                "Référence" => $refArticle->getReference(),
                "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
                "Quantité" => $refArticle->getQuantiteStock(),
                "Emplacement" => $emplacement,
                "Commentaire" => $refArticle->getCommentaire(),
                'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                    'idRefArticle' => $refArticle->getId(),
                ]),
            ];
            $rows = array_merge($rowCL, $rowDD);
            $response['new'] = $rows;
            $response['success'] = true;
            return new JsonResponse(['success' => true, 'new' => $rows]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reference_article_index",  methods="GET|POST", options={"expose"=true})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

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

        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_CEA);
        $category = CategoryType::ARTICLES_ET_REF_CEA;
        $champL = $this->champsLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        $champF[] = [
            'label' => 'Actions',
            'id' => 0,
            'typage' => ''
        ];
        $champF[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Type',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Quantité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Emplacement',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Commentaire',
            'id' => 0,
            'typage' => 'text'
        ];

        $champF[] = [
            'label' => Filter::CHAMP_FIXE_REF_ART_FOURN,
            'id' => 0,
            'typage' => 'text'
        ];

        // champs pour recherche personnalisée (uniquement de type texte)
		$champsLText = $this->champsLibreRepository->getByCategoryTypeAndCategoryCLAndText($category, $categorieCL);

		$champsFText[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];

        $champsFText[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];

        $champsFText[] = [
            'label' => 'Fournisseur',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Référence Article Fournisseur',
            'id' => 0,
            'typage' => 'text'

        ];

        $champs = array_merge($champF, $champL);
        $champsSearch = array_merge($champsFText, $champsLText);


        usort($champs, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
        });

		usort($champsSearch, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});

        $types = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLES_ET_REF_CEA);
        $emplacements = $this->emplacementRepository->findAll();
        $typeChampLibre =  [];
        $search = $this->getUser()->getRecherche();
        foreach ($types as $type) {
            $champsLibres = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($type['label'], $categorieCL);
            $typeChampLibre[] = [
                'typeLabel' =>  $type['label'],
                'typeId' => $type['id'],
                'champsLibres' => $champsLibres,
            ];
        }
        return $this->render('reference_article/index.html.twig', [
            'champs' => $champs,
            'champsSearch' => $champsSearch,
            'recherches' => $search,
            'columnsVisibles' => $this->getUser()->getColumnVisible(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'emplacements' => $emplacements,
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle = $this->referenceArticleRepository->find((int)$data['id']);

            if ($refArticle) {
                $json = $this->refArticleDataService->getViewEditRefArticle($refArticle, $data['isADemand']);
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle = $this->referenceArticleRepository->find(intval($data['idRefArticle']));
            if ($refArticle) {
                $response = $this->refArticleDataService->editRefArticle($refArticle, $data);
            } else {
                $response = ['success' => false, 'msg' => "Une erreur s'est produite lors de la modification de la référence."];
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            $rows = $refArticle->getId();
            $entityManager = $this->getDoctrine()->getManager();
            if (count($refArticle->getCollecteReferences()) > 0 || count($refArticle->getLigneArticles()) > 0) {
                return new JsonResponse(false, 250);
            }
            $entityManager->remove($refArticle);
            $entityManager->flush();

            $response['delete'] = $rows;
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/addFournisseur", name="ajax_render_add_fournisseur", options={"expose"=true}, methods="GET|POST")
     */
    public function addFournisseur(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $json =  $this->renderView('reference_article/fournisseurArticle.html.twig');
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/removeFournisseur", name="ajax_render_remove_fournisseur", options={"expose"=true}, methods="GET|POST")
     */
    public function removeFournisseur(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getManager();
            $em->remove($this->articleFournisseurRepository->find($data['articleF']));
            $em->flush();
            $json =  $this->renderView('reference_article/fournisseurArticleContent.html.twig', [
                'articles' => $this->articleFournisseurRepository->findByRefArticle($data['articleRef']),
                'articleRef' => $this->referenceArticleRepository->find($data['articleRef'])
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/quantite", name="get_quantity_ref_article", options={"expose"=true})
     */
    public function getQuantityByRefArticleId(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticleId = $request->request->get('refArticleId');

            $refArticle = $this->referenceArticleRepository->find($refArticleId);

            $quantity = $refArticle ? ($refArticle->getQuantiteStock() ? $refArticle->getQuantiteStock() : 0) : 0;

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
            $json = true;

            //edit Refrence Article
            $refArticle = (isset($data['refArticle']) ? $this->referenceArticleRepository->find($data['refArticle']) : '');
            //ajout demande
            if (array_key_exists('livraison', $data) && $data['livraison']) {
                $demande = $this->demandeRepository->find($data['livraison']);
                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $this->refArticleDataService->editRefArticle($refArticle, $data);
                    if ($this->ligneArticleRepository->countByRefArticleDemande($refArticle, $demande) < 1) {
                        $ligneArticle = new LigneArticle;
                        $ligneArticle
                            ->setReference($refArticle)
                            ->setDemande($demande)
                            ->setQuantite(max((int)$data['quantitie'], 0)); // protection contre quantités négatives

                        $em->persist($ligneArticle);
                    } else {
                        $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $demande);
                        /** @var LigneArticle $ligneArticle */
                        $ligneArticle
                            ->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0)); // protection contre quantités négatives
                    }
                } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                    $this->articleDataService->editArticle($data);
                    $article = $this->articleRepository->find($data['article']);
                    $article->setQuantiteAPrelever(max($data['quantitie'], 0)); // protection contre quantités négatives
                    $demande->addArticle($article);
                } else {
                    $json = false; //TOOD gérer message erreur
                }
            } elseif (array_key_exists('collecte', $data) && $data['collecte']) {
                $collecte = $this->collecteRepository->find($data['collecte']);
                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                    //TODO patch temporaire CEA
                    $fournisseurTemp = $this->fournisseurRepository->findOneByCodeReference('A_DETERMINER');
                    if (!$fournisseurTemp) {
                        $fournisseurTemp = new Fournisseur();
                        $fournisseurTemp
                            ->setCodeReference('A_DETERMINER')
                            ->setNom('A DETERMINER');
                        $em->persist($fournisseurTemp);
                    }
                    $toInsert = new Article();
                    $index = $this->articleFournisseurRepository->countByRefArticle($refArticle);
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF);
                    $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                    $ref = $date->format('YmdHis');
                    $articleFournisseur = new ArticleFournisseur();
                    $articleFournisseur
                        ->setReferenceArticle($refArticle)
                        ->setFournisseur($fournisseurTemp)
                        ->setReference($refArticle->getReference())
                        ->setLabel('A déterminer -' . $index);
                    $em->persist($articleFournisseur);
                    $toInsert
                        ->setLabel($refArticle->getLibelle() . '-' . $index)
                        ->setConform(true)
                        ->setStatut($statut)
                        ->setReference($ref . '-' . $index)
                        ->setQuantite(max($data['quantitie'], 0)) // protection contre quantités négatives
                        ->setEmplacement($collecte->getPointCollecte())
                        ->setArticleFournisseur($articleFournisseur)
                        ->setType($refArticle->getType());
                    $em->persist($toInsert);
                    $collecte->addArticle($toInsert);
                    //TODO fin patch temporaire CEA (à remplacer par lignes suivantes)
//                    $article = $this->articleRepository->find($data['article']);
//                    $collecte->addArticle($article);
                } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $collecteReference = new CollecteReference;
                    $collecteReference
                        ->setCollecte($collecte)
                        ->setReferenceArticle($refArticle)
                        ->setQuantite(max((int)$data['quantitie'], 0)); // protection contre quantités négatives
                    $em->persist($collecteReference);
                } else {
                    $json = false; //TOOD gérer message erreur
                }
            } else {
                $json = false; //TOOD gérer message erreur
            }
            $em->flush();

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-plus-demande-content", name="ajax_plus_demande_content", options={"expose"=true}, methods="GET|POST")
     */
    public function ajaxPlusDemandeContent(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['id']);
            if ($refArticle) {
                $statutC = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_BROUILLON);
                $collectes = $this->collecteRepository->getByStatutAndUser($statutC, $this->getUser());

                $statutD = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
                $demandes = $this->demandeRepository->getByStatutAndUser($statutD, $this->getUser());

                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    if ($refArticle) {
                        $editChampLibre  = $this->refArticleDataService->getViewEditRefArticle($refArticle, true);
                    } else {
                        $editChampLibre = false;
                    }
                } else {
                    //TODO patch temporaire CEA
                    if ($refArticle->getStatut()->getNom() === ReferenceArticle::STATUT_INACTIF && $data['demande'] === 'collecte') {
                        $response = [
                            'plusContent' => $this->renderView('reference_article/modalPlusDemandeTemp.html.twig', [
                                'collectes' => $collectes
                            ]),
                            'temp' => true
                        ];
                        return new JsonResponse($response);
                    }
                    //TODO fin de patch temporaire CEA
                    $editChampLibre = false;
                }

                $articleOrNo  = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, $data['demande'], false);

                $json = [
                    'plusContent' => $this->renderView(
                        'reference_article/modalPlusDemandeContent.html.twig',
                        [
                            'articleOrNo' => $articleOrNo,
                            'collectes' => $collectes,
                            'demandes' => $demandes
                        ]
                    ),
                    'editChampLibre' => $editChampLibre,
                ];
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    //    /**
    //     * @Route("/get-RefArticle", name="get_refArticle_in_reception", options={"expose"=true})
    //     */
    //    public function getRefArticleInReception(Request $request): Response
    //    {
    //        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
    //            $refArticle  = $this->referenceArticleRepository->find($data['referenceArticle']);
    //            if ($refArticle) {
    //                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
    //                    $json  = $this->renderView('reference_article/newRefArticleByReference.html.twig');
    //                } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
    //                    $json  = $this->renderView('reference_article/newRefArticleByArticle.html.twig', [
    //                        'articlesFournisseurs' => $this->articleFournisseurRepository->findALL(),
    //                    ]);
    //                } else {
    //                    $json = false;
    //                }
    //            } else {
    //                $json = false;
    //            }
    //            return new JsonResponse($json);
    //        }
    //        throw new NotFoundHttpException("404");
    //    }

    /**
     * @Route("/colonne-visible", name="save_column_visible", options={"expose"=true}, methods="GET|POST")
     */
    public function saveColumnVisible(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $champs = array_keys($data);
            $user  = $this->getUser();
            /** @var $user Utilisateur */
            $user->setColumnVisible($champs);
            $em  = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir", name="reference_article_show", options={"expose"=true})
     */
    public function showRefArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle  = $this->referenceArticleRepository->find($data);

            $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            $articlesFournisseur = $this->articleFournisseurRepository->findByRefArticle($refArticle->getId());
            $type = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLES_ET_REF_CEA);
            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_CEA);
            $typeChampLibre =  [];
            foreach ($type as $label) {
                $champsLibresComplet = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($label['label'], $categorieCL);
                $champsLibres = [];
                //création array edit pour vue
                foreach ($champsLibresComplet as $champLibre) {
                    $valeurChampRefArticle = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($refArticle->getId(), $champLibre);
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampRefArticle,
                    ];
                }
                $typeChampLibre[] = [
                    'typeLabel' =>  $label['label'],
                    'typeId' => $label['id'],
                    'champsLibres' => $champsLibres,
                ];
            }
            //reponse Vue + data

            if ($refArticle) {
                $view =  $this->templating->render('reference_article/modalShowRefArticleContent.html.twig', [
                    'articleRef' => $refArticle,
                    'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                    'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                    'typeChampsLibres' => $typeChampLibre,
                    'articlesFournisseur' => ($data['listArticlesFournisseur']),
                    'totalQuantity' => $data['totalQuantity'],
                    'articles' => $articlesFournisseur,

                ]);

                $json = $view;
            } else {
                return $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/exporter/{min}/{max}", name="reference_article_export", options={"expose"=true}, methods="GET|POST")
     */
    public function exportAll(Request $request, $max, $min): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = [];
            $data['values'] = [];
            $headersCL = [];
            foreach ($this->champsLibreRepository->findAll() as $champLibre) {
                $headersCL[] = $champLibre->getLabel();
            }
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLES_ET_REF_CEA);
            $articles = $this->referenceArticleRepository->findAll();
            $total = count($articles);
            if ($max > $total) $max = $total;
            $toIterate = array_slice($articles, $min, $max);
            foreach ($toIterate as $article) {
                $data['values'][] = $this->buildInfos($article, $listTypes, $headersCL);
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/export-donnees", name="exports_params")
     */
    public function renderParams()
    {
        return $this->render('exports/exportsMenu.html.twig');
    }

    /**
     * @Route("/total", name="get_total_and_headers_ref", options={"expose"=true}, methods="GET|POST")
     */
    public function total(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data['total'] = $this->referenceArticleRepository->countAll();
            $data['headers'] = ['reference', 'libelle', 'quantitée', 'type', 'type_quantite', 'statut', 'commentaire', 'emplacement'];
            foreach ($this->champsLibreRepository->findAll() as $champLibre) {
                $data['headers'][] = $champLibre->getLabel();
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    public function buildInfos(ReferenceArticle $ref, $listTypes, $headersCL)
    {
        $refData[] = $ref->getReference();
        $refData[] = $ref->getLibelle();
        $refData[] = $ref->getQuantiteStock();
        $refData[] = $ref->getType()->getLabel();
        $refData[] = $ref->getTypeQuantite();
        $refData[] = $ref->getStatut()->getNom();
        $refData[] = strip_tags($ref->getCommentaire());
        $refData[] = $ref->getEmplacement() ? $ref->getEmplacement()->getLabel() : '';
        $categorieCL = $this->categorieCLRepository->findOneByLabel('reference CEA');
        $champsLibres = [];
        foreach ($listTypes as $type) {
            $listChampsLibres = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($type['label'], $categorieCL);
            foreach ($listChampsLibres as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampsLibreRepository->findOneByRefArticleANDChampsLibre($ref->getId(), $champLibre);
                if ($valeurChampRefArticle) $champsLibres[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
            }
        }
        foreach ($headersCL as $type) {
            if (array_key_exists($type, $champsLibres)) {
                $refData[] = $champsLibres[$type];
            } else {
                $refData[] = '';
            }
        }
        return implode(';', $refData);
    }

	/**
	 * @Route("/type-quantite", name="get_quantity_type", options={"expose"=true}, methods="GET|POST")
	 */
    public function getQuantityType(Request $request)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$reference = $this->referenceArticleRepository->find($data['id']);

			$quantityType = $reference ? $reference->getTypeQuantite() : '';

			return new JsonResponse($quantityType);
		}
		throw new NotFoundHttpException('404');
	}
}
