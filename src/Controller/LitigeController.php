<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Colis;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\LitigeHistoric;

use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\LitigeHistoricRepository;
use App\Repository\LitigeRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\TransporteurRepository;

use App\Service\CSVExportService;
use App\Service\LitigeService;
use App\Service\SpecificService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
	 * @var LitigeRepository
	 */
	private $litigeRepository;
	/**
	 * @var UserService
	 */
	private $userService;

    /**
     * @var LitigeHistoricRepository
     */
    private $litigeHistoricRepository;

	/**
	 * @var PieceJointeRepository
	 */
	private $pieceJointeRepository;

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
     * @param PieceJointeRepository $pieceJointeRepository
     * @param UserService $userService ;
     * @param LitigeRepository $litigeRepository
     * @param TransporteurRepository $transporteurRepository
     * @param TranslatorInterface $translator
     * @param LitigeHistoricRepository $litigeHistoricRepository
     */
	public function __construct(LitigeService $litigeService,
                                PieceJointeRepository $pieceJointeRepository,
                                UserService $userService,
                                LitigeRepository $litigeRepository,
                                TransporteurRepository $transporteurRepository,
                                TranslatorInterface $translator,
                                LitigeHistoricRepository $litigeHistoricRepository)
	{
		$this->transporteurRepository = $transporteurRepository;
		$this->litigeRepository = $litigeRepository;
		$this->userService = $userService;
		$this->litigeHistoricRepository = $litigeHistoricRepository;
		$this->pieceJointeRepository = $pieceJointeRepository;
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

        $user = $this->getUser();
        $fieldsInTab = [
            ["key" => 'type', 'label' => 'Type'],
            ["key" => 'arrivalNumber', 'label' => $this->translator->trans('arrivage.n° d\'arrivage')],
            ["key" => 'receptionNumber', 'label' => $this->translator->trans('réception.n° de réception')],
            ["key" => 'buyers', 'label' => 'Acheteur'],
            ["key" => 'numCommandeBl', 'label' => 'N° commande / BL'],
            ["key" => 'command', 'label' => 'N° ligne'],
            ["key" => 'provider', 'label' => 'Fournisseur'],
            ["key" => 'references', 'label' => 'Référence'],
            ["key" => 'lastHistorique', 'label' => 'Dernier historique'],
            ["key" => 'creationDate', 'label' => 'Créé le'],
            ["key" => 'updateDate', 'label' => 'Modifié le'],
            ["key" => 'status', 'label' => 'Statut'],
        ];
        $fieldsCl =[];
        $champs = array_merge($fieldsInTab,$fieldsCl);


        return $this->render('litige/index.html.twig',[
            'statuts' => $statutRepository->findByCategorieNames([CategorieStatut::LITIGE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $this->transporteurRepository->findAllSorted(),
            'types' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
			'litigeOrigins' => $litigeService->getLitigeOrigin(),
			'isCollins' => $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_COLLINS),
            'champs' => $champs,
            'columnsVisibles' => $user->getColumnsVisibleForLitige(),
		]);
    }

	/**
	 * @Route("/api", name="litige_api", options={"expose"=true}, methods="GET|POST")
	 */
    public function api(Request $request) {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
				return $this->redirectToRoute('access_denied');
			}
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
	 * @param CSVExportService $CSVExportService
	 *
	 * @return Response
	 */
	public function getLitigesIntels(Request $request,
                                     CSVExportService $CSVExportService): Response
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $arrivalLitiges = $this->litigeRepository->findArrivalsLitigeByDates($dateTimeMin, $dateTimeMax);

			$headers = [
			    'Type',
                'Statut',
                'Date création',
                'Date modification',
                'Colis / Réferences',
                'Ordre arrivage / réception',
				'N° Commande / BL',
                'Fournisseur',
                'N° ligne',
            	'Date commentaire',
            	'Utilisateur',
            	'Commentaire'
            ];

			$data = [$headers];

			/** @var Litige $litige */
            foreach ($arrivalLitiges as $litige) {
                $litigeData = [];

                $litigeData[] = $CSVExportService->escapeCSV($litige->getType() ? $litige->getType()->getLabel() : '');
                $litigeData[] = $CSVExportService->escapeCSV($litige->getStatus() ? $litige->getStatus()->getNom() : '');
                $litigeData[] = $litige->getCreationDate() ? $litige->getCreationDate()->format('d/m/Y') : '';
                $litigeData[] = $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '';

                $articlesStr = implode(
                    ', ',
                    $litige
                        ->getColis()
                        ->map(function(Colis $colis) {
                            return $colis->getCode();
                        })
                        ->toArray()
                );
                $litigeData[] = $articlesStr;

                $colis = $litige->getColis();
                /** @var Arrivage $arrivage */
                $arrivage = ($colis->count() > 0 && $colis->first()->getArrivage())
                    ? $colis->first()->getArrivage()
                    : null;
                $litigeData[] = $arrivage ? $arrivage->getNumeroArrivage() : '';

                $numeroCommandeList = $arrivage ? $arrivage->getNumeroCommandeList() : [];
                $litigeData[] = implode(' / ', $numeroCommandeList); // N° de commandes

				$fournisseur = $arrivage ? $arrivage->getFournisseur() : null;
				$litigeData[] = $CSVExportService->escapeCSV(isset($fournisseur) ? $fournisseur->getNom() : '');

				$litigeData[] = ''; // N° de ligne

                $litigeHistorics = $litige->getLitigeHistorics();
                if ($litigeHistorics->count() == 0) {
                    $litigeData[] = '';
                    $litigeData[] = '';
                    $litigeData[] = '';

                    $data[] = $litigeData;
                }
                else {
                    foreach ($litigeHistorics as $historic) {
                        $data[] = array_merge(
                            $litigeData,
                            [
                                $historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : '',
                                $CSVExportService->escapeCSV($historic->getUser() ? $historic->getUser()->getUsername() : ''),
                                $CSVExportService->escapeCSV($historic->getComment())
                            ]
                        );
                    }
                }
			}

            $receptionLitiges = $this->litigeRepository->findReceptionLitigeByDates($dateTimeMin, $dateTimeMax);

			/** @var Litige $litige */
            foreach ($receptionLitiges as $litige) {
                $litigeData = [];

                $litigeData[] = $CSVExportService->escapeCSV($litige->getType() ? $litige->getType()->getLabel() : '');
                $litigeData[] = $CSVExportService->escapeCSV($litige->getStatus() ? $litige->getStatus()->getNom() : '');
                $litigeData[] = $litige->getCreationDate() ? $litige->getCreationDate()->format('d/m/Y') : '';
                $litigeData[] = $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y') : '';

                $referencesStr = implode(', ', $this->litigeRepository->getReferencesByLitigeId($litige->getId()));

                $litigeData[] = $referencesStr;

                $articles = $litige->getArticles();

                /** @var Article $firstArticle */
                $firstArticle = ($articles->count() > 0 ? $articles->first() : null);
                $receptionRefArticle = isset($firstArticle) ? $firstArticle->getReceptionReferenceArticle() : null;
                $reception = isset($receptionRefArticle) ? $receptionRefArticle->getReception() : null;

                $litigeData[] = (isset($reception) ? $reception->getNumeroReception() : '');

				$litigeData[] = (isset($reception) ? $reception->getReference() : null); // n° commande reception

				$fournisseur = (isset($reception) ? $reception->getFournisseur() : null);
				$litigeData[] = $CSVExportService->escapeCSV(isset($fournisseur) ? $fournisseur->getNom() : '');

				$litigeData[] = implode(', ', $this->litigeRepository->getCommandesByLitigeId($litige->getId()));

                $litigeHistorics = $litige->getLitigeHistorics();
                if ($litigeHistorics->count() == 0) {
                    $litigeData[] = '';
                    $litigeData[] = '';
                    $litigeData[] = '';

                    $data[] = $litigeData;
                }
                else {
                    foreach ($litigeHistorics as $historic) {
                        $data[] = array_merge(
                            $litigeData,
                            [
                                ($historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : ''),
                                $CSVExportService->escapeCSV($historic->getUser() ? $historic->getUser()->getUsername() : ''),
                                $CSVExportService->escapeCSV($historic->getComment())
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
	 */
	public function deleteAttachementLitige(Request $request)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$em = $this->getDoctrine()->getManager();

			$litigeId = (int)$data['litigeId'];

			$attachements = $this->pieceJointeRepository->findOneByFileNameAndLitigeId($data['pjName'], $litigeId);
			if (!empty($attachements)) {
			    foreach ($attachements as $attachement) {
                    $em->remove($attachement);
                }
				$em->flush();
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
            $litigeHisto = new LitigeHistoric();
            $litigeHisto
                ->setLitige($litige)
                ->setUser($this->getUser())
                ->setDate(new DateTime('now'))
                ->setComment($data);
            $em->persist($litigeHisto);
            $em->flush();

            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }

	/**
	 * @Route("/modifier", name="litige_edit",  options={"expose"=true}, methods="GET|POST")
	 */
	public function editLitige(Request $request): Response
	{
		if ($request->isXmlHttpRequest()) {
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
     * @Route("/colonne-visible", name="get_column_visible_for_litige", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     */
    public function getColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DISPLAY_LITI)) {
            return $this->redirectToRoute('access_denied');
        }
        $user = $this->getUser();     ;

        return new JsonResponse($user->getColumnsVisibleForLitige());
    }
}
