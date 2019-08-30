<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\ValeurChampLibre;
use App\Entity\Fournisseur;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;

use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\CollecteRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\StatutRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\CollecteReferenceRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\TypeRepository;

use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;


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
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var OrdreCollecteRepository
     */
    private $ordreCollecteRepository;

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


    /**
     * @var ArticleDataService
     */
    private $articleDataService;

	/**
	 * @var ChampLibreRepository
	 */
    private $champLibreRepository;

	/**
	 * @var ValeurChampLibreRepository
	 */
    private $valeurChampLibreRepository;


    public function __construct(ValeurChampLibreRepository $valeurChampLibreRepository, ChampLibreRepository $champLibreRepository, TypeRepository $typeRepository, FournisseurRepository $fournisseurRepository, ArticleFournisseurRepository $articleFournisseurRepository, OrdreCollecteRepository $ordreCollecteRepository, RefArticleDataService $refArticleDataService, CollecteReferenceRepository $collecteReferenceRepository, ReferenceArticleRepository $referenceArticleRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, ArticleDataService $articleDataService)
    {
        $this->typeRepository = $typeRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->collecteRepository = $collecteRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->collecteReferenceRepository = $collecteReferenceRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->champLibreRepository = $champLibreRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

	/**
	 * @Route("/liste/{filter}", name="collecte_index", options={"expose"=true}, methods={"GET", "POST"})
	 * @param string|null $filter
	 * @return Response
	 */
    public function index($filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

		$types = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE);

		$typeChampLibre = [];
		foreach ($types as $type) {
			$champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);

			$typeChampLibre[] = [
				'typeLabel' => $type->getLabel(),
				'typeId' => $type->getId(),
				'champsLibres' => $champsLibres,
			];
		}

		switch ($filter) {
			case 'a-traiter':
				$filter = Collecte::STATUS_A_TRAITER;
				break;
		}

        return $this->render('collecte/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(Collecte::CATEGORIE),
            'utilisateurs' => $this->utilisateurRepository->findAll(),
			'typeChampsLibres' => $typeChampLibre,
			'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE),
			'filterStatus' => $filter
        ]);
    }

    /**
     * @Route("/voir/{id}", name="collecte_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(Collecte $collecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $ordreCollecte = $this->ordreCollecteRepository->findOneByDemandeCollecte($collecte);
		$valeursChampLibre = $this->valeurChampLibreRepository->getByDemandeCollecte($collecte);

		return $this->render('collecte/show.html.twig', [
            'refCollecte' => $this->collecteReferenceRepository->findByCollecte($collecte),
            'collecte' => $collecte,
            'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON),
            'ordreCollecte' => $ordreCollecte,
			'champsLibres' => $valeursChampLibre
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

                if ($this->ordreCollecteRepository->findOneByDemandeCollecte($collecte) == null) {
                    $ordreCollecteDate = null;
                } else {
                    $ordreCollecteDate = $this->ordreCollecteRepository->findOneByDemandeCollecte($collecte)->getDate()->format('d/m/Y H:i');
                }

                $url = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
                $rows[] = [
                    'id' => ($collecte->getId() ? $collecte->getId() : 'Non défini'),
                    'Création' => ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                    'Validation' => $ordreCollecteDate,
                    'Demandeur' => ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null),
                    'Objet' => ($collecte->getObjet() ? $collecte->getObjet() : null),
                    'Statut' => ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                    'Type' => ($collecte->getType() ? $collecte->getType()->getLabel() : ''),
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
            $articles = $this->articleRepository->findByCollecteId($collecte->getId());
            $referenceCollectes = $this->collecteReferenceRepository->findByCollecte($collecte);
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
                        'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON),
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getEntityManager();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $status = $this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_BROUILLON);
            $numero = 'C-' . $date->format('YmdHis');
            $collecte = new Collecte();
            $destination = ($data['destination'] == 0) ? false : true;
            $type = $this->typeRepository->find($data['type']);
            $collecte
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setNumero($numero)
                ->setDate($date)
                ->setType($type)
                ->setStatut($status)
                ->setPointCollecte($this->emplacementRepository->find($data['emplacement']))
                ->setObjet(substr($data['Objet'], 0, 255))
                ->setCommentaire($data['commentaire'])
                ->setstockOrDestruct($destination);
            $em->persist($collecte);
			$em->flush();

			// enregistrement des champs libres
			$champsLibresKey = array_keys($data);

			foreach ($champsLibresKey as $champs) {
				if (gettype($champs) === 'integer') {
					$valeurChampLibre = new ValeurChampLibre();
					$valeurChampLibre
						->setValeur($data[$champs])
						->addDemandesCollecte($collecte)
						->setChampLibre($this->champLibreRepository->find($champs));
					$em->persist($valeurChampLibre);
					$em->flush();
				}
			}

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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getManager();
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $collecte = $this->collecteRepository->find($data['collecte']);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if ($this->collecteReferenceRepository->countByCollecteAndRA($collecte, $refArticle) > 0) {
                    $collecteReference = $this->collecteReferenceRepository->getByCollecteAndRA($collecte, $refArticle);
                    $collecteReference->setQuantite(intval($collecteReference->getQuantite()) + max(intval($data['quantitie']), 0)); // protection contre quantités négatives
                } else {
                    $collecteReference = new CollecteReference();
                    $collecteReference
                        ->setCollecte($collecte)
                        ->setReferenceArticle($refArticle)
                        ->setQuantite(max($data['quantitie'], 0)); // protection contre quantités négatives

                    $em->persist($collecteReference);
                }
                $this->refArticleDataService->editRefArticle($refArticle, $data);
            } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                //TODO patch temporaire CEA
                $fournisseurTemp = $this->fournisseurRepository->findOneByCodeReference('A_DETERMINER');
                if (!$fournisseurTemp) {
                    $fournisseurTemp = new Fournisseur();
                    $fournisseurTemp
                        ->setCodeReference('A_DETERMINER')
                        ->setNom('A DETERMINER');
                    $em->persist($fournisseurTemp);
                }
                $article = new Article();
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
                $article
                    ->setLabel($refArticle->getLibelle() . '-' . $index)
                    ->setConform(true)
                    ->setStatut($statut)
                    ->setReference($ref . '-' . $index)
                    ->setQuantite(max($data['quantitie'], 0)) // protection contre quantités négatives
                    ->setEmplacement($collecte->getPointCollecte())
                    ->setArticleFournisseur($articleFournisseur)
                    ->setType($refArticle->getType());
                $em->persist($article);
                $collecte->addArticle($article);

				$champslibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($refArticle->getType(), Article::CATEGORIE);
                foreach($champslibres as $champLibre) {
                	$valeurChampLibre = new ValeurChampLibre();
                	$valeurChampLibre
						->addArticle($article)
						->setChampLibre($champLibre);
                	$em->persist($valeurChampLibre);
				}
                //TODO fin patch temporaire CEA (à remplacer par lignes suivantes)
            // $article = $this->articleRepository->find($data['article']);
            // $collecte->addArticle($article);

            // $this->articleDataService->editArticle($data);
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
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
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $json = $this->renderView('collecte/modalEditArticleContent.html.twig', [
                'collecteRef' => $this->collecteReferenceRepository->find($data['id']),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/nouveau-api-article", name="collecte_article_new_content", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function newArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $json['content'] = $this->renderView('collecte/newRefArticleByQuantiteRefContentTemp.html.twig', [
                'references' => $this->articleFournisseurRepository->getByFournisseur($this->fournisseurRepository->find($data['fournisseur']))
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $entityManager = $this->getDoctrine()->getManager();

            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $collecteReference = $this->collecteReferenceRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager->remove($collecteReference);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $this->articleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
                $collecte = $this->collecteRepository->find($data['collecte']);
                $collecte->removeArticle($article);
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
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
				return $this->redirectToRoute('access_denied');
			}

            $collecte = $this->collecteRepository->find($data['id']);

			$listTypes = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE);
			$typeChampLibre = [];

			foreach ($listTypes as $type) {
				$champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);
				$champsLibresArray = [];
				foreach ($champsLibres as $champLibre) {
					$valeurChampDC = $this->valeurChampLibreRepository->getValueByDemandeCollecteAndChampLibre($collecte, $champLibre);
					$champsLibresArray[] = [
						'id' => $champLibre->getId(),
						'label' => $champLibre->getLabel(),
						'typage' => $champLibre->getTypage(),
						'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
						'defaultValue' => $champLibre->getDefaultValue(),
						'valeurChampLibre' => $valeurChampDC,
					];
				}
				$typeChampLibre[] = [
					'typeLabel' => $type->getLabel(),
					'typeId' => $type->getId(),
					'champsLibres' => $champsLibresArray,
				];
			}

            $json = $this->renderView('collecte/modalEditCollecteContent.html.twig', [
                'collecte' => $collecte,
                'emplacements' => $this->emplacementRepository->findAll(),
                'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE),
				'typeChampsLibres' => $typeChampLibre
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

			// vérification des champs Libres obligatoires
			$requiredEdit = true;
			$type = $this->typeRepository->find(intval($data['type']));
			$CLRequired = $this->champLibreRepository->getByTypeAndRequiredEdit($type);
			foreach ($CLRequired as $CL) {
				if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
					$requiredEdit = false;
				}
			}

			if ($requiredEdit) {
				$collecte = $this->collecteRepository->find($data['collecte']);
				$pointCollecte = $this->emplacementRepository->find($data['Pcollecte']);
				$destination = ($data['destination'] == 0) ? false : true;

				$ordreCollecte = $this->ordreCollecteRepository->findOneByDemandeCollecte($collecte);
				$type = $this->typeRepository->find($data['type']);
				$collecte
					->setDate(new \DateTime($data['date-collecte']))
					->setCommentaire($data['commentaire'])
					->setObjet(substr($data['objet'], 0, 255))
					->setPointCollecte($pointCollecte)
					->setType($type)
					->setstockOrDestruct($destination);
				$em = $this->getDoctrine()->getManager();
				$em->flush();

				// modification ou création des champs libres
				$champsLibresKey = array_keys($data);

				foreach ($champsLibresKey as $champ) {
					if (gettype($champ) === 'integer') {
						$valeurChampLibre = $this->valeurChampLibreRepository->findOneByDemandeCollecteAndChampLibre($collecte, $champ);

						// si la valeur n'existe pas, on la crée
						if (!$valeurChampLibre) {
							$valeurChampLibre = new ValeurChampLibre();
							$valeurChampLibre
								->addDemandesCollecte($collecte)
								->setChampLibre($this->champLibreRepository->find($champ));
							$em->persist($valeurChampLibre);
						}
						$valeurChampLibre->setValeur($data[$champ]);
						$em->flush();
					}
				}

				$response = [
					'entete' => $this->renderView('collecte/enteteCollecte.html.twig', [
						'collecte' => $collecte,
						'modifiable' => ($collecte->getStatut()->getNom() == Collecte::STATUS_BROUILLON),
						'ordreCollecte' => $ordreCollecte,
						'champsLibres' => $this->valeurChampLibreRepository->getByDemandeCollecte($collecte)
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
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_COLLECTE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecte = $this->collecteRepository->find($data['collecte']);
            $entityManager = $this->getDoctrine()->getManager();
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
     */
    public function hasArticles(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articles = $this->articleRepository->findByCollecteId($data['id']);
            $referenceCollectes = $this->collecteReferenceRepository->findByCollecte($data['id']);
            $count = count($articles) + count($referenceCollectes);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }
}
