<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Article;

use App\Service\ArticleDataService;
use App\Service\DemandeCollecteService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use App\Service\FreeFieldService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


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
     * @param EntityManagerInterface $entityManager
     * @param string|null $filter
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager,
                          $filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $types = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE);

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
            'utilisateurs' => $utilisateurRepository->findAll(),
			'typeChampsLibres' => $typeChampLibre,
			'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE),
			'filterStatus' => $filter
        ]);
    }

    /**
     * @Route("/voir/{id}", name="collecte_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param Collecte $collecte
     * @param DemandeCollecteService $collecteService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function show(Collecte $collecte,
                         DemandeCollecteService $collecteService,
                         EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)) {
            return $this->redirectToRoute('access_denied');
        }

        $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

		return $this->render('collecte/show.html.twig', [
            'refCollecte' => $collecteReferenceRepository->findByCollecte($collecte),
            'collecte' => $collecte,
            'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUT_BROUILLON),
            'detailsConfig' => $collecteService->createHeaderDetailsConfig($collecte)
		]);
    }

    /**
     * @Route("/api", name="collecte_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)) {
				return $this->redirectToRoute('access_denied');
			}

			// cas d'un filtre statut depuis page d'accueil
			$filterStatus = $request->request->get('filterStatus');
			$data = $this->collecteService->getDataForDatatable($request->request, $filterStatus);

			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

    /**
     * @Route("/article/api/{id}", name="collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function articleApi(EntityManagerInterface $entityManager,
                               Request $request,
                               $id): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)) {
                return $this->redirectToRoute('access_denied');
            }

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
                    'Emplacement' => $collecte->getPointCollecte()->getLabel(),
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="collecte_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        FreeFieldService $freeFieldService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

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
            $entityManager->flush();
            $freeFieldService->manageFreeFields($collecte, $data, $entityManager);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('collecte_show', ['id' => $collecte->getId()]),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/ajouter-article", name="collecte_add_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param FreeFieldService $champLibreService
     * @param EntityManagerInterface $entityManager
     * @param DemandeCollecteService $demandeCollecteService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function addArticle(Request $request,
                               FreeFieldService $champLibreService,
                               EntityManagerInterface $entityManager,
                               DemandeCollecteService $demandeCollecteService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $collecte = $collecteRepository->find($data['collecte']);

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
            }
            elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $demandeCollecteService->persistArticleInDemand($data, $refArticle, $collecte);
            }
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence <strong>' . $refArticle->getLibelle() . '</strong> a bien été ajoutée à la collecte.'
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-quantite-article", name="collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editArticle(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

//TODO dans DL et DC, si on modifie une ligne, la réf article n'est pas modifiée dans l'edit
            $collecteReference = $collecteReferenceRepository->find($data['collecteRef']);
            $collecteReference->setQuantite(intval($data['quantite']));
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-quantite-api-article", name="collecte_edit_api_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApiArticle(Request $request,
                                   EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $json = $this->renderView('collecte/modalEditArticleContent.html.twig', [
                'collecteRef' => $collecteReferenceRepository->find($data['id']),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/retirer-article", name="collecte_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse|RedirectResponse
     */
    public function removeArticle(Request $request,
                                  EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
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
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/api-modifier", name="collecte_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
				return $this->redirectToRoute('access_denied');
			}
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $collecteRepository = $entityManager->getRepository(Collecte::class);

            $collecte = $collecteRepository->find($data['id']);
			$listTypes = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE);

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
                'emplacements' => $emplacementRepository->findAll(),
                'types' => $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE),
				'typeChampsLibres' => $typeChampLibre,
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes
            ]);

            return new JsonResponse($json);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="collecte_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param DemandeCollecteService $collecteService
     * @param FreeFieldService $champLibreService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function edit(Request $request,
                         DemandeCollecteService $collecteService,
                         FreeFieldService $champLibreService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
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
					->setDate(new \DateTime($data['date-collecte']))
					->setCommentaire($data['commentaire'])
					->setObjet(substr($data['objet'], 0, 255))
					->setPointCollecte($pointCollecte)
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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="collecte_delete", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/non-vide", name="demande_collecte_has_articles", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function hasArticles(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $articles = $articleRepository->findByCollecteId($data['id']);
            $referenceCollectes = $collecteReferenceRepository->findByCollecte($data['id']);
            $count = count($articles) + count($referenceCollectes);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/autocomplete", name="get_demand_collect", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
	public function getDemandCollectAutoComplete(Request $request,
                                                 EntityManagerInterface $entityManager): Response
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)) {
				return $this->redirectToRoute('access_denied');
			}

            $collecteRepository = $entityManager->getRepository(Collecte::class);

			$search = $request->query->get('term');

			$collectes = $collecteRepository->getIdAndLibelleBySearch($search);

			return new JsonResponse(['results' => $collectes]);
		}
		throw new NotFoundHttpException("404");
	}
}
