<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
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
     * @param EntityManagerInterface $entityManager
     * @param PreparationsManagerService $preparationsManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function finishPrepa($idPrepa,
                                Request $request,
                                EntityManagerInterface $entityManager,
                                EmplacementRepository $emplacementRepository,
                                PreparationsManagerService $preparationsManager): Response {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $preparation = $this->preparationRepository->find($idPrepa);
        $emplacementTo = $emplacementRepository->find($request->request->get('emplacement'));

        // we create mouvements
        $preparationsManager->createMouvementAndScission($preparation, $this->getUser());

        $dateEnd = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $livraison = $preparationsManager->persistLivraison($dateEnd);
        $preparationsManager->treatPreparation($preparation, $livraison, $this->getUser());
        $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $emplacementTo);

        $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementRepository->findByPreparation($preparation);

        foreach ($mouvements as $mouvement) {
            $article = $mouvement->getArticle();
            $refArticle = $mouvement->getRefArticle();
            $preparationsManager->treatMouvement(
                $mouvement->getQuantity(),
                $preparation,
                $this->getUser(),
                $livraison,
                isset($refArticle),
                isset($refArticle) ? $refArticle : $article,
                $emplacementTo
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
            $preparation->setUtilisateur($this->getUser()->getUsername());
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
        if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
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
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
                return $this->redirectToRoute('access_denied');

            }

            $data = $this->preparationsManagerService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api_article/{id}", name="preparation_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function lignePreparationApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $demande = $this->demandeRepository->find($id);
            if ($demande) {
                $rows = [];

                $lignesArticles = $this->ligneArticleRepository->findByDemande($demande->getId());
                foreach ($lignesArticles as $ligneArticle) {
                    $articleRef = $ligneArticle->getReference();
                    $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF);
                    $qtt = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE ?
                        $this->articleRepository->getTotalQuantiteFromRefNotInDemand($articleRef, $statutArticleActif) :
                        $articleRef->getQuantiteStock();

                    $rows[] = [
                        "Référence" => ($ligneArticle->getReference() ? $ligneArticle->getReference()->getReference() : ' '),
                        "Libellé" => ($ligneArticle->getReference() ? $ligneArticle->getReference()->getLibelle() : ' '),
                        "Emplacement" => ($ligneArticle->getReference()->getEmplacement() ? $ligneArticle->getReference()->getEmplacement()->getLabel() : ''),
                        "Quantité" => $qtt,
                        "Quantité à prélever" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ' '),
                        "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                            'refArticleId' => $ligneArticle->getReference()->getId(),
                        ])
                    ];
                }

                $articles = $this->articleRepository->findByDemande($demande);
                foreach ($articles as $ligneArticle) {
                    $rows[] = [
                        "Référence" => $ligneArticle->getArticleFournisseur()->getReferenceArticle() ? $ligneArticle->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "Libellé" => $ligneArticle->getLabel() ? $ligneArticle->getLabel() : '',
                        "Emplacement" => $ligneArticle->getEmplacement() ? $ligneArticle->getEmplacement()->getLabel() : '',
                        "Quantité" => $ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : '',
                        "Quantité à prélever" => $ligneArticle->getQuantiteAPrelever() ? $ligneArticle->getQuantiteAPrelever() : '',
                        "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                            'id' => $ligneArticle->getId()
                        ])
                    ];
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
        if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('preparation/show.html.twig', [
            'demande' => $this->demandeRepository->findOneByPreparation($preparation),
            'livraison' => $this->livraisonRepository->findOneByPreparation($preparation),
            'preparation' => $preparation,
            'statut' => $preparation->getStatut() === $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER),
            'finished' => $preparation->getStatut()->getNom() !== Preparation::STATUT_PREPARE,
            'articles' => $this->articleRepository->getIdRefLabelAndQuantity(),
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="preparation_delete", methods="GET|POST")
     */
    public function delete(Preparation $preparation): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PREPA, Action::LIST)) {
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
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $demande = $this->demandeRepository->find($data['demande']);
            $response = [];
            $key = 0;
            foreach ($demande->getLigneArticle() as $ligneArticle) {
                $refArticle = $ligneArticle->getReference();
                $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF);
                $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);
                if ($ligneArticle->getToSplit()) {
                    $response['prepas'][] = $this->renderView('preparation/modalSplitting.html.twig', [
                        'reference' => $refArticle->getReference(),
                        'referenceId' => $refArticle->getId(),
                        'articles' => $articles,
                        'index' => $key,
                        'quantite' => $ligneArticle->getQuantite(),
                        'preparation' => $ligneArticle->getDemande()->getPreparation(),
                        'demande' => $ligneArticle->getDemande()
                    ]);
                    $key++;
                }
            }
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

			if ($totalQuantity == $data['quantite']) {
				$em = $this->getDoctrine()->getManager();
				$demande = $this->demandeRepository->find($data['demande']);
				$i = 0;
				foreach ($data['articles'] as $idArticle => $quantite) {
					$article = $this->articleRepository->find($idArticle);
					if ($quantite !== '' && $quantite > 0) {
						$article->setQuantiteAPrelever($quantite);
						$article->setDemande($demande);
					}
					$i++;
					if ($i === count($data['articles'])) {
						$refArticle = $article->getArticleFournisseur()->getReferenceArticle();
						$em->remove($this->ligneArticleRepository->findOneByRefArticleAndDemandeAndToSplit($refArticle, $demande));
					}
				}
				$em->flush();
				$resp = true;
			} else {
				$resp = false;
			}
            return new JsonResponse($resp);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/prelever-articles", name="preparation_take_articles", options={"expose"=true},  methods="GET|POST")
     */
    public function takeArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getManager();

            // modification des articles de la demande
            $demande = $this->demandeRepository->find($data['demande']);
            $articles = $demande->getArticles();
            foreach ($articles as $article) {
                $article->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                // scission des articles dont la quantité prélevée n'est pas totale
                if ($article->getQuantite() !== $article->getQuantiteAPrelever()) {
                    $newArticle = [
                        'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                        'libelle' => $article->getLabel(),
                        'conform' => !$article->getConform(),
                        'commentaire' => $article->getcommentaire(),
                        'quantite' => $article->getQuantite() - $article->getQuantiteAPrelever(),
                        'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                        'statut' => Article::STATUT_ACTIF,
						'prix' => $article->getPrixUnitaire(),
						'refArticle' => isset($data['refArticle']) ? $data['refArticle'] : $article->getArticleFournisseur()->getReferenceArticle()->getId()
                    ];

                    foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
////                    	spécifique CEA : vider le champ libre code projet
//						$labelCL = strtolower($valeurChampLibre->getChampLibre()->getLabel());
//						if (!(
//							$this->specificService->isCurrentClientNameFunction(ParamClient::CEA_LETI)
//							&& ($labelCL == 'code projet' || $labelCL == 'destinataire'))) {
						$newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
//						}
                    }
                    $this->articleDataService->newArticle($newArticle);

                    $article->setQuantite($article->getQuantiteAPrelever(), 0);
                }
            }

            // modif du statut de la préparation
            $preparation = $demande->getPreparation();
            $statutEDP = $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_EN_COURS_DE_PREPARATION);
            $preparation->setStatut($statutEDP);
            $em->flush();

            return new JsonResponse($statutEDP->getNom());
        }
        throw new NotFoundHttpException('404');
    }
}
