<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ParametrageGlobal;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Article;
use App\Helper\FormatHelper;
use DateTime;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\DemandeCollecteService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use App\Service\FreeFieldService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var DemandeCollecteService
     */
    private $collecteService;


    public function __construct(RefArticleDataService $refArticleDataService,
                                UserService $userService,
                                ArticleDataService $articleDataService,
                                DemandeCollecteService $collecteService)
    {
        $this->refArticleDataService = $refArticleDataService;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->collecteService = $collecteService;
    }

    /**
     * @Route("/liste/{filter}", name="collecte_index", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_COLL})
     */
    public function index(EntityManagerInterface $entityManager,
                          $filter = null): Response
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $paramGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);
        $restrictedResults = $paramGlobalRepository->getOneParamByLabel(ParametrageGlobal::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST);
		$typeChampLibre = [];
		foreach ($types as $type) {
			$champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);

			$typeChampLibre[] = [
				'typeLabel' => $type->getLabel(),
				'typeId' => $type->getId(),
				'champsLibres' => $champsLibres,
			];
		}

        return $this->render('collecte/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(Collecte::CATEGORIE),
			'typeChampsLibres' => $typeChampLibre,
			'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]),
			'filterStatus' => $filter,
            'restrictResults' => $restrictedResults,
        ]);
    }

    /**
     * @Route("/voir/{id}", name="collecte_show", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_COLL}, mode=HasPermission::IN_JSON)
     */
    public function show(Collecte $collecte,
                         DemandeCollecteService $collecteService,
                         EntityManagerInterface $entityManager): Response
    {
        $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

        $hasPairings = false;
        foreach ($collecte->getOrdresCollecte() as $collectOrder) {
            $hasPairings = !$collectOrder->getPairings()->isEmpty();
            if ($hasPairings) {
                break;
            }
        }

		return $this->render('collecte/show.html.twig', [
            'refCollecte' => $collecteReferenceRepository->findByCollecte($collecte),
            'collecte' => $collecte,
            'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUT_BROUILLON),
            'detailsConfig' => $collecteService->createHeaderDetailsConfig($collecte),
            'hasPairings' => $hasPairings,
		]);
    }

    /**
     * @Route("/api", name="collecte_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_COLL}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request): Response
	{
        // cas d'un filtre statut depuis page d'accueil
        $filterStatus = $request->request->get('filterStatus');
        $data = $this->collecteService->getDataForDatatable($request->request, $filterStatus);

        return new JsonResponse($data);
	}

    /**
     * @Route("/article/api/{id}", name="collecte_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_COLL}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(EntityManagerInterface $entityManager, $id): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $collecteRepository = $entityManager->getRepository(Collecte::class);
        $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

        $collecte = $collecteRepository->find($id);
        $articles = $articleRepository->findByCollecteId($collecte->getId());
        $referenceCollectes = $collecteReferenceRepository->findByCollecte($collecte);
        $rowsRC = [];
        foreach ($referenceCollectes as $referenceCollecte) {
            $rowsRC[] = [
                'Référence' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getReference() : ''),
                'Libellé' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getLibelle() : ''),
                'Emplacement' => FormatHelper::location($collecte->getPointCollecte()),
                'Quantité' => ($referenceCollecte->getQuantite() ? $referenceCollecte->getQuantite() : ''),
                'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', [
                    'type' => 'reference',
                    'id' => $referenceCollecte->getId(),
                    'name' => ($referenceCollecte->getReferenceArticle() ? $referenceCollecte->getReferenceArticle()->getTypeQuantite() : ReferenceArticle::TYPE_QUANTITE_REFERENCE),
                    'refArticleId' => $referenceCollecte->getReferenceArticle()->getId(),
                    'collecteId' => $collecte->getid(),
                    'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUT_BROUILLON),
                ]),
            ];
        }
        $rowsCA = [];
        foreach ($articles as $article) {
            $rowsCA[] = [
                'Référence' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                'Libellé' => $article->getLabel(),
                'Emplacement' => ($collecte->getPointCollecte() ? $collecte->getPointCollecte()->getLabel() : ''),
                'Quantité' => $article->getQuantite(),
                'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', [
                    'name' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
                    'type' => 'article',
                    'id' => $article->getId(),
                    'collecteId' => $collecte->getid(),
                    'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUT_BROUILLON ? true : false),
                ]),
            ];
        }
        $data['data'] = array_merge($rowsCA, $rowsRC);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="collecte_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $date = new DateTime('now');

            $status = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_BROUILLON);
            $numero = 'C-' . $date->format('YmdHis');
            $collecte = new Collecte();
            $destination = ($data['destination'] == 0) ? false : true;
            $type = $typeRepository->find($data['type']);

            $collecte
                ->setDemandeur($utilisateurRepository->find($data['demandeur']))
                ->setNumero($numero)
                ->setDate($date)
                ->setType($type)
                ->setStatut($status)
                ->setPointCollecte($emplacementRepository->find($data['emplacement']))
                ->setObjet(substr($data['Objet'], 0, 255))
                ->setCommentaire($data['commentaire'])
                ->setstockOrDestruct($destination);

            $entityManager->persist($collecte);

            try {
                $entityManager->flush();
            }
            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une autre demande de collecte est en cours de création, veuillez réessayer.'
                ]);
            }

            $freeFieldService->manageFreeFields($collecte, $data, $entityManager);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('collecte_show', ['id' => $collecte->getId()]),
            ];

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajouter-article", name="collecte_add_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addArticle(Request $request,
                               FreeFieldService $champLibreService,
                               EntityManagerInterface $entityManager,
                               DemandeCollecteService $demandeCollecteService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $collecte = $collecteRepository->find($data['collecte']);
            if (($data['quantity-to-pick'] <= 0) || empty($data['quantity-to-pick'])) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La quantité doit être superieur à zero.'
                ]);
            }
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if ($collecteReferenceRepository->countByCollecteAndRA($collecte, $refArticle) > 0) {
                    $collecteReference = $collecteReferenceRepository->getByCollecteAndRA($collecte, $refArticle);
                    $collecteReference->setQuantite(intval($collecteReference->getQuantite()) + max(intval($data['quantity-to-pick']), 0)); // protection contre quantités négatives
                } else {
                    $collecteReference = new CollecteReference();
                    $collecteReference
                        ->setCollecte($collecte)
                        ->setReferenceArticle($refArticle)
                        ->setQuantite(max($data['quantity-to-pick'], 0)); // protection contre quantités négatives

                    $entityManager->persist($collecteReference);
                }

                if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                    return $this->redirectToRoute('access_denied');
                }
                $this->refArticleDataService->editRefArticle($refArticle, $data, $this->getUser(), $champLibreService);
            } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $demandeCollecteService->persistArticleInDemand($data, $refArticle, $collecte);
            }
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence <strong>' . $refArticle->getLibelle() . '</strong> a bien été ajoutée à la collecte.'
            ]);

        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-quantite-article", name="collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editArticle(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

//TODO dans DL et DC, si on modifie une ligne, la réf article n'est pas modifiée dans l'edit
            $collecteReference = $collecteReferenceRepository->find($data['collecteRef']);
            $collecteReference->setQuantite(intval($data['quantite']));
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-quantite-api-article", name="collecte_edit_api_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApiArticle(Request $request,
                                   EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $json = $this->renderView('collecte/modalEditArticleContent.html.twig', [
                'collecteRef' => $collecteReferenceRepository->find($data['id']),
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/retirer-article", name="collecte_remove_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeArticle(Request $request,
                                  EntityManagerInterface $entityManager)
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $collecteReference = $collecteReferenceRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager->remove($collecteReference);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $articleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
                $collecte = $collecteRepository->find($data['collecte']);
                $collecte->removeArticle($article);
            }
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence a bien été supprimée de la collecte.'
            ]);
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/api-modifier", name="collecte_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $globalSettingsRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $collecte = $collecteRepository->find($data['id']);
			$listTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);

			$typeChampLibre = [];
            $freeFieldsGroupedByTypes = [];

			foreach ($listTypes as $type) {
				$champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);
				$champsLibresArray = [];
				foreach ($champsLibres as $champLibre) {
					$champsLibresArray[] = [
						'id' => $champLibre->getId(),
						'label' => $champLibre->getLabel(),
						'typage' => $champLibre->getTypage(),
						'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
						'defaultValue' => $champLibre->getDefaultValue(),
					];
				}
				$typeChampLibre[] = [
					'typeLabel' => $type->getLabel(),
					'typeId' => $type->getId(),
					'champsLibres' => $champsLibresArray,
				];
                $freeFieldsGroupedByTypes[$type->getId()] = $champsLibres;
			}

            $json = $this->renderView('collecte/modalEditCollecteContent.html.twig', [
                'collecte' => $collecte,
                'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]),
				'typeChampsLibres' => $typeChampLibre,
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
                'restrictedLocations' => $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST),
            ]);

            return new JsonResponse($json);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="collecte_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         DemandeCollecteService $collecteService,
                         FreeFieldService $champLibreService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);

			// vérification des champs Libres obligatoires
			$requiredEdit = true;
			$type = $typeRepository->find(intval($data['type']));
			$CLRequired = $champLibreRepository->getByTypeAndRequiredEdit($type);
			foreach ($CLRequired as $CL) {
				if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
					$requiredEdit = false;
				}
			}

			if ($requiredEdit) {
				$collecte = $collecteRepository->find($data['collecte']);
				$pointCollecte = $emplacementRepository->find($data['Pcollecte']);
				$destination = ($data['destination'] == 0) ? false : true;

				$type = $typeRepository->find($data['type']);
				$collecte
					->setDate(new DateTime($data['date-collecte']))
					->setCommentaire($data['commentaire'])
					->setObjet(substr($data['objet'], 0, 255))
					->setPointCollecte($pointCollecte)
                    ->setFilled(true)
					->setType($type)
					->setstockOrDestruct($destination);
				$entityManager->flush();

                $champLibreService->manageFreeFields($collecte, $data, $entityManager);
                $entityManager->flush();

                $response = [
					'entete' => $this->renderView('collecte/collecte-show-header.html.twig', [
						'collecte' => $collecte,
						'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUT_BROUILLON),
                        'showDetails' => $collecteService->createHeaderDetailsConfig($collecte)
					]),
				];
			} else {
				$response['success'] = false;
				$response['msg'] = "Tous les champs obligatoires n'ont pas été renseignés.";
			}

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="collecte_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $collecteRepository = $entityManager->getRepository(Collecte::class);

            $collecte = $collecteRepository->find($data['collecte']);
            foreach ($collecte->getCollecteReferences() as $cr) {
                $entityManager->remove($cr);
            }
            $entityManager->remove($collecte);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('collecte_index'),
            ];

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/non-vide", name="demande_collecte_has_articles", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
    public function hasArticles(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $articles = $articleRepository->findByCollecteId($data['id']);
            $referenceCollectes = $collecteReferenceRepository->findByCollecte($data['id']);
            $count = count($articles) + count($referenceCollectes);

            return new JsonResponse($count > 0);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete", name="get_demand_collect", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_COLL}, mode=HasPermission::IN_JSON)
     */
	public function getDemandCollectAutoComplete(Request $request,
                                                 EntityManagerInterface $entityManager): Response
	{
        $collecteRepository = $entityManager->getRepository(Collecte::class);

        $search = $request->query->get('term');

        $collectes = $collecteRepository->getIdAndLibelleBySearch($search);

        return new JsonResponse(['results' => $collectes]);
	}

    /**
     * @Route("/csv", name="get_demandes_collectes_for_csv",options={"expose"=true}, methods="GET|POST" )
     * @HasPermission({Menu::DEM, Action::EXPORT})
     */
	public function getDemandesCollecteCSV(EntityManagerInterface $entityManager,
                                           DemandeCollecteService $demandeCollecteService,
                                           Request $request,
                                           FreeFieldService $freeFieldService,
                                           CSVExportService $CSVExportService): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_COLLECTE]);

            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $collectes = $collecteRepository->findByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = array_merge(
                [
                    'Numero demande',
                    'Date de création',
                    'Date de validation',
                    'Type',
                    'Statut',
                    'Sujet',
                    'Stock ou destruction',
                    'Demandeur',
                    'Point de collecte',
                    'Commentaire',
                    'Code barre',
                    'Quantité',
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );
            $today = new DateTime('now');
            $fileName = "export_demande_collecte" . $today->format('d_m_Y') . ".csv";
            return $CSVExportService->createBinaryResponseFromData(
                $fileName,
                $collectes,
                $csvHeader,
                function (Collecte $collecte) use ($freeFieldsConfig, $freeFieldService, $demandeCollecteService) {
                    $rows = [];
                    foreach ($collecte->getArticles() as $article) {
                        $rows[] = $demandeCollecteService->serialiseExportRow($collecte, $freeFieldsConfig, $freeFieldService, function () use ($article) {
                            return [
                                $article->getBarCode(),
                                $article->getQuantite()
                            ];
                        });
                    }

                    foreach ($collecte->getCollecteReferences() as $collecteReference) {
                        $rows[] = $demandeCollecteService->serialiseExportRow($collecte, $freeFieldsConfig, $freeFieldService, function () use ($collecteReference) {
                            return [
                                $collecteReference->getReferenceArticle() ? $collecteReference->getReferenceArticle()->getBarCode() : '',
                                $collecteReference->getQuantite()
                            ];
                        });
                    }

                    return $rows;
                }
            );
        }

        throw new BadRequestHttpException();
    }
}
