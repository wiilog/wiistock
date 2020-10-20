<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\LitigeHistoric;

use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\LitigeHistoricRepository;
use App\Repository\TransporteurRepository;

use App\Service\CSVExportService;
use App\Service\LitigeService;
use App\Service\SpecificService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/litige")
 */
class LitigeController extends AbstractController
{
	/**
	 * @var TransporteurRepository
	 */
	private $transporteurRepository;

	/**
	 * @var UserService
	 */
	private $userService;

    /**
     * @var LitigeHistoricRepository
     */
    private $litigeHistoricRepository;

	/**
	 * @var LitigeService
	 */
	private $litigeService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param LitigeService $litigeService
     * @param UserService $userService
     * @param TransporteurRepository $transporteurRepository
     * @param TranslatorInterface $translator
     * @param LitigeHistoricRepository $litigeHistoricRepository
     */
	public function __construct(LitigeService $litigeService,
                                UserService $userService,
                                TransporteurRepository $transporteurRepository,
                                TranslatorInterface $translator,
                                LitigeHistoricRepository $litigeHistoricRepository)
	{
		$this->transporteurRepository = $transporteurRepository;
		$this->userService = $userService;
		$this->litigeHistoricRepository = $litigeHistoricRepository;
		$this->litigeService = $litigeService;
        $this->translator = $translator;
	}

    /**
     * @Route("/liste", name="litige_index", options={"expose"=true}, methods="GET|POST")
     * @param LitigeService $litigeService
     * @param EntityManagerInterface $entityManager
     * @param SpecificService $specificService
     * @return Response
     */
    public function index(LitigeService $litigeService,
                          EntityManagerInterface $entityManager,
                          SpecificService $specificService)
    {
        if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $fieldsInTab = [
            ["key" => 'disputeNumber', 'label' => 'Numéro du litige'],
            ["key" => 'type', 'label' => 'Type'],
            ["key" => 'arrivalNumber', 'label' => $this->translator->trans('arrivage.n° d\'arrivage')],
            ["key" => 'receptionNumber', 'label' => $this->translator->trans('réception.n° de réception')],
            ["key" => 'buyers', 'label' => 'Acheteur'],
            ["key" => 'numCommandeBl', 'label' => 'N° commande / BL'],
            ["key" => 'declarant', 'label' => 'Déclarant'],
            ["key" => 'command', 'label' => 'N° ligne'],
            ["key" => 'provider', 'label' => 'Fournisseur'],
            ["key" => 'references', 'label' => 'Référence'],
            ["key" => 'lastHistoric', 'label' => 'Dernier historique'],
            ["key" => 'creationDate', 'label' => 'Créé le'],
            ["key" => 'updateDate', 'label' => 'Modifié le'],
            ["key" => 'status', 'label' => 'Statut'],
        ];
        $fieldsCl = [];
        $champs = array_merge($fieldsInTab,$fieldsCl);


        return $this->render('litige/index.html.twig',[
            'statuts' => $statutRepository->findByCategorieNames([CategorieStatut::LITIGE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $this->transporteurRepository->findAllSorted(),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
			'litigeOrigins' => $litigeService->getLitigeOrigin(),
			'isCollins' => $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_COLLINS),
            'champs' => $champs,
            'columnsVisibles' => $user->getColumnsVisibleForLitige()
		]);
    }

    /**
     * @Route("/api", name="litige_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     * @throws Exception
     */
    public function api(Request $request) {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
				return $this->redirectToRoute('access_denied');
			}

			/** @var Utilisateur $user */
            $user = $this->getUser();
			$data = $this->litigeService->getDataForDatatable($request->request);
            $columnVisible = $user->getColumnsVisibleForLitige();
            $data['visible'] = $columnVisible;
			return new JsonResponse($data);
		}
		throw new NotFoundHttpException('404');
	}

    /**
     * @Route("/litiges_infos", name="get_litiges_for_csv", options={"expose"=true}, methods={"GET","POST"})
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     *
     * @return Response
     */
	public function getLitigesIntels(Request $request,
                                     EntityManagerInterface $entityManager,
                                     CSVExportService $CSVExportService): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $litigeRepository = $entityManager->getRepository(Litige::class);

			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $arrivalLitiges = $litigeRepository->findArrivalsLitigeByDates($dateTimeMin, $dateTimeMax);

			$headers = [
			    'Numéro de litige',
			    'Type',
                'Statut',
                'Date création',
                'Date modification',
                'Colis / Réferences',
                'Code barre',
                'QteArticle',
                'Ordre arrivage / réception',
				'N° Commande / BL',
                'Déclarant',
                'Fournisseur',
                'N° ligne',
                'Acheteur(s)',
                'Date commentaire',
            	'Utilisateur',
            	'Commentaire'
            ];

			$data = [$headers];

            /** @var Litige $litige */
            foreach ($arrivalLitiges as $litige) {
                $colis = $litige->getPacks();
                foreach ($colis as $coli) {

                    $colis = $litige->getPacks();
                    /** @var Arrivage $arrivage */
                    $arrivage = ($colis->count() > 0 && $colis->first()->getArrivage())
                        ? $colis->first()->getArrivage()
                        : null;
                    $acheteurs = $arrivage->getAcheteurs()->toArray();
                    $buyersMailsStr = implode('/', array_map(function(Utilisateur $acheteur) {
                        return $acheteur->getEmail();
                    }, $acheteurs));

                    $litigeData = [];

                    $litigeData[] = $litige->getNumeroLitige();
                    $litigeData[] = $CSVExportService->escapeCSV($litige->getType() ? $litige->getType()->getLabel() : '');
                    $litigeData[] = $CSVExportService->escapeCSV($litige->getStatus() ? $litige->getStatus()->getNom() : '');
                    $litigeData[] = $litige->getCreationDate() ? $litige->getCreationDate()->format('d/m/Y') : '';
                    $litigeData[] = $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '';
                    $litigeData[] = $coli->getCode();
                    $litigeData[] = ' ';
                    $litigeData[] = '' ;

                    $litigeData[] = $arrivage ? $arrivage->getNumeroArrivage() : '';

                    $numeroCommandeList = $arrivage ? $arrivage->getNumeroCommandeList() : [];
                    $litigeData[] = implode(' / ', $numeroCommandeList); // N° de commandes
                    $declarant = $litige->getDeclarant() ? $litige->getDeclarant()->getUsername() : '';
                    $litigeData[] = $declarant;
                    $fournisseur = $arrivage ? $arrivage->getFournisseur() : null;
                    $litigeData[] = $CSVExportService->escapeCSV(isset($fournisseur) ? $fournisseur->getNom() : '');
                    $litigeData[] = ''; // N° de ligne
                    $litigeData[] = $buyersMailsStr;
                    $litigeHistorics = $litige->getLitigeHistorics();
                    if ($litigeHistorics->count() === 0) {
                        $litigeData[] = '';
                        $litigeData[] = '';
                        $litigeData[] = '';
                        $data[] = $litigeData;
                    } else {
                        $historic = $litigeHistorics->last();
                        $data[] = array_merge(
                            $litigeData,
                            [
                                $historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : '',
                                $CSVExportService->escapeCSV($historic->getUser() ? $historic->getUser()->getUsername() : ''),
                                $CSVExportService->escapeCSV($historic->getComment()),
                            ]
                        );
                    }
                }
            }

            $receptionLitiges = $litigeRepository->findReceptionLitigeByDates($dateTimeMin, $dateTimeMax);

            /** @var Litige $litige */
            foreach ($receptionLitiges as $litige) {
                $articles = $litige->getArticles();
                foreach ($articles as $article) {

                    $buyers = $litige->getBuyers()->toArray();
                    $buyersMailsStr = implode('/', array_map(function(Utilisateur $acheteur) {
                        return $acheteur->getEmail();
                    }, $buyers));

                    $litigeData = [];

                    $litigeData[] = $litige->getNumeroLitige();
                    $litigeData[] = $CSVExportService->escapeCSV($litige->getType() ? $litige->getType()->getLabel() : '');
                    $litigeData[] = $CSVExportService->escapeCSV($litige->getStatus() ? $litige->getStatus()->getNom() : '');
                    $litigeData[] = $litige->getCreationDate() ? $litige->getCreationDate()->format('d/m/Y') : '';
                    $litigeData[] = $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '';

                    $referencesStr = implode(', ', $litigeRepository->getReferencesByLitigeId($litige->getId()));

                    $litigeData[] = $referencesStr;

                    /** @var Article $firstArticle */
                    $firstArticle = ($articles->count() > 0 ? $articles->first() : null);
                    $qteArticle = $article->getQuantite();
                    $receptionRefArticle = isset($firstArticle) ? $firstArticle->getReceptionReferenceArticle() : null;
                    $reception = isset($receptionRefArticle) ? $receptionRefArticle->getReception() : null;
                    $litigeData[] = $article->getBarCode();
                    $litigeData[] = $qteArticle;
                    $litigeData[] = (isset($reception) ? $reception->getNumeroReception() : '');

                    $litigeData[] = (isset($reception) ? $reception->getOrderNumber() : null);

                    $declarant = $litige->getDeclarant() ? $litige->getDeclarant()->getUsername() : '';
                    $litigeData[] = $declarant;
                    $fournisseur = (isset($reception) ? $reception->getFournisseur() : null);
                    $litigeData[] = $CSVExportService->escapeCSV(isset($fournisseur) ? $fournisseur->getNom() : '');

                    $litigeData[] = implode(', ', $litigeRepository->getCommandesByLitigeId($litige->getId()));

                    $litigeHistorics = $litige->getLitigeHistorics();

                    $litigeData[] = $buyersMailsStr;
                    if ($litigeHistorics->count() === 0) {
                        $litigeData[] = '';
                        $litigeData[] = '';
                        $litigeData[] = '';

                        $data[] = $litigeData;
                    } else {
                        $historic = $litigeHistorics->last();
                        $data[] = array_merge(
                            $litigeData,
                            [
                                ($historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : ''),
                                $CSVExportService->escapeCSV($historic->getUser() ? $historic->getUser()->getUsername() : ''),
                                $CSVExportService->escapeCSV($historic->getComment()),
                            ]
                        );
                    }
                }
			}

			return new JsonResponse($data);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

    /**
     * @Route("/supprime-pj-litige", name="litige_delete_attachement", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
	public function deleteAttachementLitige(Request $request,
                                            EntityManagerInterface $entityManager)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$litigeId = (int)$data['litigeId'];
			$attachmentRepository = $entityManager->getRepository(Attachment::class);

			$attachements = $attachmentRepository->findOneByFileNameAndLitigeId($data['pjName'], $litigeId);
			if (!empty($attachements)) {
			    foreach ($attachements as $attachement) {
                    $entityManager->remove($attachement);
                }
				$entityManager->flush();
				$response = true;
			} else {
				$response = false;
			}

			return new JsonResponse($response);
		} else {
			throw new NotFoundHttpException('404');
		}
	}

    /**
     * @Route("/histo/{litige}", name="histo_litige_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param Litige $litige
     * @return Response
     */
	public function apiHistoricLitige(Request $request, Litige $litige): Response
    {
        if ($request->isXmlHttpRequest()) {
            $rows = [];
                $idLitige = $litige->getId();
                $litigeHisto = $this->litigeHistoricRepository->findByLitige($idLitige);
                foreach ($litigeHisto as $histo)
                {
                    $rows[] = [
                        'user' => $histo->getUser() ? $histo->getUser()->getUsername() : '',
                        'date' => $histo->getDate() ? $histo->getDate()->format('d/m/Y H:i') : '',
                        'commentaire' => nl2br($histo->getComment()),
                    ];
                }
            $data['data'] = $rows;

            return new JsonResponse($data);

        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/add_Comment/{litige}", name="add_comment", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param Litige $litige
     * @return Response
     * @throws Exception
     */
    public function addComment(Request $request, Litige $litige): Response
    {
        if ($request->isXmlHttpRequest() && $data = (json_decode($request->getContent(), true) ?? [])) {
            $em = $this->getDoctrine()->getManager();

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $litigeHisto = new LitigeHistoric();
            $litigeHisto
                ->setLitige($litige)
                ->setUser($currentUser)
                ->setDate(new DateTime('now'))
                ->setComment($data);
            $em->persist($litigeHisto);
            $em->flush();

            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }

    /**
     * @Route("/modifier", name="litige_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @return Response
     */
	public function editLitige(Request $request): Response
	{
        if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $post = $request->request;
        $isArrivage = $post->get('isArrivage');

        $controller = $isArrivage ? 'App\Controller\ArrivageController' : 'App\Controller\ReceptionController';

        return $this->forward($controller . '::editLitige', [
            'request' => $request
        ]);
	}

    /**
     * @Route("/supprimer", name="litige_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $litigeRepository = $entityManager->getRepository(Litige::class);
            $dispute = $litigeRepository->find($data['litige']);

            $articlesInDispute = $dispute->getArticles()->toArray();
            $controller = !empty($articlesInDispute) ? 'App\Controller\ReceptionController' : 'App\Controller\ArrivageController';


            return $this->forward($controller . '::deleteLitige', [
                'request' => $request
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_litige", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     */
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() ) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = json_decode($request->getContent(), true);
            $champs = array_keys($data);
            $user = $this->getUser();
            /** @var $user Utilisateur */
            $champs[] = "actions";
            $user->setColumnsVisibleForLitige($champs);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/colonne-visible", name="get_column_visible_for_litige", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     *
     * @return Response
     */
    public function getColumnVisible(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
            return $this->redirectToRoute('access_denied');
        }

        /** @var Utilisateur $user */
        $user = $this->getUser();

        return new JsonResponse($user->getColumnsVisibleForLitige());
    }

    /**
     * @Route("/article/{litige}", name="article_litige_api", options={"expose"=true}, methods="POST|GET", condition="request.isXmlHttpRequest()")
     * @param Litige $litige
     * @return Response
     */
    public function articlesByLitige(Litige $litige): Response
    {
        $rows = [];
        $articlesInLitige = $litige->getFiveLastArticles();

        foreach ($articlesInLitige as $article) {
            $rows[] = [
                'codeArticle' => $article ? $article->getBarCode() : '',
                'status' => $article->getStatut() ? $article->getStatut()->getNom() : '',
                'libelle' => $article->getLabel() ? $article->getLabel() : '',
                'reference' => $article->getReference() ? $article->getReference() : '',
                'quantity' => $article ? $article->getQuantite() : 'non renseigné',
            ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/autocomplete", name="get_dispute_number", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getDisputeNumberAutoComplete(Request $request,
                                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $utilisateurRepository = $entityManager->getRepository(Litige::class);
            $user = $utilisateurRepository->getIdAndDisputeNumberBySearch($search);
            return new JsonResponse(['results' => $user]);
        }
        throw new NotFoundHttpException("404");
    }
}
