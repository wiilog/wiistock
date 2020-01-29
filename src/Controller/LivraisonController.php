<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;

use App\Repository\ArticleRepository;
use App\Repository\LivraisonRepository;
use App\Repository\PreparationRepository;
use App\Repository\DemandeRepository;
use App\Repository\StatutRepository;
use App\Repository\EmplacementRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\TypeRepository;

use App\Service\LivraisonService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\UserService;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;

	/**
	 * @var LivraisonService
	 */
    private $livraisonService;


    public function __construct(LivraisonService $livraisonService, CategorieCLRepository $categorieCLRepository, TypeRepository $typeRepository, ValeurChampLibreRepository $valeurChampLibreRepository, ChampLibreRepository $champsLibreRepository, UtilisateurRepository $utilisateurRepository, ReferenceArticleRepository $referenceArticleRepository, PreparationRepository $preparationRepository, LigneArticleRepository $ligneArticleRepository, EmplacementRepository $emplacementRepository, DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, StatutRepository $statutRepository, UserService $userService, MailerService $mailerService, ArticleRepository $articleRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->champLibreRepository = $champsLibreRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->statutRepository = $statutRepository;
        $this->preparationRepository = $preparationRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->livraisonService = $livraisonService;
    }

    /**
     * @Route("/", name="livraison_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('livraison/index.html.twig', [
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON),
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="livraison_finish", options={"expose"=true}, methods={"GET", "POST"})
     * @param Livraison $livraison
     * @param LivraisonsManagerService $livraisonsManager
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws Exception
     */
    public function finish(Livraison $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           DemandeRepository $demandeRepository,
                           EntityManagerInterface $entityManager): Response {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($livraison->getStatut()->getnom() === Livraison::STATUT_A_TRAITER) {
            $dateEnd = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $demande = $demandeRepository->findOneByLivraison($livraison);
            $livraisonsManager->finishLivraison(
                $this->getUser(),
                $livraison,
                $dateEnd,
                $demande->getDestination()
            );
            $entityManager->flush();
        }
        return $this->redirectToRoute('livraison_show', [
            'id' => $livraison->getId()
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

			$data = $this->livraisonService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function apiArticle(Request $request, Livraison $livraison): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $preparation = $livraison->getPreparation();
            $data = [];
            if ($preparation) {
                $rows = [];
                foreach ($preparation->getArticles() as $article) {
                    if ($article->getQuantite() !== 0) {
                        $rows[] = [
                            "Référence" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                            "Libellé" => $article->getLabel() ? $article->getLabel() : '',
                            "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                            "Quantité" => $article->getQuantitePrelevee(),
                            "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                'id' => $article->getId(),
                            ])
                        ];
                    }
                }

                foreach ($preparation->getLigneArticlePreparations() as $ligne) {
                	if ($ligne->getQuantitePrelevee() > 0) {
						$rows[] = [
							"Référence" => $ligne->getReference()->getReference(),
							"Libellé" => $ligne->getReference()->getLibelle(),
							"Emplacement" => $ligne->getReference()->getEmplacement() ? $ligne->getReference()->getEmplacement()->getLabel() : '',
							"Quantité" => $ligne->getQuantitePrelevee(),
							"Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
								'refArticleId' => $ligne->getReference()->getId(),
							])
						];
					}
                }

                $data['data'] = $rows;
            } else {
                $data = false; //TODO gérer retour message erreur
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", methods={"GET","POST"})
     */
    public function show(Livraison $livraison): Response
    {
        if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('livraison/show.html.twig', [
            'demande' => $this->demandeRepository->findOneByLivraison($livraison),
            'livraison' => $livraison,
            'preparation' => $this->preparationRepository->find($livraison->getPreparation()->getId()),
            'finished' => ($livraison->getStatut()->getNom() === Livraison::STATUT_LIVRE || $livraison->getStatut()->getNom() === Livraison::STATUT_INCOMPLETE)
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="livraison_delete", options={"expose"=true},methods={"GET","POST"})
     */

    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::LIVRAISON, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $livraison = $this->livraisonRepository->find($data['livraison']);

            $statutP = $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);

            $preparation = $livraison->getpreparation();
            $preparation->setStatut($statutP);

            $demandes = $livraison->getDemande();
            foreach ($demandes as $demande) {
                $demande->setLivraison(null);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($livraison);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('preparation_show', [
                    'id' => $livraison->getPreparation()->getId()
                ]),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @Route("/infos", name="get_ordres_livraison_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 */
	public function getOrdreLivraisonIntels(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

			$livraisons = $this->livraisonRepository->findByDates($dateTimeMin, $dateTimeMax);

			$headers = [
				'numéro',
				'statut',
				'date création',
				'opérateur',
				'type',
				'référence',
				'libellé',
				'emplacement',
				'quantité à livrer',
				'quantité en stock',
				'code-barre'
			];

			$data = [];
			$data[] = $headers;

			foreach ($livraisons as $livraison) {
				$this->buildInfos($livraison, $data);
			}
			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}


	private function buildInfos(Livraison $livraison, &$data)
	{
		$demande = !empty($livraison->getDemande()) ? $livraison->getDemande()[0] : null;
		if ($demande) {
            $dataLivraison =
                [
                    $livraison->getNumero() ?? '',
                    $livraison->getStatut() ? $livraison->getStatut()->getNom() : '',
                    $livraison->getDate() ? $livraison->getDate()->format('d/m/Y h:i') : '',
                    $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
                    $demande ? $demande->getType() ? $demande->getType()->getLabel() : '' : '',
                ];

            foreach ($demande->getLigneArticle() as $ligneArticle) {
                $referenceArticle = $ligneArticle->getReference();

                $data[] = array_merge($dataLivraison, [
                    $referenceArticle->getReference() ?? '',
                    $referenceArticle->getLibelle() ?? '',
                    $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                    $ligneArticle->getQuantite() ?? 0,
                    $referenceArticle->getBarCode(),
                ]);
            }

            foreach ($demande->getArticles() as $article) {
                $articleFournisseur = $article->getArticleFournisseur();
                $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                $reference = $referenceArticle ? $referenceArticle->getReference() : '';

                $data[] = array_merge($dataLivraison, [
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
