<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Repository\EmplacementRepository;
use App\Repository\PreparationRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\PreparationsManagerService;
use App\Service\SpecificService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ArticleDataService;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;
use App\Entity\Demande;
use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\StatutRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\LigneArticleRepository;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
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
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var PreparationsManagerService
     */
    private $preparationsManagerService;

    public function __construct(PreparationsManagerService $preparationsManagerService, TypeRepository $typeRepository, UtilisateurRepository $utilisateurRepository, SpecificService $specificService, LivraisonRepository $livraisonRepository, ArticleDataService $articleDataService, PreparationRepository $preparationRepository, LigneArticleRepository $ligneArticleRepository, ArticleRepository $articleRepository, StatutRepository $statutRepository, DemandeRepository $demandeRepository, ReferenceArticleRepository $referenceArticleRepository, UserService $userService)
    {
        $this->typeRepository = $typeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->statutRepository = $statutRepository;
        $this->preparationRepository = $preparationRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->specificService = $specificService;
        $this->preparationsManagerService = $preparationsManagerService;
    }


    /**
     * @Route("/finish/{idPrepa}", name="preparation_finish", methods={"POST"})
     * @param $idPrepa
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param EmplacementRepository $emplacementRepository
     * @param PreparationsManagerService $preparationsManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function finishPrepa($idPrepa,
                                Request $request,
                                EntityManagerInterface $entityManager,
                                EmplacementRepository $emplacementRepository,
                                PreparationsManagerService $preparationsManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $preparation = $this->preparationRepository->find($idPrepa);
        $locationEndPrepa = $emplacementRepository->find($request->request->get('emplacement'));

        $preparationsManager->createMouvementsPrepaAndSplit($preparation, $this->getUser());

        $dateEnd = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $livraison = $preparationsManager->persistLivraison($dateEnd);
        $preparationsManager->treatPreparation($preparation, $livraison, $this->getUser(), $locationEndPrepa);
        $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $locationEndPrepa);

        $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementRepository->findByPreparation($preparation);

        foreach ($mouvements as $mouvement) {
            $preparationsManager->createMouvementLivraison(
                $mouvement->getQuantity(),
                $this->getUser(),
                $livraison,
                $locationEndPrepa,
                !empty($mouvement->getRefArticle()),
                $mouvement->getRefArticle() ?? $mouvement->getArticle(),
                $preparation,
                false
            );
        }

        $entityManager->flush();

        return $this->redirectToRoute('livraison_show', [
            'id' => $livraison->getId(),
        ]);
    }

    /**
     * @Route("/creer", name="", methods="POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $preparation = new Preparation();

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $preparation->setNumero('P-' . $date->format('YmdHis'));
            $preparation->setDate($date);
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
            $preparation->setStatut($statut);

            foreach ($data as $key) {
                $demande = $this->demandeRepository->find($key);
                $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_A_TRAITER);
                $demande
                    ->setPreparation($preparation)
                    ->setStatut($statut);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($preparation);
            $em->flush();

            $data = [
                "preparation" => [
                    "id" => $preparation->getId(),
                    "numero" => $preparation->getNumero(),
                    "date" => $preparation->getDate()->format("d/m/Y H:i:s"),
                    "Statut" => $preparation->getStatut()->getNom()
                ],
                "message" => "Votre préparation à été enregistrée."
            ];
            $data = json_encode($data);
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="preparation_index", methods="GET|POST")
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
            return $this->redirectToRoute('access_denied');
        }
        return $this->render('preparation/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(Preparation::CATEGORIE),
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
        ]);
    }

    /**
     * @Route("/api", name="preparation_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->preparationsManagerService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api_article/{id}/{prepaId}", name="preparation_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param $id
     * @param $prepaId
     * @return Response
     * @throws NonUniqueResultException
     */
    public function apiLignePreparation(Request $request, $id, $prepaId): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $this->demandeRepository->find($id);
            $preparation = $this->preparationRepository->find($prepaId);
            $preparationStatut = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;
            $isPrepaEditable = $preparationStatut === Preparation::STATUT_A_TRAITER || ($preparationStatut == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser());

            if ($demande) {
                $rows = [];

                $lignesArticles = $this->ligneArticleRepository->findByDemande($demande->getId());
                foreach ($lignesArticles as $ligneArticle) {
                    $articleRef = $ligneArticle->getReference();
                    $isRefByRef = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                    $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF);
                    $qtt = $isRefByRef ?
                        $this->articleRepository->getTotalQuantiteFromRefNotInDemand($articleRef, $statutArticleActif) :
                        $articleRef->getQuantiteStock();


                    if ($ligneArticle->getQuantitePrelevee() > 0 ||
                        ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                        $rows[] = [
                            "Référence" => $articleRef ? $articleRef->getReference() : ' ',
                            "Libellé" => $articleRef ? $articleRef->getLibelle() : ' ',
                            "Emplacement" => $articleRef ? ($articleRef->getEmplacement() ? $articleRef->getEmplacement()->getLabel() : '') : '',
                            "Quantité" => $qtt,
                            "Quantité à prélever" => $ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ' ',
                            "Quantité prélevée" => $ligneArticle->getQuantitePrelevee() ? $ligneArticle->getQuantitePrelevee() : ' ',
                            "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                                'barcode' => $articleRef->getBarCode(),
                                'demandeId' => $id,
                                'isRef' => true,
                                'artOrRefId' => $articleRef->getId(),
                                'isRefByRef' => $isRefByRef,
                                'quantity' => $qtt,
                                'id' => $ligneArticle->getId(),
                                'isPrepaEditable' => $isPrepaEditable,
                                'active' => !empty($ligneArticle->getQuantitePrelevee())
                            ])
                        ];
                    }
                }

                $articles = $this->articleRepository->findByDemande($demande);
                foreach ($articles as $article) {
                    if ($article->getQuantite() > 0 ||
                        ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                        if (empty($article->getQuantiteAPrelever())) {
                            $article->setQuantiteAPrelever($article->getQuantite());
                            $this->getDoctrine()->getManager()->flush();
                        }
                        $rows[] = [
                            "Référence" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                            "Libellé" => $article->getLabel() ? $article->getLabel() : '',
                            "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                            "Quantité" => $article->getQuantite() ?? '',
                            "Quantité à prélever" => $article->getQuantiteAPrelever() ? $article->getQuantiteAPrelever() : '',
                            "Quantité prélevée" => $article->getQuantitePrelevee() ? $article->getQuantitePrelevee() : ' ',
                            "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                                'barcode' => $article->getBarCode(),
                                'demandeId' => $id,
                                'artOrRefId' => $article->getId(),
                                'isRef' => false,
                                'isRefByRef' => false,
                                'quantity' => $article->getQuantiteAPrelever(),
                                'id' => $article->getId(),
                                'isPrepaEditable' => $isPrepaEditable,
                                'active' => !empty($article->getQuantitePrelevee())
                            ])
                        ];
                    }
                }

                $data['data'] = $rows;
            } else {
                $data = false; //TODO gérer affichage erreur
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="preparation_show", methods="GET|POST")
     */
    public function show(Preparation $preparation): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_PREPA)) {
            return $this->redirectToRoute('access_denied');
        }

        $preparationStatus = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;

        return $this->render('preparation/show.html.twig', [
            'demande' => $this->demandeRepository->findOneByPreparation($preparation),
            'livraison' => $this->livraisonRepository->findOneByPreparation($preparation),
            'preparation' => $preparation,
            'isPrepaEditable' => $preparationStatus === Preparation::STATUT_A_TRAITER || ($preparationStatus == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser()),
            'articles' => $this->articleRepository->getIdRefLabelAndQuantity(),
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="preparation_delete", methods="GET|POST")
     */
    public function delete(Preparation $preparation): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
            return $this->redirectToRoute('access_denied');
        }

        $em = $this->getDoctrine()->getManager();
        foreach ($preparation->getDemandes() as $demande) {
            $demande
                ->setPreparation(null)
                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_BROUILLON));

            foreach ($demande->getArticles() as $article) {
                $article->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF));
                if ($article->getQuantiteAPrelever()) {
                    $article->setQuantite($article->getQuantiteAPrelever());
                    $article->setQuantiteAPrelever(0);
                    $article->setQuantitePrelevee(0);
                }
            }
        }

        $em->remove($preparation);
        $em->flush();
        return $this->redirectToRoute('preparation_index');
    }

    /**
     * @Route("/commencer-scission", name="start_splitting", options={"expose"=true}, methods="GET|POST")
     * Get list of article
     */
    public function startSplitting(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $ligneArticleId = json_decode($request->getContent(), true)) {

            $ligneArticle = $this->ligneArticleRepository->find($ligneArticleId);

            $refArticle = $ligneArticle->getReference();
            $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF);
            $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);
            $demande = $ligneArticle->getDemande();

            $response = $this->renderView('preparation/modalSplitting.html.twig', [
                'reference' => $refArticle->getReference(),
                'referenceId' => $refArticle->getId(),
                'articles' => $articles,
                'quantite' => $ligneArticle->getQuantite(),
                'preparation' => $demande ? $demande->getPreparation() : null,
                'demande' => $demande
            ]);

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/finir-scission", name="submit_splitting", options={"expose"=true}, methods="GET|POST")
     */
    public function submitSplitting(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $totalQuantity = 0;
            foreach ($data['articles'] as $id => $quantity) {
                $totalQuantity += $quantity;
            }
            if (!empty($data['articles'])) {
                $em = $this->getDoctrine()->getManager();
                $demande = $this->demandeRepository->find($data['demande']);
                $articleFirst = $this->articleRepository->find(array_key_first($data['articles']));
                $refArticle = $articleFirst->getArticleFournisseur()->getReferenceArticle();
                $ligneArticle = $this->ligneArticleRepository->findOneByRefArticleAndDemandeAndToSplit($refArticle, $demande);
                foreach ($data['articles'] as $idArticle => $quantite) {
                    $article = $this->articleRepository->find($idArticle);
                    $this->preparationsManagerService->treatArticleSplitting($article, $quantite, $ligneArticle);
                }
                $this->preparationsManagerService->deleteLigneRefOrNot($ligneArticle);
                $em->flush();
                $resp = true;
            } else {
                $resp = false;
            }
            return new JsonResponse($resp);
        }
        throw new NotFoundHttpException('404');
    }

//	/**
//	 * @Route("/prelever-articles", name="preparation_take_articles", options={"expose"=true},  methods="GET|POST")
//	 */
//	public function takeArticle(Request $request): Response
//	{
//		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
//			if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
//				return $this->redirectToRoute('access_denied');
//			}
//			$em = $this->getDoctrine()->getManager();
//
//			// modification des articles de la demande
//			$demande = $this->demandeRepository->find($data['demande']);
//			$articles = $demande->getArticles();
//			foreach ($articles as $article) {
//				$article->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
//				// scission des articles dont la quantité prélevée n'est pas totale
//				if ($article->getQuantiteAPrelever() &&
//					($article->getQuantite() !== $article->getQuantiteAPrelever())) {
//					$newArticle = [
//						'articleFournisseur' => $article->getArticleFournisseur()->getId(),
//						'libelle' => $article->getLabel(),
//						'conform' => !$article->getConform(),
//						'commentaire' => $article->getcommentaire(),
//						'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
//						'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
//						'statut' => Article::STATUT_ACTIF,
//						'prix' => $article->getPrixUnitaire(),
//						'refArticle' => isset($data['refArticle'])
//							? $data['refArticle']
//							: $article->getArticleFournisseur()->getReferenceArticle()->getReference()
//					];
//
//					foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
//						$newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
//					}
//					$this->articleDataService->newArticle($newArticle);
//
//					$article->setQuantite($article->getQuantiteAPrelever());
//				}
//			}
//
//			// modif du statut de la préparation
//			$preparation = $demande->getPreparation();
//			$statutEDP = $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_EN_COURS_DE_PREPARATION);
//			$preparation->setStatut($statutEDP);
//			$em->flush();
//
//			return new JsonResponse($statutEDP->getNom());
//		}
//		throw new NotFoundHttpException('404');
//	}


    /**
     * @Route("/modifier-article", name="prepa_edit_ligne_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editLigneArticle(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($data['isRef']) {
                $ligneArticle = $this->ligneArticleRepository->find($data['ligneArticle']);
            } else {
                $ligneArticle = $this->articleRepository->find($data['ligneArticle']);
            }

            if (isset($data['quantite'])) $ligneArticle->setQuantitePrelevee(max($data['quantite'], 0)); // protection contre quantités négatives

            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article-api", name="prepa_edit_api", options={"expose"=true}, methods={"GET","POST"} )
     */
    public function apiEditLigneArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($data['ref']) {
                $ligneArticle = $this->ligneArticleRepository->find($data['id']);
                $quantity = $ligneArticle->getQuantite();
            } else {
                $article = $this->articleRepository->find($data['id']);
                $quantity = $article->getQuantiteAPrelever();
            }

            $json = $this->renderView(
                'preparation/modalEditLigneArticleContent.html.twig',
                [
                    'isRef' => $data['ref'],
                    'quantity' => $quantity,
                ]
            );

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/commencer-preparation", name="prepa_begin", options={"expose"=true}, methods={"GET","POST"} )
     */
    public function beginPrepa(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $prepaId = json_decode($request->getContent(), true)) {

            $preparation = $this->preparationRepository->find($prepaId);

            if ($preparation) {
                $statusInProgress = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
                $preparation
                    ->setStatut($statusInProgress)
                    ->setUtilisateur($this->getUser());
                $this->getDoctrine()->getManager()->flush();
            }

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/infos", name="get_ordres_prepa_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 */
	public function getOrdrePrepaIntels(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

			$preparations = $this->preparationRepository->findByDates($dateTimeMin, $dateTimeMax);

			$headers = [
				'numéro',
				'statut',
				'date création',
				'opérateur',
				'type',
				'référence',
				'libellé',
				'emplacement',
				'quantité à collecter',
				'code-barre'
			];

			$data = [];
			$data[] = $headers;

			foreach ($preparations as $preparation) {
				$this->buildInfos($preparation, $data);
			}
			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}


	private function buildInfos(Preparation $preparation, &$data)
	{
		$demande = $preparation->getDemandes()[0];

		$dataPrepa =
			[
				$preparation->getNumero() ?? '',
				$preparation->getStatut() ? $preparation->getStatut()->getNom() : '',
				$preparation->getDate() ? $preparation->getDate()->format('d/m/Y h:i') : '',
				$preparation->getUtilisateur() ? $preparation->getUtilisateur()->getUsername() : '',
				$demande->getType() ? $demande->getType()->getLabel() : '',
			];

		foreach ($demande->getLigneArticle() as $ligneArticle) {
			$referenceArticle = $ligneArticle->getReference();

			if ($ligneArticle->getQuantitePrelevee() > 0) {
				$data[] = array_merge($dataPrepa, [
					$referenceArticle->getReference() ?? '',
					$referenceArticle->getLibelle() ?? '',
					$referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
					$ligneArticle->getQuantite() ?? 0,
					$referenceArticle->getBarCode(),
				]);
			}
		}

		foreach ($demande->getArticles() as $article) {
			$articleFournisseur = $article->getArticleFournisseur();
			$referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
			$reference = $referenceArticle ? $referenceArticle->getReference() : '';

			if ($article->getQuantitePrelevee() > 0) {
				$data[] = array_merge($dataPrepa, [
					$reference,
					$article->getLabel() ?? '',
					$article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
					$article->getQuantite() ?? 0,
					$article->getBarCode(),
				]);
			}
		}
	}
}
