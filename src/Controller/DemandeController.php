<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Demande;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\LigneArticle;
use App\Entity\Article;

use App\Entity\ValeurChampsLibre;
use App\Repository\CategorieCLRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\DemandeRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\PreparationRepository;
use App\Repository\ValeurChampsLibreRepository;

use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

	/**
	 * @var TypeRepository
	 */
    private $typeRepository;

    /**
	 * @var ChampsLibreRepository
	 */
    private $champLibreRepository;

	/**
	 * @var ValeurChampsLibreRepository
	 */
    private $valeurChampLibreRepository;
	/**
	 * @var CategorieCLRepository
	 */
    private $categorieCLRepository;

	/**
	 * @var ParametreRoleRepository
	 */
    private $parametreRoleRepository;

	/**
	 * @var ParametreRepository
	 */
    private $parametreRepository;


    public function __construct(ParametreRepository $parametreRepository, ParametreRoleRepository $parametreRoleRepository, ValeurChampsLibreRepository $valeurChampLibreRepository, CategorieCLRepository $categorieCLRepository, ChampsLibreRepository $champLibreRepository, TypeRepository $typeRepository, PreparationRepository $preparationRepository, ArticleRepository $articleRepository, LigneArticleRepository $ligneArticleRepository, DemandeRepository $demandeRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, UserService $userService, RefArticleDataService $refArticleDataService, ArticleDataService $articleDataService)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->userService = $userService;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->preparationRepository = $preparationRepository;
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->parametreRepository = $parametreRepository;
    }

    /**
     * @Route("/compareStock", name="compare_stock", options={"expose"=true}, methods="GET|POST")
     */
    public function compareStock(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data['demande']);

            $response = [];
            $response['status'] = false;
            // pour réf gérées par articles
            $articles = $demande->getArticles();
            $statutArticleActif = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
            foreach ($articles as $article) {
                $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                $totalQuantity = $this->articleRepository->getTotalQuantiteByRefAndStatut($refArticle, $statutArticleActif);
                $totalQuantity -= $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($refArticle);
                $treshHold = ($article->getQuantite() > $totalQuantity) ? $totalQuantity : $article->getQuantite();
                if ($article->getQuantiteAPrelever() > $treshHold) {
                    $response['stock'] = $treshHold;
                    return new JsonResponse($response);
                }
            }

            // pour réf gérées par référence
            foreach ($demande->getLigneArticle() as $ligne) {
                if (!$ligne->getToSplit()) {
                    $articleRef = $ligne->getReference();

                    $stock = $articleRef->getQuantiteStock();
                    $quantiteReservee = $ligne->getQuantite();

                    $listLigneArticleByRefArticle = $this->ligneArticleRepository->findByRefArticle($articleRef);

                    foreach ($listLigneArticleByRefArticle as $ligneArticle) {
                        /** @var LigneArticle $ligneArticle */
                        $statusLabel = $ligneArticle->getDemande()->getStatut()->getNom();
                        if ($statusLabel === Demande::STATUT_A_TRAITER || $statusLabel === Demande::STATUT_PREPARE) {
                            $quantiteReservee += $ligneArticle->getQuantite();
                        }
                    }

                    if ($quantiteReservee > $stock) {
                        $response['stock'] = $stock;
                        return new JsonResponse($response);
                    }
                } else {
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
                    $statutDemande = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
                    $totalQuantity = $this->articleRepository->getTotalQuantiteByRefAndStatut($ligne->getReference(), $statut);
                    $totalQuantity -= $this->referenceArticleRepository->getTotalQuantityReservedWithoutLigne($ligne->getReference(), $ligne, $statutDemande);
                    if ($ligne->getQuantite() > $totalQuantity) {
                        $response['stock'] = $totalQuantity;
                        return new JsonResponse($response);
                    }
                }
            }

            return $this->finish($request);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/finir", name="finish_demande", options={"expose"=true}, methods="GET|POST")
     */
    public function finish(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getManager();

            $demande = $this->demandeRepository->find($data['demande']);

            // Creation d'une nouvelle preparation basée sur une selection de demandes
            $preparation = new Preparation();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $preparation
                ->setNumero('P-' . $date->format('YmdHis'))
                ->setDate($date);

            $statutP = $this->statutRepository->findOneByCategorieAndStatut(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
            $preparation->setStatut($statutP);

            $demande->setPreparation($preparation);
            $statutD = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
            $demande->setStatut($statutD);
            $em->persist($preparation);

            // modification du statut articles => en transit
            $articles = $demande->getArticles();
            foreach ($articles as $article) {
                $article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
            }
            $em->flush();

            //renvoi de l'en-tête avec modification
            $data = [
                'entete' => $this->renderView(
                    'demande/enteteDemandeLivraison.html.twig',
                    [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
						'champsLibres' => $this->valeurChampLibreRepository->getByDemandeLivraison($demande)
					]
                ),
                'status' => true
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="demandeLivraison_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $this->demandeRepository->find($data['id']);

			$typesDL = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);
			$typeChampLibre =  [];

			foreach ($typesDL as $type) {
				$champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);
				$champsLibresArray = [];
				foreach ($champsLibres as $champLibre) {
					$valeurChampDL = $this->valeurChampLibreRepository->getValueByDemandeLivraisonAndChampLibre($demande, $champLibre);
					$champsLibresArray[] = [
						'id' => $champLibre->getId(),
						'label' => $champLibre->getLabel(),
						'typage' => $champLibre->getTypage(),
						'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
						'defaultValue' => $champLibre->getDefaultValue(),
						'valeurChampLibre' => $valeurChampDL,
					];
				}
				$typeChampLibre[] = [
					'typeLabel' =>  $type->getLabel(),
					'typeId' => $type->getId(),
					'champsLibres' => $champsLibresArray,
				];
			}

            $json = $this->renderView('demande/modalEditDemandeContent.html.twig', [
                'demande' => $demande,
				'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
				'typeChampsLibres' => $typeChampLibre
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

			// vérification des champs Libres obligatoires
			$requiredEdit = true;
			$type =  $this->typeRepository->find(intval($data['type']));
			$CLRequired = $this->champLibreRepository->getByTypeAndRequiredEdit($type);
			foreach ($CLRequired as $CL) {
				if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
					$requiredEdit = false;
				}
			}

			if ($requiredEdit) {
				$utilisateur = $this->utilisateurRepository->find(intval($data['demandeur']));
				$emplacement = $this->emplacementRepository->find(intval($data['destination']));
				$type = $this->typeRepository->find(intval($data['type']));
				$demande = $this->demandeRepository->find($data['demandeId']);
				$demande
					->setUtilisateur($utilisateur)
					->setDestination($emplacement)
					->setType($type)
					->setCommentaire($data['commentaire']);
				$em = $this->getDoctrine()->getEntityManager();
				$em->flush();

				// modification ou création des champs libres
				$champsLibreKey = array_keys($data);

				foreach ($champsLibreKey as $champ) {
					if (gettype($champ) === 'integer') {
						$champLibre = $this->champLibreRepository->find($champ);
						$valeurChampLibre = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampsLibre($demande, $champLibre);

						// si la valeur n'existe pas, on la crée
						if (!$valeurChampLibre) {
							$valeurChampLibre = new ValeurChampsLibre();
							$valeurChampLibre
								->addDemandesLivraison($demande)
								->setChampLibre($this->champLibreRepository->find($champ));
							$em->persist($valeurChampLibre);
						}
						$valeurChampLibre->setValeur($data[$champ]);
						$em->flush();
					}
				}

				$response = [
					'entete' => $this->renderView('demande/enteteDemandeLivraison.html.twig', [
						'demande' => $demande,
						'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
						'champsLibres' => $this->valeurChampLibreRepository->getByDemandeLivraison($demande)
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
     * @Route("/creer", name="demande_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            // protection champs libres obligatoires
			$requiredCreate = true;
			$type = $this->typeRepository->find($data['type']);

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
            $utilisateur = $this->utilisateurRepository->find($data['demandeur']);
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $destination = $this->emplacementRepository->find($data['destination']);
            $type = $this->typeRepository->find($data['type']);
            $demande = new Demande();
            $demande
                ->setStatut($statut)
                ->setUtilisateur($utilisateur)
                ->setdate($date)
				->setType($type)
                ->setDestination($destination)
                ->setNumero('D-' . $date->format('YmdHis'))
                ->setCommentaire($data['commentaire']);
            $em->persist($demande);

			// enregistrement des champs libres
			$champsLibreKey = array_keys($data);

			foreach ($champsLibreKey as $champs) {
				if (gettype($champs) === 'integer') {
					$valeurChampLibre = new ValeurChampsLibre();
					$valeurChampLibre
						->setValeur($data[$champs])
						->addDemandesLivraison($demande)
						->setChampLibre($this->champLibreRepository->find($champs));
					$em->persist($valeurChampLibre);
					$em->flush();
				}
			}

            $em->flush();

            $data = [
                'redirect' => $this->generateUrl('demande_show', ['id' => $demande->getId()]),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/", name="demande_index", methods="GET|POST", options={"expose"=true})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $types = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

		$typeChampLibre = [];
		foreach ($types as $type) {
			$champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

			$typeChampLibre[] = [
				'typeLabel' =>  $type->getLabel(),
				'typeId' => $type->getId(),
				'champsLibres' => $champsLibres,
			];
		}

        return $this->render('demande/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
			'typeChampsLibres' => $typeChampLibre,
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
        ]);
    }

    /**
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $this->demandeRepository->find($data['demandeId']);
            foreach ($demande->getArticles() as $article) {
                $article->setDemande(null);
            }
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($demande);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('demande_index'),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $demandes = $this->demandeRepository->findAll();
            $rows = [];
            foreach ($demandes as $demande) {
                $idDemande = $demande->getId();
                $url = $this->generateUrl('demande_show', ['id' => $idDemande]);
                $rows[] =
                    [
                        'Date' => ($demande->getDate() ? $demande->getDate()->format('d/m/Y') : ''),
                        'Demandeur' => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                        'Numéro' => ($demande->getNumero() ? $demande->getNumero() : ''),
                        'Statut' => ($demande->getStatut()->getNom() ? $demande->getStatut()->getNom() : ''),
                        'Type' => ($demande->getType() ? $demande->getType()->getLabel() : ''),
                        'Actions' => $this->renderView(
                            'demande/datatableDemandeRow.html.twig',
                            [
                                'idDemande' => $idDemande,
                                'url' => $url,
                            ]
                        ),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}", name="demande_show", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function show(Demande $demande): Response
    {
        if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $valeursChampLibre = $this->valeurChampLibreRepository->getByDemandeLivraison($demande);

        return $this->render('demande/show.html.twig', [

            'demande' => $demande,
           //'preparation' => $this->preparationRepository->findOneByPreparation($demande),
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Demande::CATEGORIE),
            'references' => $this->referenceArticleRepository->getIdAndLibelle(),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'emplacements' => $this->emplacementRepository->findAll(),
            'finished' => ($demande->getStatut()->getNom() === Demande::STATUT_A_TRAITER),
			'champsLibres' => $valeursChampLibre
        ]);
    }

    /**
     * @Route("/api/{id}", name="demande_article_api", options={"expose"=true},  methods="GET|POST")
     */
    public function articleApi(Request $request, Demande $demande): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticles = $demande->getLigneArticle();
            $rowsRC = [];
            foreach ($ligneArticles as $ligneArticle) {
                $articleRef = $ligneArticle->getReference();
                $statutArticleActif = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
                $totalQuantity = 0;
                if ($articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                    foreach ($articleRef->getArticlesFournisseur() as $articleFournisseur) {
                        $quantity = 0;
                        foreach ($articleFournisseur->getArticles() as $article) {
                            if ($article->getStatut() == $statutArticleActif) $quantity += $article->getQuantite();
                        }
                        $totalQuantity += $quantity;
                    }
                }
                $quantity = ($articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) ? $articleRef->getQuantiteStock() : $totalQuantity;
                $rowsRC[] = [
                    "Référence CEA" => ($ligneArticle->getReference()->getReference() ? $ligneArticle->getReference()->getReference() : ''),
                    "Libellé" => ($ligneArticle->getReference()->getLibelle() ? $ligneArticle->getReference()->getLibelle() : ''),
                    "Emplacement" => ($ligneArticle->getReference()->getEmplacement() ? $ligneArticle->getReference()->getEmplacement()->getLabel() : ' '),
                    "Quantité" => $quantity,
                    "Quantité à prélever" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ''),
                    "Actions" => $this->renderView(
                        'demande/datatableLigneArticleRow.html.twig',
                        [
                            'id' => $ligneArticle->getId(),
                            'name' => (ReferenceArticle::TYPE_QUANTITE_REFERENCE),
                            'refArticleId' => $ligneArticle->getReference()->getId(),
                            'reference' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        ]
                    )
                ];
            }
            $articles = $this->articleRepository->getByDemande($demande);
            $rowsCA = [];
            foreach ($articles as $article) {
                /** @var Article $article */
                $rowsCA[] = [
                    "Référence CEA" => ($article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                    "Libellé" => ($article->getLabel() ? $article->getLabel() : ''),
                    "Emplacement" => ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' '),
                    "Quantité" => ($article->getQuantite() ? $article->getQuantite() : ''),
                    "Quantité à prélever" => ($article->getQuantiteAPrelever() ? $article->getQuantiteAPrelever() : ''),
                    "Actions" => $this->renderView(
                        'demande/datatableLigneArticleRow.html.twig',
                        [
                            'id' => $article->getId(),
                            'name' => (ReferenceArticle::TYPE_QUANTITE_ARTICLE),
                            'reference' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        ]
                    ),
                ];
            }

            $data['data'] = array_merge($rowsCA, $rowsRC);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-article", name="demande_add_article", options={"expose"=true},  methods="GET|POST")
     */
    public function addArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getEntityManager();

            $referenceArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $demande = $this->demandeRepository->find($data['demande']);

            // cas gestion quantité par article
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                if (isset($data['wantGlobal']) && $data['wantGlobal']) {
                    if ($this->ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                        $ligneArticle = new LigneArticle();
                        $ligneArticle
                            ->setQuantite(max($data["quantitie"], 0))// protection contre quantités négatives
                            ->setToSplit(true)
                            ->setReference($referenceArticle);
                        $em->persist($ligneArticle);
                        $demande->addLigneArticle($ligneArticle);
                    } else {
                        $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemandeAndToSplit($referenceArticle, $demande);
                        $ligneArticle
                            ->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0));
                    }
                } else {
                    $article = $this->articleRepository->find($data['article']);
                    $demande->addArticle($article);
                    $article->setQuantiteAPrelever(max($data['quantitie'], 0)); // protection contre quantités négatives

                    $this->articleDataService->editArticle($data);
                }

                // cas gestion quantité par référence
            } elseif ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if ($this->ligneArticleRepository->countByRefArticleDemande($referenceArticle, $demande) < 1) {
                    $ligneArticle = new LigneArticle();
                    $ligneArticle
                        ->setQuantite(max($data["quantitie"], 0))// protection contre quantités négatives
                        ->setReference($referenceArticle);
                    $em->persist($ligneArticle);
                    $demande->addLigneArticle($ligneArticle);
                } else {
                    $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemande($referenceArticle, $demande);
                    $ligneArticle
                        ->setQuantite($ligneArticle->getQuantite() + max($data["quantitie"], 0)); // protection contre quantités négatives
                }
                $this->refArticleDataService->editRefArticle($referenceArticle, $data);
            }

            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/retirer-article", name="demande_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function removeArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $entityManager = $this->getDoctrine()->getManager();
            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $ligneAricle = $this->ligneArticleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
                $entityManager->remove($ligneAricle);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $article = $this->articleRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
                $demande = $article->getDemande();
                $demande->removeArticle($article);
            }
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-article", name="demande_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $ligneArticle = $this->ligneArticleRepository->find($data['ligneArticle']);
            $ligneArticle->setQuantite(max($data["quantite"], 0)); // protection contre quantités négatives
            $this->getDoctrine()->getEntityManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-article", name="demande_article_api_edit", options={"expose"=true}, methods={"POST"})
     */
    public function articleEditApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::DEM_LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->ligneArticleRepository->find($data['id']);
            $articleRef = $ligneArticle->getReference();
            $statutArticleActif = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
            $qtt = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE ?
                $this->articleRepository->getTotalQuantiteFromRef($articleRef, $statutArticleActif) :
                $articleRef->getQuantiteStock();
            $json = $this->renderView('demande/modalEditArticleContent.html.twig', [
                'ligneArticle' => $ligneArticle,
                'maximum' => $qtt,
                'toSplit' => $ligneArticle->getToSplit()
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/non-vide", name="demande_livraison_has_articles", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function hasArticles(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articles = $this->articleRepository->getByDemande($data['id']);
            $references = $this->ligneArticleRepository->getByDemande($data['id']);
            $count = count($articles) + count($references);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/besoin-scission", name="need_splitting", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function needSplitting(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data['demande']);
            $need = false;
            foreach ($demande->getLigneArticle() as $ligneArticle) {
                if ($ligneArticle->getToSplit()) {
                    $need = true;
                }
            }

            return new JsonResponse($need);
        }
        throw new NotFoundHttpException('404');
    }

//    /**
//     * @Route("/changer-gestion", name="switch_choice", options={"expose"=true}, methods={"GET", "POST"})
//     */
//    public function switchChoice(Request $request): Response
//    {
//        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
//
//			$refArticle = $this->referenceArticleRepository->find($data['reference']);
//            $response = [];
//            if ($data['checked']) {
//                $statutArticleActif = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
//                $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);
//                $totalQuantity = 0;
//                foreach ($articles as $article) {
//					$totalQuantity += $article->getQuantite();
//                }
//                $availableQuantity = $totalQuantity - $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($refArticle);
//
//                $response['content'] = $this->renderView('demande/choiceContent.html.twig', [
//                    'maximum' => $availableQuantity,
//                ]);
//            } else {
//                $statutArticleActif = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
//                $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);
//
//                if (count($articles) < 1) {
//                    $articles[] = [
//                        'id' => '',
//                        'reference' => 'aucun article disponible',
//                    ];
//                }
//                $response['content'] = $this->renderView('demande/newRefArticleByQuantiteArticleContent.html.twig', [
//                    'articles' => $articles,
//					'maximum' => $availableQuantity
//                ]);
//            }
//            return new JsonResponse($response);
//        }
//        throw new NotFoundHttpException('404');
//    }
}
