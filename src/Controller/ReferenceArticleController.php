<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\InventoryCategory;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\CollecteReference;
use App\Entity\CategorieCL;
use App\Entity\Collecte;
use App\Entity\VisibilityGroup;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Helper\FormatHelper;
use App\Service\AttachmentService;
use WiiCommon\Helper\Stream;
use App\Service\DemandeCollecteService;
use App\Service\MouvementStockService;
use App\Service\FreeFieldService;
use App\Service\ArticleFournisseurService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment as Twig_Environment;
use App\Service\CSVExportService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Demande;
use App\Entity\ArticleFournisseur;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;


/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends AbstractController
{

    /** @Required */
    public RefArticleDataService $refArticleDataService;

    /** @Required */
    public ArticleDataService $articleDataService;

    /** @Required */
    public UserService $userService;

    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public SpecificService $specificService;

    /**
     * @Route("/api-columns", name="ref_article_api_columns", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(RefArticleDataService $refArticleDataService,
                               EntityManagerInterface $entityManager): Response {

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $fields = Stream::from($refArticleDataService->getColumnVisibleConfig($entityManager, $currentUser))
            ->filter(function ($column) {
                return !isset($column['hiddenColumn']) || !$column['hiddenColumn'];
            })
            ->values();
        return $this->json(($fields));
    }

    /**
     * @Route("/api", name="ref_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request): Response {
        return $this->json($this->refArticleDataService->getRefArticleDataByParams($request->request));
    }

    /**
     * @Route("/creer", name="reference_article_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        FreeFieldService $champLibreService,
                        EntityManagerInterface $entityManager,
                        MouvementStockService $mouvementStockService,
                        ArticleFournisseurService $articleFournisseurService,
                        AttachmentService $attachmentService): Response
    {
        if ($data = $request->request->all()) {

            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $referenceArticleRepository->countByReference($data['reference']);

            if ($refAlreadyExist) {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
					'invalidFieldsSelector' => 'input[name="reference"]'
				]);
            }

            $type = $typeRepository->find($data['type']);

            if ($data['emplacement'] !== null) {
                $emplacement = $emplacementRepository->find($data['emplacement']);
            } else {
                $emplacement = null; //TODO gérer message erreur (faire un return avec msg erreur adapté -> à ce jour un return false correspond forcément à une réf déjà utilisée)
            }

            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);

            switch($data['type_quantite']) {
                case 'article':
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                    break;
                case 'reference':
                default:
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_REFERENCE;
                    break;
            }

            $refArticle = new ReferenceArticle();
            $refArticle
                ->setNeedsMobileSync($data['mobileSync'] ?? false)
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setCommentaire($data['commentaire'])
                ->setTypeQuantite($typeArticle)
                ->setPrixUnitaire(max(0, $data['prix']))
                ->setType($type)
                ->setIsUrgent($data['urgence'] == "true")
                ->setEmplacement($emplacement)
				->setBarCode($this->refArticleDataService->generateBarCode())
                ->setBuyer(isset($data['buyer']) ? $userRepository->find($data['buyer']) : null);


            if ($refArticle->getIsUrgent()) {
                $refArticle->setUserThatTriggeredEmergency($loggedUser);
            }

            if ($data['limitSecurity']) {
            	$refArticle->setLimitSecurity($data['limitSecurity']);
			}
            if ($data['limitWarning']) {
            	$refArticle->setLimitWarning($data['limitWarning']);
			}
            if ($data['emergency-comment-input']) {
                $refArticle->setEmergencyComment($data['emergency-comment-input']);
            }
            if ($data['categorie']) {
            	$category = $inventoryCategoryRepository->find($data['categorie']);
            	if ($category) $refArticle->setCategory($category);
			}
            if ($statut) $refArticle->setStatut($statut);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $refArticle->setQuantiteStock($data['quantite'] ? max($data['quantite'], 0) : 0); // protection contre quantités négatives
            } else {
                $refArticle->setQuantiteStock(0);
            }
            $refArticle->setQuantiteReservee(0);
            $refArticle->setStockManagement($data['stockManagement'] ?? null);

            $managersIds = Stream::explode(",", $data["managers"])
                ->filter()
                ->toArray();
            foreach ($managersIds as $managerId) {
                $manager = $userRepository->find($managerId);
                if ($manager) {
                    $refArticle->addManager($manager);
                }
            }

            $visibilityGroupsIds = Stream::explode(",", $data["visibility-group"])
                ->filter()
                ->toArray();
            foreach ($visibilityGroupsIds as $visibilityGroupsId) {
                $visibilityGroup = $visibilityGroupRepository->find($visibilityGroupsId);
                if ($visibilityGroup) {
                    $refArticle->addVisibilityGroup($visibilityGroup);
                }
            }

            $supplierReferenceLines = json_decode($data['frl'], true);
            if (!empty($supplierReferenceLines)) {
                foreach ($supplierReferenceLines as $supplierReferenceLine) {
                    $referenceArticleFournisseur = $supplierReferenceLine['referenceFournisseur'];
                    try {
                        $supplierArticle = $articleFournisseurService->createArticleFournisseur([
                            'fournisseur' => $supplierReferenceLine['fournisseur'],
                            'article-reference' => $refArticle,
                            'label' => $supplierReferenceLine['labelFournisseur'],
                            'reference' => $referenceArticleFournisseur
                        ]);

                        $entityManager->persist($supplierArticle);
                    } catch (Exception $exception) {
                        if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                            return new JsonResponse([
                                'success' => false,
                                'msg' => "La référence '$referenceArticleFournisseur' existe déjà pour un article fournisseur."
                            ]);
                        }
                    }
                }
            }

            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE &&
                $refArticle->getQuantiteStock() > 0) {
                $mvtStock = $mouvementStockService->createMouvementStock(
                    $loggedUser,
                    null,
                    $refArticle->getQuantiteStock(),
                    $refArticle,
                    MouvementStock::TYPE_ENTREE
                );
                $mouvementStockService->finishMouvementStock(
                    $mvtStock,
                    new DateTime('now'),
                    $emplacement
                );
                $entityManager->persist($mvtStock);
            }

            $entityManager->persist($refArticle);
            $entityManager->flush();

            $champLibreService->manageFreeFields($refArticle, $data, $entityManager);
            $attachmentService->manageAttachments($entityManager, $refArticle, $request->files);

            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => 'La référence ' . $refArticle->getReference() . ' a bien été créée',
                'data' => [ // for reference created in reception-show
                    'id' => $refArticle->getId(),
                    'reference' => $refArticle->getReference()
                ]
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/", name="reference_article_index",  methods="GET|POST", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE})
     */
    public function index(RefArticleDataService $refArticleDataService,
                          EntityManagerInterface $entityManager): Response {

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();


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

        $fields = $refArticleDataService->getColumnVisibleConfig($entityManager, $user);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $inventoryCategories = $inventoryCategoryRepository->findAll();
        $typeChampLibre =  [];
        $freeFieldsGroupedByTypes = [];

        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' =>  $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
            $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
        }

        $filter = $filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::FIXED_FIELD_STATUT);

        return $this->render('reference_article/index.html.twig', [
            'filters' => $filtreRefRepository->findByUserExceptChampFixe($this->getUser(), FiltreRef::FIXED_FIELD_STATUT),
            "fields" => $fields,
            "searches" => $user->getRecherche(),
            'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
            'columnsVisibles' => $this->getUser()->getColumnVisible(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'typeQuantite' => $typeQuantite,
            'categories' => $inventoryCategories,
            'wantInactif' => !empty($filter) && $filter->getValue() === Article::STATUT_INACTIF,
            'stockManagement' => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
        ]);
    }

    /**
     * @Route("/api-modifier", name="reference_article_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $refArticle = $referenceArticleRepository->find((int)$data['id']);

            if ($refArticle) {
                $json = $this->refArticleDataService->getViewEditRefArticle($refArticle, $data['isADemand'], true, true);
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="reference_article_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $entityManager, FreeFieldService $champLibreService): Response
    {
        if ($data = $request->request->all()) {
            $refId = intval($data['idRefArticle']);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $referenceArticleRepository->find($refId);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $referenceArticleRepository->countByReference($data['reference'], $refId);

            if ($refAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
                    'invalidFieldsSelector' => 'input[name="reference"]'
                ]);
            }
            if ($refArticle) {
                try {
                    /** @var Utilisateur $currentUser */
                    $currentUser = $this->getUser();
                    $refArticle->removeIfNotIn($data['files'] ?? []);
                    $response = $this->refArticleDataService->editRefArticle($refArticle, $data, $currentUser, $champLibreService, $request);
                }
                catch (ArticleNotAvailableException $exception) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas modifier la quantité d'une référence inactive."
                    ];
                }
                catch (RequestNeedToBeProcessedException $exception) {
                    $response = [
                        'success' => false,
                        'msg' => "Vous ne pouvez pas modifier la quantité d'une référence qui est dans un ordre de livraison en cours."
                    ];
                }
            } else {
                $response = ['success' => false, 'msg' => "Une erreur s'est produite lors de la modification de la référence."];
            }
            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="reference_article_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            /** @var ReferenceArticle $refArticle */
            $refArticle = $referenceArticleRepository->find($data['refArticle']);
            if (!($refArticle->getCollecteReferences()->isEmpty())
                || !($refArticle->getLigneArticles()->isEmpty())
                || !($refArticle->getReceptionReferenceArticles()->isEmpty())
                || !($refArticle->getMouvements()->isEmpty())
                || !($refArticle->getArticlesFournisseur()->isEmpty())
                || !($refArticle->getTransferRequests()->isEmpty())
                || !($refArticle->getTransferRequests()->isEmpty())
                || $refArticle->getTrackingPack()
                || $refArticle->hasTrackingMovements()) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => '
                        Cet article est lié à un colis, des mouvements, une collecte, une livraison, une réception ou un article fournisseur.<br>
                        Vous ne pouvez donc pas le supprimer.
                    '
                ]);
            }
            $entityManager->remove($refArticle);
            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/addFournisseur", name="ajax_render_add_fournisseur", options={"expose"=true}, methods="GET", requirements={"currentIndex": "\d+"})
     * @HasPermission({Menu::STOCK, Action::EDIT})
     */
    public function addFournisseur(Request $request): Response
    {
        $currentIndex = $request->query->get('currentIndex');
        $currentIndexInt = $request->query->getInt('currentIndex');

        $json = $this->renderView('reference_article/fournisseurArticle.html.twig', [
            'multipleObjectIndex' => !empty($currentIndex) || $currentIndexInt === 0 ? ($currentIndexInt + 1) : 0
        ]);
        return new JsonResponse($json);
    }

    /**
     * @Route("/removeFournisseur", name="ajax_render_remove_fournisseur", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON))
     */
    public function removeFournisseur(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $entityManager->remove($articleFournisseurRepository->find($data['articleF']));
            $entityManager->flush();
            $json =  $this->renderView('reference_article/fournisseurArticleContent.html.twig', [
                'articles' => $articleFournisseurRepository->findByRefArticle($data['articleRef']),
                'articleRef' => $referenceArticleRepository->find($data['articleRef'])
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/quantite", name="get_quantity_ref_article", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON))
     */
    public function getQuantityByRefArticleId(Request $request, EntityManagerInterface $entityManager)
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $quantity = false;

        $refArticleId = $request->request->get('refArticleId');
        $refArticle = $referenceArticleRepository->find($refArticleId);

        if ($refArticle) {
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $quantity = $refArticle->getQuantiteDisponible();
            }
        }

        return new JsonResponse($quantity);
    }

    /**
     * @Route("/autocomplete-ref", name="get_ref_articles", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getRefArticles(Request $request,
                                   EntityManagerInterface $entityManager)
    {
        $search = $request->query->get('term');
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $activeOnly = $request->query->getBoolean('activeOnly', false);
        $minQuantity = $request->query->get('minQuantity');
        $typeQuantity = $request->query->get('typeQuantity');
        $field = $request->query->get('field', 'reference');
        $locationFilter = $request->query->get('locationFilter');
        $buyerFilter = $request->query->get('buyerFilter');

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $refArticles = $referenceArticleRepository->getIdAndRefBySearch(
            $search,
            $user,
            $activeOnly,
            $minQuantity !== null ? (int) $minQuantity : null,
            $typeQuantity,
            $field,
            $locationFilter,
            $buyerFilter
        );
        return new JsonResponse(['results' => $refArticles]);
    }

    /**
     * @Route("/{reference}/data", name="get_reference_data", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function getReferenceData(ReferenceArticle $reference)
    {
        return $this->json([
            'label' => $reference->getLibelle(),
            'buyer' => FormatHelper::user($reference->getBuyer()),
            'stockQuantity' => $reference->getQuantiteStock()
        ]);
    }

    /**
     * @Route("/plus-demande", name="plus_demande", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function plusDemande(EntityManagerInterface $entityManager,
                                Request $request,
                                FreeFieldService $champLibreService,
                                DemandeCollecteService $demandeCollecteService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $transfertRepository = $entityManager->getRepository(TransferRequest::class);
            $articleRepository = $entityManager->getRepository(Article::class);

            $success = true;

            $refArticle = (isset($data['refArticle']) ? $referenceArticleRepository->find($data['refArticle']) : '');
            $article = (isset($data['article']) ? $articleRepository->find($data['article']) : '');
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $statusName = $refArticle->getStatut() ? $refArticle->getStatut()->getNom() : '';

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            if ($statusName == ReferenceArticle::STATUT_ACTIF) {
                if (array_key_exists('transfert', $data) && $data['transfert']) {
                    $transfert = $transfertRepository->find($data['transfert']);

                    if ($article) {
                        $transfert
                            ->addArticle($article);
                    } else {
                        $transfert
                            ->addReference($refArticle);
                    }
                } else if (array_key_exists('livraison', $data) && $data['livraison']) {
				    $demande = $demandeRepository->find($data['livraison']);
                    $success = $this->refArticleDataService->addRefToDemand(
                        $data,
                        $refArticle,
                        $currentUser,
                        false,
                        $entityManager,
                        $demande,
                        $champLibreService
                    );
                    if ($success === 'article') {
                        try {
                            $this->articleDataService->editArticle($data);
                            $success = true;
                        }
                        catch(ArticleNotAvailableException $exception) {
                            $success = [
                                'success' => false,
                                'msg' => "Vous ne pouvez pas modifier un article qui n'est pas disponible."
                            ];
                        }
                        catch(RequestNeedToBeProcessedException $exception) {
                            $success = [
                                'success' => false,
                                'msg' => "Vous ne pouvez pas modifier un article qui est dans une demande de livraison."
                            ];
                        }
					}

				} else if (array_key_exists('collecte', $data) && $data['collecte']) {
					$collecte = $collecteRepository->find($data['collecte']);
					if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
						//TODO patch temporaire CEA
                        if (!isset($article)) {
                            $data['quantity-to-pick'] = $data['quantite'];
                            $demandeCollecteService->persistArticleInDemand($data, $refArticle, $collecte);
                        } else {
                            $collecte->addArticle($article);
                        }
					}
					elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
						$collecteReference = new CollecteReference();
						$collecteReference
							->setCollecte($collecte)
							->setReferenceArticle($refArticle)
							->setQuantite(max((int)$data['quantity-to-pick'], 0)); // protection contre quantités négatives
                        $entityManager->persist($collecteReference);
					} else {
						$success = false; //TOOD gérer message erreur
					}
				} else {
					$success = false; //TOOD gérer message erreur
				}
                $entityManager->flush();
			} else {
            	$success = false;
			}

            return new JsonResponse([
                'success' => $success
            ]);

        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax-plus-demande-content", name="ajax_plus_demande_content", options={"expose"=true}, methods="GET|POST")
     */
    public function ajaxPlusDemandeContent(EntityManagerInterface $entityManager,
                                           Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $transfersRepository = $entityManager->getRepository(TransferRequest::class);

            $refArticle = $referenceArticleRepository->find($data['id']);
            if ($refArticle) {
                $collectes = $collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

                $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
                $demandes = $demandeRepository->findByStatutAndUser($statutD, $this->getUser());
                $transfers = $transfersRepository->findByStatutLabelAndUser(TransferRequest::DRAFT, $this->getUser());

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
                $articleOrNo  = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, $data['demande'], $byRef);

                $json = [
                    "success" => true,
                    'plusContent' => $this->renderView('reference_article/modalPlusDemandeContent.html.twig', [
                        'articleOrNo' => $articleOrNo,
                        'collectes' => $collectes,
                        'demandes' => $demandes,
                        'transfers' => $transfers,
                        'demandeType' => $data['demande']
                    ]),
                    'editChampLibre' => $editChampLibre,
					'byRef' => $byRef && ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE)
				];
            } else {
                $json = [
                    "success" => false,
                    "msg" => "Cette référence article n'est plus disponible."
                ];
            }

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request): Response
    {
            $data = json_decode($request->getContent(), true);
            $champs = array_keys($data);
            $user  = $this->getUser();
            /** @var $user Utilisateur */
            $user->setColumnVisible($champs);
            $em  = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/voir", name="reference_article_show", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON))
     */
    public function show(Request $request,
                         RefArticleDataService $refArticleDataService,
                         EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $refArticle  = $referenceArticleRepository->find($data);
            $json = $refArticle
                ? $refArticleDataService->getViewEditRefArticle($refArticle, false, false, true)
                : false;
            return new JsonResponse($json);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="reference_article_show_page", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE})
     */
    public function showPage(ReferenceArticle $referenceArticle): Response {
        return $this->render('reference_article/show/show.html.twig', [
            'referenceArticle' => $referenceArticle
        ]);
    }

    /**
     * @Route("/exporter-refs", name="export_all_refs", options={"expose"=true}, methods="GET|POST")
     */
    public function exportAllRefs(EntityManagerInterface $manager,
                                  CSVExportService $csvService,
                                  FreeFieldService $ffService): Response {
        $ffConfig = $ffService->createExportArrayConfig($manager, [CategorieCL::REFERENCE_ARTICLE]);

        $header = array_merge([
            'reference',
            'libellé',
            'quantité',
            'type',
            'acheteur',
            'type quantité',
            'statut',
            'commentaire',
            'emplacement',
            'seuil sécurite',
            'seuil alerte',
            'prix unitaire',
            'code barre',
            'catégorie inventaire',
            'date dernier inventaire',
            'synchronisation nomade',
            'gestion de stock',
            'gestionnaire(s)',
            'Labels Fournisseurs',
            'Codes Fournisseurs',
            'Groupe(s) de visibilité'
        ], $ffConfig['freeFieldsHeader']);

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $user = $this->userService->getUser();

        return $csvService->streamResponse(function($output) use ($manager, $csvService, $ffService, $ffConfig, $user) {
            $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);
            $managersByReference = $manager
                ->getRepository(Utilisateur::class)
                ->getUsernameManagersGroupByReference();

            $suppliersByReference = $manager
                ->getRepository(Fournisseur::class)
                ->getCodesAndLabelsGroupedByReference();

            $references = $referenceArticleRepository->iterateAll($user);
            foreach($references as $reference) {
                $this->putReferenceLine($output, $csvService, $ffService, $ffConfig, $managersByReference, $reference, $suppliersByReference);
            }
        }, "export-references-$today.csv", $header);
    }

    private function putReferenceLine($handle,
                                      CSVExportService $csvService,
                                      FreeFieldService $ffService,
                                      array $ffConfig,
                                      array $managersByReference,
                                      array $reference,
                                      array $suppliersByReference) {
        $id = (int)$reference['id'];
        $line = [
            $reference['reference'],
            $reference['libelle'],
            $reference['quantiteStock'],
            $reference['type'],
            $reference['buyer'],
            $reference['typeQuantite'],
            $reference['statut'],
            $reference['commentaire'] ? strip_tags($reference['commentaire']) : "",
            $reference['emplacement'],
            $reference['limitSecurity'],
            $reference['limitWarning'],
            $reference['prixUnitaire'],
            $reference['barCode'],
            $reference['category'],
            $reference['dateLastInventory'] ? $reference['dateLastInventory']->format("d/m/Y H:i:s") : "",
            $reference['needsMobileSync'],
            $reference['stockManagement'],
            $managersByReference[$id] ?? "",
            $suppliersByReference[$id]['supplierLabels'] ?? "",
            $suppliersByReference[$id]['supplierCodes'] ?? "",
            $reference['visibilityGroups'],
        ];

        foreach($ffConfig['freeFieldIds'] as $freeFieldId) {
            $line[] = $ffService->serializeValue([
                'typage' => $ffConfig['freeFieldsIdToTyping'][$freeFieldId],
                'valeur' => $reference['freeFields'][$freeFieldId] ?? ''
            ]);
        }

        $csvService->putLine($handle, $line);
    }

    /**
     * @Route("/export-donnees", name="exports_params")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_EXPO})
     */
    public function renderParams(UserService $userService)
    {
        return $this->render('exports/exportsMenu.html.twig');
    }

    /**
     * @Route("/type-quantite", name="get_quantity_type", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getQuantityType(Request $request, EntityManagerInterface $entityManager)
	{
		if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $referenceArticleRepository->find($data['id']);

			$quantityType = $reference ? $reference->getTypeQuantite() : '';

			return new JsonResponse($quantityType);
		}
		throw new BadRequestHttpException();
	}

    /**
     * @Route("/get-demande", name="demande", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getDemande(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($data= json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $transferRepository = $entityManager->getRepository(TransferRequest::class);

            $statutDemande = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $demandes = $demandeRepository->findByStatutAndUser($statutDemande, $this->getUser());
            $collectes = $collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());
            $transfers = $transferRepository->findByStatutLabelAndUser(TransferRequest::DRAFT, $this->getUser());

            return $this->json([
                "success" =>
                    ($data['typeDemande'] === 'livraison' && $demandes) ||
                    ($data['typeDemande'] === 'collecte' && $collectes) ||
                    ($data['typeDemande'] === 'transfert' && $transfers),
                "msg" => "Vous n'avez créé aucune demande de {$data['typeDemande']}"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/etiquettes", name="reference_article_bar_codes_print", options={"expose"=true})
     */
    public function getBarCodes(Request $request,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                PDFGeneratorService $PDFGeneratorService): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $userId = $user->getId();
        $filters = $filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $referenceArticleRepository->findByFiltersAndParams($filters, $request->query, $user);
        $refs = $queryResult['data'];
        $refs = array_map(function($refArticle) {
            return is_array($refArticle) ? $refArticle[0] : $refArticle;
        }, $refs);
        $barcodeConfigs = array_map(
            function (ReferenceArticle $reference) use ($refArticleDataService) {
                return $refArticleDataService->getBarcodeConfig($reference);
            },
            $refs
        );

        $barcodeCounter = count($barcodeConfigs);

        if ($barcodeCounter > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barcodeConfigs,
                'reference' . ($barcodeCounter > 1 ? 's' : '')
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
                $fileName
            );
        }
        else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/{reference}/etiquette", name="reference_article_single_bar_code_print", options={"expose"=true})
     */
    public function getSingleBarCodes(ReferenceArticle $reference,
                                      RefArticleDataService $refArticleDataService,
                                      PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($reference)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'reference');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route("/show-actif-inactif", name="reference_article_actif_inactif", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function displayActifOrInactif(Request $request,
                                          EntityManagerInterface $entityManager) : Response
    {
        if ($data = json_decode($request->getContent(), true)){

            /** @var Utilisateur $user */
            $user = $this->getUser();
            $statutArticle = $data['donnees'];

            $filtreRefRepository = $entityManager->getRepository(FiltreRef::class);

            $filter = $filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::FIXED_FIELD_STATUT);

            $em = $this->getDoctrine()->getManager();
            if($filter == null) {
                $filter = new FiltreRef();
                $filter
                    ->setUtilisateur($user)
                    ->setChampFixe(FiltreRef::FIXED_FIELD_STATUT)
                    ->setValue(ReferenceArticle::STATUT_ACTIF);
                $em->persist($filter);
            }

            if ($filter->getValue() != $statutArticle) {
                $filter->setValue($statutArticle);
            }
            $em->flush();

            return new JsonResponse();
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/mouvements/lister", name="ref_mouvements_list", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function showMovements(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {

            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            if ($ref = $referenceArticleRepository->find($data)) {
                $name = $ref->getLibelle();
            }

           return new JsonResponse($this->renderView('reference_article/modalShowMouvementsContent.html.twig', [
               'refLabel' => $name?? ''
           ]));
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/mouvements/api/{referenceArticle}", name="ref_mouvements_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiMouvements(EntityManagerInterface $entityManager,
                                  MouvementStockService $mouvementStockService,
                                  ReferenceArticle $referenceArticle): Response
    {
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementStockRepository->findByRef($referenceArticle);

        $data['data'] = array_map(
            function(MouvementStock $mouvement) use ($entityManager, $mouvementStockService) {
                $fromColumnConfig = $mouvementStockService->getFromColumnConfig($entityManager, $mouvement);
                $from = $fromColumnConfig['from'];
                $orderPath = $fromColumnConfig['orderPath'];
                $orderId = $fromColumnConfig['orderId'];

                return [
                    'Date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : 'aucune',
                    'Quantity' => $mouvement->getQuantity(),
                    'Origin' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : 'aucun',
                    'Destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : 'aucun',
                    'Type' => $mouvement->getType(),
                    'Operator' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : 'aucun',
                    'from' => $this->templating->render('mouvement_stock/datatableMvtStockRowFrom.html.twig', [
                        'from' => $from,
                        'mvt' => $mouvement,
                        'orderPath' => $orderPath,
                        'orderId' => $orderId
                    ]),
                    'ArticleCode' => $mouvement->getArticle() ? $mouvement->getArticle()->getBarCode() : $mouvement->getRefArticle()->getBarCode()
                ];
            },
            $mouvements
        );
        return new JsonResponse($data);
    }

    /**
     * @Route("/{referenceArticle}/quantity", name="update_qte_refarticle", options={"expose"=true}, methods="PATCH", condition="request.isXmlHttpRequest()")
     */
    public function updateQuantity(EntityManagerInterface $entityManager,
                                   ReferenceArticle $referenceArticle,
                                   RefArticleDataService $refArticleDataService) {

        $refArticleDataService->updateRefArticleQuantities($entityManager, $referenceArticle, true);
        $entityManager->flush();
        $refArticleDataService->treatAlert($entityManager, $referenceArticle);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/nouveau-page", name="reference_article_new", options={"expose"=true})
     */
    public function newTemplate(EntityManagerInterface $manager) {
        $typeRepository = $manager->getRepository(Type::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);

        return $this->render("reference_article/form/new.html.twig", [
            "types" => $types,
        ]);
    }
}
