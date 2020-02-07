<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\FiltreRef;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Entity\CollecteReference;
use App\Entity\CategorieCL;
use App\Entity\Fournisseur;
use App\Entity\Collecte;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\StatutRepository;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\ArticleRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;

use App\Service\CSVExportService;
use App\Service\GlobalParamService;
use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use App\Entity\Demande;
use App\Entity\ArticleFournisseur;
use App\Repository\FournisseurRepository;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends AbstractController
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
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

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
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

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

	/**
	 * @var SpecificService
	 */
    private $specificService;

	/**
	 * @var ParametreRepository
	 */
    private $parametreRepository;

	/**
	 * @var ParametreRoleRepository
	 */
    private $parametreRoleRepository;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var InventoryCategoryRepository
     */
    private $inventoryCategoryRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var object|string
     */
    private $user;

    private $CSVExportService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                GlobalParamService $globalParamService,
                                ParametreRoleRepository $parametreRoleRepository,
                                ParametreRepository $parametreRepository,
                                SpecificService $specificService,
                                \Twig_Environment $templating,
                                EmplacementRepository $emplacementRepository,
                                FournisseurRepository $fournisseurRepository,
                                CategorieCLRepository $categorieCLRepository,
                                LigneArticleRepository $ligneArticleRepository,
                                ArticleRepository $articleRepository,
                                ArticleDataService $articleDataService,
                                LivraisonRepository $livraisonRepository,
                                DemandeRepository $demandeRepository,
                                CollecteRepository $collecteRepository,
                                StatutRepository $statutRepository,
                                ValeurChampLibreRepository $valeurChampLibreRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                TypeRepository  $typeRepository,
                                ChampLibreRepository $champsLibreRepository,
                                ArticleFournisseurRepository $articleFournisseurRepository,
                                FiltreRefRepository $filtreRefRepository,
                                RefArticleDataService $refArticleDataService,
                                UserService $userService,
                                InventoryCategoryRepository $inventoryCategoryRepository,
                                InventoryFrequencyRepository $inventoryFrequencyRepository,
                                MouvementStockRepository $mouvementStockRepository,
                                CSVExportService $CSVExportService)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champLibreRepository = $champsLibreRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->statutRepository = $statutRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->collecteRepository = $collecteRepository;
        $this->demandeRepository = $demandeRepository;
        $this->filtreRefRepository = $filtreRefRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->templating = $templating;
        $this->specificService = $specificService;
        $this->parametreRepository = $parametreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->globalParamService = $globalParamService;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->CSVExportService = $CSVExportService;
    }

    /**
     * @Route("/api-columns", name="ref_article_api_columns", options={"expose"=true}, methods="GET|POST")
     */
    public function apiColumns(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }

            $currentUser = $this->getUser(); /** @var Utilisateur $currentUser */
            $columnsVisible = $currentUser->getColumnVisible();
            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
            $category = CategoryType::ARTICLE;
            $champs = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

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
					"title" => 'Statut',
					"data" => 'Statut',
					'name' => 'Statut',
					"class" => (in_array('Statut', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Quantité disponible',
					"data" => 'Quantité',
					'name' => 'Quantité disponible',
					"class" => (in_array('Quantité disponible', $columnsVisible) ? 'display' : 'hide'),
				],
                [
                    "title" => 'Code barre',
                    "data" => 'Code barre',
                    'name' => 'Code barre',
                    "class" => (in_array('Code barre', $columnsVisible) ? 'display' : 'hide'),

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
				[
					"title" => 'Seuil d\'alerte',
					"data" => 'Seuil d\'alerte',
					'name' => 'Seuil d\'alerte',
					"class" => (in_array('Seuil d\'alerte', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Seuil de sécurité',
					"data" => 'Seuil de sécurité',
					'name' => 'Seuil de sécurité',
					"class" => (in_array('Seuil de sécurité', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Prix unitaire',
					"data" => 'Prix unitaire',
					'name' => 'Prix unitaire',
					"class" => (in_array('Prix unitaire', $columnsVisible) ? 'display' : 'hide'),
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->refArticleDataService->getRefArticleDataByParams($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $this->referenceArticleRepository->countByReference($data['reference']);

            if ($refAlreadyExist) {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
					'codeError' => 'DOUBLON-REF'
				]);
            }
            $requiredCreate = true;
            $type = $this->typeRepository->find($data['type']);

            if ($data['emplacement'] !== null) {
                $emplacement = $this->emplacementRepository->find($data['emplacement']);
            } else {
                $emplacement = null; //TODO gérer message erreur (faire un return avec msg erreur adapté -> à ce jour un return false correspond forcément à une réf déjà utilisée)
            };
            $CLRequired = $this->champLibreRepository->getByTypeAndRequiredCreate($type);
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
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(ReferenceArticle::CATEGORIE, $data['statut']);

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
                ->setPrixUnitaire(max(0, $data['prix']))
                ->setType($type)
                ->setIsUrgent($data['urgence'])
                ->setEmplacement($emplacement)
				->setBarCode($this->refArticleDataService->generateBarCode());

            if ($data['limitSecurity']) {
            	$refArticle->setLimitSecurity($data['limitSecurity']);
			}
            if ($data['limitWarning']) {
            	$refArticle->setLimitWarning($data['limitWarning']);
			}
            if ($data['categorie']) {
            	$category = $this->inventoryCategoryRepository->find($data['categorie']);
            	if ($category) $refArticle->setCategory($category);
			}
            if ($statut) $refArticle->setStatut($statut);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $refArticle->setQuantiteStock($data['quantite'] ? max($data['quantite'], 0) : 0); // protection contre quantités négatives
            }
            foreach ($data['frl'] as $frl) {
                $fournisseurId = explode(';', $frl)[0];
                $ref = explode(';', $frl)[1];
                $label = explode(';', $frl)[2];
                $fournisseur = $this->fournisseurRepository->find(intval($fournisseurId));

                // on vérifie que la référence article fournisseur n'existe pas déjà
                $refFournisseurAlreadyExist = $this->articleFournisseurRepository->findByReferenceArticleFournisseur($ref);
                if ($refFournisseurAlreadyExist) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Ce nom de référence article fournisseur existe déjà. Vous ne pouvez pas le recréer.'
                    ]);
                }

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
            $champsLibresKey = array_keys($data);

            foreach ($champsLibresKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->setValeur(is_array($data[$champs]) ? implode(";", $data[$champs]) : $data[$champs])
                        ->addArticleReference($refArticle)
                        ->setChampLibre($this->champLibreRepository->find($champs));
                    $em->persist($valeurChampLibre);
                    $em->flush();
                }
            }

            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
            $category = CategoryType::ARTICLE;
            $champsLibres = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

            $rowCL = [];
            foreach ($champsLibres as $champLibre) {
                $valeur = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre['id']);

                $rowCL[$champLibre['label']] = ($valeur ? $valeur->getValeur() : "");
            }
            $rowDD = [
                "id" => $refArticle->getId(),
                "Libellé" => $refArticle->getLibelle(),
                "Référence" => $refArticle->getReference(),
                "Type" => ($refArticle->getType() ? $refArticle->getType()->getLabel() : ""),
                "Quantité" => $refArticle->getQuantiteStock(),
                "Emplacement" => $emplacement,
                "Statut" => $refArticle->getStatut(),
                "Commentaire" => $refArticle->getCommentaire(),
                "Code barre" => $refArticle->getBarCode() ?? '',
                "Seuil de sécurité" => $refArticle->getLimitSecurity() ?? "",
                "Seuil d'alerte" => $refArticle->getLimitWarning() ?? "",
                "Prix unitaire" => $refArticle->getPrixUnitaire() ?? "",
                'Actions' => $this->renderView('reference_article/datatableReferenceArticleRow.html.twig', [
                    'idRefArticle' => $refArticle->getId(),
					'isActive' => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF : 0,
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
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
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

        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
        $category = CategoryType::ARTICLE;
        $champL = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
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
            'label' => 'Code barre',
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
            'label' => 'Statut',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Quantité disponible',
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
            'label' => 'Seuil de sécurité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Seuil d\'alerte',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Prix unitaire',
            'id' => 0,
            'typage' => 'number'
        ];

        // champs pour recherche personnalisée (uniquement de type texte ou liste)
		$champsLText = $this->champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_TEXT);
		$champsLTList = $this->champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_LIST);

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
            'label' => 'Code barre',
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
        $champsSearch = array_merge($champsFText, $champsLText, $champsLTList);

        usort($champs, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
        });

		usort($champsSearch, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});

        $types = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
        $inventoryCategories = $this->inventoryCategoryRepository->findAll();
        $emplacements = $this->emplacementRepository->findAll();
        $typeChampLibre =  [];
        $search = $this->getUser()->getRecherche();
        foreach ($types as $type) {
            $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' =>  $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }
        $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($this->getUser(), FiltreRef::CHAMP_FIXE_STATUT);

        return $this->render('reference_article/index.html.twig', [
            'champs' => $champs,
            'champsSearch' => $champsSearch,
            'recherches' => $search,
            'columnsVisibles' => $this->getUser()->getColumnVisible(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'emplacements' => $emplacements,
            'typeQuantite' => $typeQuantite,
            'filters' => $this->filtreRefRepository->findByUserExceptChampFixe($this->getUser(), FiltreRef::CHAMP_FIXE_STATUT),
            'categories' => $inventoryCategories,
            'wantInactif' => !empty($filter) && $filter->getValue() === Article::STATUT_INACTIF
        ]);
    }

    /**
     * @Route("/api-modifier", name="reference_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refId = intval($data['idRefArticle']);
            $refArticle = $this->referenceArticleRepository->find($refId);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $this->referenceArticleRepository->countByReference($data['reference'], $refId);

            if ($refAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
                    'codeError' => 'DOUBLON-REF'
                ]);
            }
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            $rows = $refArticle->getId();
            $entityManager = $this->getDoctrine()->getManager();
            if (count($refArticle->getCollecteReferences()) > 0
                || count($refArticle->getLigneArticles()) > 0
                || count($refArticle->getReceptionReferenceArticles()) > 0
                || count($refArticle->getArticlesFournisseur()) > 0) {
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
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
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
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $quantity = false;

            $refArticleId = $request->request->get('refArticleId');
            $refArticle = $this->referenceArticleRepository->find($refArticleId);

            if ($refArticle) {
				if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
					$quantity = $refArticle->getQuantiteStock();
				}
			}

            return new JsonResponse($quantity);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/autocomplete-ref/{activeOnly}/type/{typeQuantity}", name="get_ref_articles", options={"expose"=true}, methods="GET|POST")
	 *
	 * @param Request $request
	 * @param bool $activeOnly
	 * @return JsonResponse
	 */
    public function getRefArticles(Request $request, $activeOnly = false, $typeQuantity = null)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $refArticles = $this->referenceArticleRepository->getIdAndRefBySearch($search, $activeOnly, $typeQuantity);

            return new JsonResponse(['results' => $refArticles]);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/autocomplete-ref-and-article/{activeOnly}", name="get_ref_and_articles", options={"expose"=true}, methods="GET|POST")
	 *
	 * @param Request $request
	 * @param bool $activeOnly
	 * @return JsonResponse
	 */
	public function getRefAndArticles(Request $request, $activeOnly = false)
	{
		if ($request->isXmlHttpRequest()) {
			$search = $request->query->get('term');

			$refArticles = $this->referenceArticleRepository->getIdAndRefBySearch($search, $activeOnly);
			$articles = $this->articleRepository->getIdAndRefBySearch($search, $activeOnly);

			return new JsonResponse(['results' => array_merge($articles, $refArticles)]);
		}
		throw new NotFoundHttpException("404");
	}

    /**
     * @Route("/plus-demande", name="plus_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function plusDemande(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $json = true;

            $refArticle = (isset($data['refArticle']) ? $this->referenceArticleRepository->find($data['refArticle']) : '');

            $statusName = $refArticle->getStatut() ? $refArticle->getStatut()->getNom() : '';
            if ($statusName == ReferenceArticle::STATUT_ACTIF) {

				if (array_key_exists('livraison', $data) && $data['livraison']) {
					$json = $this->refArticleDataService->addRefToDemand($data, $refArticle);
					if ($json === 'article') {
						$this->articleDataService->editArticle($data);
						$json = true;
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
						$newArticle = new Article();
						$index = $this->articleFournisseurRepository->countByRefArticle($refArticle);
						$statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_INACTIF);
						$date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
						$ref = $date->format('YmdHis');
						$articleFournisseur = new ArticleFournisseur();
						$articleFournisseur
							->setReferenceArticle($refArticle)
							->setFournisseur($fournisseurTemp)
							->setReference($refArticle->getReference())
							->setLabel('A déterminer -' . $index);
						$em->persist($articleFournisseur);
						$newArticle
							->setLabel($refArticle->getLibelle() . '-' . $index)
							->setConform(true)
							->setStatut($statut)
							->setReference($ref . '-' . $index)
							->setQuantite(max($data['quantite'], 0)) // protection contre quantités négatives
							//TODO quantite, quantitie ?
							->setEmplacement($collecte->getPointCollecte())
							->setArticleFournisseur($articleFournisseur)
							->setType($refArticle->getType())
							->setBarCode($this->articleDataService->generateBarCode());
						$em->persist($newArticle);
						$collecte->addArticle($newArticle);
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
			} else {
            	$json = false;
			}

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
                $collectes = $this->collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

                $statutD = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
                $demandes = $this->demandeRepository->findByStatutAndUser($statutD, $this->getUser());

                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    if ($refArticle) {
                        $editChampLibre  = $this->refArticleDataService->getViewEditRefArticle($refArticle, true);
                    } else {
                        $editChampLibre = false;
                    }
                } else {
                    //TODO patch temporaire CEA
					$isCea = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);
                    if ($isCea && $refArticle->getStatut()->getNom() === ReferenceArticle::STATUT_INACTIF && $data['demande'] === 'collecte') {
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

				$byRef = $this->userService->hasParamQuantityByRef();
                $articleOrNo  = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, $data['demande'], false, $byRef);

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
					'byRef' => $byRef && $refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE
				];
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible", options={"expose"=true}, methods="GET|POST")
     */
    public function saveColumnVisible(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
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
     * @Route("/est-urgent", name="is_urgent", options={"expose"=true}, methods="GET|POST")
     */
    public function isUrgent(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $id = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticle = $this->referenceArticleRepository->find($id);
            return new JsonResponse($referenceArticle->getIsUrgent() ?? false);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir", name="reference_article_show", options={"expose"=true})
     */
    public function show(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle  = $this->referenceArticleRepository->find($data);

            $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            $articlesFournisseur = $this->articleFournisseurRepository->findByRefArticle($refArticle->getId());
            $types = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);

            $typeChampLibre =  [];
            foreach ($types as $type) {
                $champsLibresComplet = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);

                $champsLibres = [];
                foreach ($champsLibresComplet as $champLibre) {
                    $valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre);
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
                    'typeLabel' =>  $type->getLabel(),
					'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                ];
            }
            //reponse Vue + data

            if ($refArticle) {
                $view =  $this->templating->render('reference_article/modalShowRefArticleContent.html.twig', [
                    'articleRef' => $refArticle,
                    'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                    'valeurChampLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
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
            foreach ($this->champLibreRepository->findAll() as $champLibre) {
                $headersCL[] = $champLibre->getLabel();
            }
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLE);
            $references = $this->referenceArticleRepository->getBetweenLimits($min, $max-$min);
            foreach ($references as $reference) {
                $data['values'][] = $this->buildInfos($reference, $listTypes, $headersCL);
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
            $data['headers'] = [
                'reference',
                'libelle',
                'quantite',
                'type',
                'type_quantite',
                'statut',
                'commentaire',
                'emplacement',
                'fournisseurs',
                'articles fournisseurs',
                'seuil securite',
                'seuil alerte',
                'prix unitaire',
                'code barre'
            ];
            foreach ($this->champLibreRepository->findAll() as $champLibre) {
                $data['headers'][] = $champLibre->getLabel();
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @param ReferenceArticle $ref
	 * @param array $listTypes
	 * @param string[] $headersCL
	 * @return string
	 */
    public function buildInfos(ReferenceArticle $ref, $listTypes, $headersCL)
    {
    	$listFournisseurAndAF = $this->fournisseurRepository->getNameAndRefArticleFournisseur($ref);

    	$arrayAF = $arrayF = [];

    	foreach ($listFournisseurAndAF as $fournisseurAndAF) {
    		$arrayAF[] = $fournisseurAndAF['reference'];
    		$arrayF[] = $fournisseurAndAF['nom'];
		}

    	$stringArticlesFournisseur = implode(' / ', $arrayAF);
    	$stringFournisseurs = implode(' / ', $arrayF);

        $refData[] = $this->CSVExportService->escapeCSV($ref->getReference());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLibelle());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getQuantiteStock());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getType() ? $ref->getType()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($ref->getTypeQuantite());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getStatut() ? $ref->getStatut()->getNom() : '');
        $refData[] = $this->CSVExportService->escapeCSV(strip_tags($ref->getCommentaire()));
        $refData[] = $this->CSVExportService->escapeCSV($ref->getEmplacement() ? $ref->getEmplacement()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($stringFournisseurs);
        $refData[] = $this->CSVExportService->escapeCSV($stringArticlesFournisseur);
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLimitSecurity());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLimitWarning());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getPrixUnitaire());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getBarCode());

        $champsLibres = [];
        foreach ($listTypes as $typeArray) {
        	$type = $this->typeRepository->find($typeArray['id']);
            $listChampsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            foreach ($listChampsLibres as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($ref->getId(), $champLibre);
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

    /**
     * @Route("/get-demande", name="demande", options={"expose"=true})
     */
    public function getDemande(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data= json_decode($request->getContent(), true)) {

            $statutDemande = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $demandes = $this->demandeRepository->findByStatutAndUser($statutDemande, $this->getUser());

            $collectes = $this->collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

            if ($data['typeDemande'] === 'livraison' && $demandes) {
                $json = $demandes;
            } elseif ($data['typeDemande'] === 'collecte' && $collectes) {
                $json = $collectes;
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-etiquettes", name="reference_article_get_data_to_print", options={"expose"=true})
     */
    public function getDataToPrintLabels(Request $request, $params = null) : Response
    {
        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $this->referenceArticleRepository->findByFiltersAndParams($filters, $params, $this->user);
        $refs = $queryResult['data'];
        $data = json_decode($request->getContent(), true);

        /** @var ReferenceArticle[] $refs */
        $refs = array_slice($refs, $data['start'] ,$data['length']);

        $refArticleDataService = $this->refArticleDataService;
        $barcodeInformations = array_reduce(
            $refs,
            function (array $acc, ReferenceArticle $referenceArticle) use ($refArticleDataService) {
                $refBarcodeInformations = $refArticleDataService->getBarcodeInformations($referenceArticle);
                $acc['barcodes'][] = $refBarcodeInformations['barcode'];
                $acc['barcodeLabels'][] = $refBarcodeInformations['barcodeLabel'];
                return $acc;
            },
            ['barcodes' => [], 'barcodeLabels' => []]
        );

        if ($request->isXmlHttpRequest()) {
            $data = [
            	'tags' => $this->globalParamService->getDimensionAndTypeBarcodeArray(),
				'barcodes' => $barcodeInformations['barcodes'],
				'barcodeLabels' => $barcodeInformations['barcodeLabels'],
			];
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

	/**
	 * @Route("/ajax-reference_article-depuis-id", name="get_reference_article_from_id", options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @return Response
	 * @throws NonUniqueResultException
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws NoResultException
	 */
    public function getArticleRefFromId(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            $ref = $this->referenceArticleRepository->find(intval($dataContent['article']));
            $barcodeInformations = $this->refArticleDataService->getBarcodeInformations($ref);
            $data  = [
                'tags' => $this->globalParamService->getDimensionAndTypeBarcodeArray(),
                'barcodes' => [$barcodeInformations['barcode']],
                'barcodeLabels' => [$barcodeInformations['barcodeLabel']],
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/show-actif-inactif", name="reference_article_actif_inactif", options={"expose"=true})
     */
    public function displayActifOrInactif(Request $request) : Response
    {
        if ($request->isXmlHttpRequest() && $data= json_decode($request->getContent(), true)){

            $user = $this->getUser();
            $statutArticle = $data['donnees'];

            $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::CHAMP_FIXE_STATUT);

            $em = $this->getDoctrine()->getManager();
            if($filter == null){
                $filter = new FiltreRef();
                $filter
                    ->setUtilisateur($user)
                    ->setChampFixe('Statut')
                    ->setValue(ReferenceArticle::STATUT_ACTIF);
                $em->persist($filter);
            }

            if ($filter->getValue() != $statutArticle) {
                $filter->setValue($statutArticle);
            }
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/mouvements/lister", name="ref_mouvements_list", options={"expose"=true}, methods="GET|POST")
     */
    public function showMovements(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if ($ref = $this->referenceArticleRepository->find($data)) {
                $name = $ref->getLibelle();
            }

           return new JsonResponse($this->renderView('reference_article/modalShowMouvementsContent.html.twig', [
               'refLabel' => $name?? ''
           ]));
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/mouvements/api/{id}", name="ref_mouvements_api", options={"expose"=true}, methods="GET|POST")
     */
    public function apiMouvements(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) {

            $mouvements = $this->mouvementStockRepository->findByRef($id);

            $rows = [];
            foreach ($mouvements as $mouvement) {
                $rows[] =
                    [
                        'Date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : 'aucune',
                        'Quantity' => $mouvement->getQuantity(),
                        'Origin' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : 'aucun',
                        'Destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : 'aucun',
                        'Type' => $mouvement->getType(),
                        'Operator' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : 'aucun'
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
