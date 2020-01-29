<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;

use App\Repository\ArticleRepository;
use App\Repository\CollecteReferenceRepository;
use App\Repository\CollecteRepository;
use App\Repository\EmplacementRepository;
use App\Repository\OrdreCollecteReferenceRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\MailerServerRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;

use App\Service\MailerService;
use App\Service\OrdreCollecteService;
use App\Service\UserService;

use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Exception;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/ordre-collecte")
 */
class OrdreCollecteController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var OrdreCollecteRepository
     */
    private $ordreCollecteRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var CollecteReferenceRepository
     */
    private $collecteReferenceRepository;

    /**
     * @var OrdreCollecteReferenceRepository
     */
    private $ordreCollecteReferenceRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var OrdreCollecteService
     */
    private $ordreCollecteService;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

	/**
	 * @var ReferenceArticleRepository
	 */
    private $referenceArticleRepository;

    public function __construct(ReferenceArticleRepository $referenceArticleRepository, EmplacementRepository $emplacementRepository, OrdreCollecteReferenceRepository $ordreCollecteReferenceRepository, OrdreCollecteService $ordreCollecteService, TypeRepository $typeRepository, UtilisateurRepository $utilisateurRepository, MailerServerRepository $mailerServerRepository, OrdreCollecteRepository $ordreCollecteRepository, StatutRepository $statutRepository, CollecteRepository $collecteRepository, CollecteReferenceRepository $collecteReferenceRepository, UserService $userService, MailerService $mailerService, ArticleRepository $articleRepository)
    {
        $this->ordreCollecteReferenceRepository = $ordreCollecteReferenceRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->typeRepository = $typeRepository;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->statutRepository = $statutRepository;
        $this->collecteRepository = $collecteRepository;
        $this->collecteReferenceRepository = $collecteReferenceRepository;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->ordreCollecteService = $ordreCollecteService;
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

	/**
	 * @Route("/liste/{demandId}", name="ordre_collecte_index")
	 * @param string|null $demandId
	 * @return RedirectResponse|Response
	 */
    public function index(string $demandId = null)
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
            return $this->redirectToRoute('access_denied');
        }

        $demandeCollecte = $demandId ? $this->collecteRepository->find($demandId) : null;

        return $this->render('ordre_collecte/index.html.twig', [
        	'filterDemand' => $demandId ? ($demandId . ':' . $demandeCollecte->getNumero()) : null,
            'disabled' => $demandeCollecte != null,
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::ORDRE_COLLECTE),
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_COLLECTE),
        ]);
    }

    /**
     * @Route("/api", name="ordre_collecte_api", options={"expose"=true})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
                return $this->redirectToRoute('access_denied');
            }

            // cas d'un filtre par demande de collecte
            $filterDemand = $request->request->get('filterDemand');
			$data = $this->ordreCollecteService->getDataForDatatable($request->request, $filterDemand);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="ordre_collecte_show",  methods={"GET","POST"})
     */
    public function show(OrdreCollecte $ordreCollecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('ordre_collecte/show.html.twig', [
            'collecte' => $ordreCollecte,
            'finished' => $ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_TRAITE
        ]);
    }

    /**
     * @Route("/finir/{id}", name="ordre_collecte_finish", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param OrdreCollecte $ordreCollecte
     * @return Response
     * @throws NonUniqueResultException
	 * @throws Exception
     */
    public function finish(Request $request, OrdreCollecte $ordreCollecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($data = json_decode($request->getContent(), true)) {
            if ($ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER) {
                $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
                $this->ordreCollecteService->finishCollecte(
                    $ordreCollecte,
                    $this->getUser(),
                    $date,
                    isset($data['depositLocationId']) ? $this->emplacementRepository->find($data['depositLocationId']) : null,
                    $data['rows']
                );
            }

            $data = $this->renderView('ordre_collecte/enteteOrdreCollecte.html.twig', [
            	'collecte' => $ordreCollecte,
				'finished' => $ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_TRAITE
			]);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="ordre_collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function apiArticle(Request $request, OrdreCollecte $ordreCollecte): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
                return $this->redirectToRoute('access_denied');
            }

            $rows = [];
            foreach ($ordreCollecte->getOrdreCollecteReferences() as $ligneArticle) {
                $referenceArticle = $ligneArticle->getReferenceArticle();

                $rows[] = [
                    "Référence" => $referenceArticle ? $referenceArticle->getReference() : ' ',
                    "Libellé" => $referenceArticle ? $referenceArticle->getLibelle() : ' ',
                    "Emplacement" => $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                    "Quantité" => $ligneArticle->getQuantite() ?? ' ',
                    "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                        'id' => $ligneArticle->getId(),
                        'refArticleId' => $referenceArticle->getId(),
                        'refRef' => $referenceArticle ? $referenceArticle->getReference() : '',
                        'quantity' => $ligneArticle->getQuantite(),
                        'modifiable' => $ordreCollecte->getStatut() ? ($ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER) : false,
                    ])
                ];
            }

            foreach ($ordreCollecte->getArticles() as $article) {
                $rows[] = [
                    'Référence' => $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                    'Libellé' => $article->getLabel(),
                    "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                    'Quantité' => $article->getQuantite(),
                    "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                        'id' => $article->getId(),
                        'refArt' => $article->getReference(),
                        'quantity' => $article->getQuantite(),
                        'modifiable' => $ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER
                    ])
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer/{id}", name="ordre_collecte_new", options={"expose"=true}, methods={"GET","POST"} )
     */
    public function new(Collecte $demandeCollecte): Response
    {
        if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        // on crée l'ordre de collecte
        $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $ordreCollecte
            ->setDate($date)
            ->setNumero('C-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setDemandeCollecte($demandeCollecte);
        $entityManager = $this->getDoctrine()->getManager();
        foreach ($demandeCollecte->getArticles() as $article) {
            $ordreCollecte->addArticle($article);
        }
        foreach ($demandeCollecte->getCollecteReferences() as $collecteReference) {
            $ordreCollecteReference = new OrdreCollecteReference();
            $ordreCollecteReference
                ->setOrdreCollecte($ordreCollecte)
                ->setQuantite($collecteReference->getQuantite())
                ->setReferenceArticle($collecteReference->getReferenceArticle());
            $entityManager->persist($ordreCollecteReference);
            $ordreCollecte->addOrdreCollecteReference($ordreCollecteReference);
        }
        $entityManager->persist($ordreCollecte);

        // on modifie le statut de la demande de collecte liée
        $demandeCollecte->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER));

        $entityManager->flush();

        return $this->redirectToRoute('collecte_show', [
            'id' => $demandeCollecte->getId(),
        ]);
    }

    /**
     * @Route("/modifier-article-api", name="ordre_collecte_edit_api", options={"expose"=true}, methods={"GET","POST"} )
     */
    public function apiEditArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->ordreCollecteReferenceRepository->find($data['id']);
            $modif = isset($data['ref']) && !($data['ref'] === 0);

            $json = $this->renderView(
                'ordre_collecte/modalEditArticleContent.html.twig',
                [
                    'ligneArticle' => $ligneArticle,
                    'modifiable' => $modif
                ]
            );
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="ordre_collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $ligneArticle = $this->ordreCollecteReferenceRepository->find($data['ligneArticle']);
            if (isset($data['quantite'])) $ligneArticle->setQuantite(max($data['quantite'], 0)); // protection contre quantités négatives

            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer/{id}", name="ordre_collecte_delete", options={"expose"=true},methods={"GET","POST"})
     */

    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::COLLECTE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ordreCollecte = $this->ordreCollecteRepository->find($data['collecte']);
            $collecte = $ordreCollecte->getDemandeCollecte();

            $collecte
                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Collecte::CATEGORIE, Collecte::STATUT_BROUILLON));
            $entityManager = $this->getDoctrine()->getManager();
            foreach ($ordreCollecte->getOrdreCollecteReferences() as $cr) {
                $entityManager->remove($cr);
            }
            $entityManager->remove($ordreCollecte);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('ordre_collecte_index'),
            ];

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @Route("/infos", name="get_ordres_collecte_for_csv", options={"expose"=true}, methods={"GET","POST"})
	 */
	public function getOrdreCollecteIntels(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

			$collectes = $this->ordreCollecteRepository->findByDates($dateTimeMin, $dateTimeMax);

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

			foreach ($collectes as $collecte) {
				$this->buildInfos($collecte, $data);
			}
			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}


	private function buildInfos(OrdreCollecte $ordreCollecte, &$data)
	{
		$collecte = $ordreCollecte->getDemandeCollecte();

		$dataCollecte =
		[
			$ordreCollecte->getNumero() ?? '',
			$ordreCollecte->getStatut() ? $ordreCollecte->getStatut()->getNom() : '',
			$ordreCollecte->getDate() ? $ordreCollecte->getDate()->format('d/m/Y h:i') : '',
			$ordreCollecte->getUtilisateur() ? $ordreCollecte->getUtilisateur()->getUsername() : '',
			$collecte->getType() ? $collecte->getType()->getLabel() : '',
		];

		foreach ($ordreCollecte->getOrdreCollecteReferences() as $ordreCollecteReference) {
			$referenceArticle = $ordreCollecteReference->getReferenceArticle();

			$data[] = array_merge($dataCollecte, [
				$referenceArticle->getReference() ?? '',
				$referenceArticle->getLibelle() ?? '',
				$referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
				$ordreCollecteReference->getQuantite() ?? 0,
				$referenceArticle->getBarCode(),
			]);
		}

		foreach ($ordreCollecte->getArticles() as $article) {
			$articleFournisseur = $article->getArticleFournisseur();
			$referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
			$reference = $referenceArticle ? $referenceArticle->getReference() : '';

			$data[] = array_merge($dataCollecte, [
				$reference,
				$article->getLabel() ?? '',
				$article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
				$article->getQuantite() ?? 0,
				$article->getBarCode(),
			]);
		}
	}
}
