<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\DisputeHistoryRecord;

use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\CSVExportService;
use App\Service\LitigeService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/litige")
 */
class LitigeController extends AbstractController
{

	/**
	 * @var UserService
	 */
	private $userService;

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
     * @param TranslatorInterface $translator
     */
	public function __construct(LitigeService $litigeService,
                                UserService $userService,
                                TranslatorInterface $translator) {
		$this->userService = $userService;
		$this->litigeService = $litigeService;
        $this->translator = $translator;
	}

    /**
     * @Route("/liste", name="litige_index", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI})
     */
    public function index(LitigeService $litigeService,
                          EntityManagerInterface $entityManager)
    {

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('litige/index.html.twig',[
            'statuts' => $statutRepository->findByCategorieNames([CategorieStatut::LITIGE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $transporteurRepository->findAllSorted(),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
			'litigeOrigins' => $litigeService->getLitigeOrigin(),
            'fields' => $litigeService->getColumnVisibleConfig($user)
		]);
    }

    /**
     * @Route("/api", name="litige_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request) {

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $this->litigeService->getDataForDatatable($request->request);
        $columnVisible = $user->getColumnsVisibleForLitige();
        $data['visible'] = $columnVisible;
        return new JsonResponse($data);
	}

    /**
     * @Route("/litiges_infos", name="get_litiges_for_csv", options={"expose"=true}, methods={"GET","POST"})
     *
     * @param Request $request
     * @param LitigeService $litigeService
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     *
     * @return Response
     */
    public function getLitigesIntels(Request $request,
                                     LitigeService $litigeService,
                                     EntityManagerInterface $entityManager,
                                     CSVExportService $CSVExportService): Response
    {


        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

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

        $nowStr = date("d-m-Y_H-i");

        return $CSVExportService->streamResponse(function ($output) use ($litigeService, $entityManager, $dateTimeMin, $dateTimeMax) {

            $litigeRepository = $entityManager->getRepository(Litige::class);

            $arrivalDisputes = $litigeRepository->iterateArrivalsLitigeByDates($dateTimeMin, $dateTimeMax);
            /** @var Litige $dispute */
            foreach ($arrivalDisputes as $dispute) {
                $litigeService->putDisputeLine(LitigeService::PUT_LINE_ARRIVAL, $output, $litigeRepository, $dispute);
            }

            $receptionDisputes = $litigeRepository->iterateReceptionLitigeByDates($dateTimeMin, $dateTimeMax);
            /** @var Litige $dispute */
            foreach ($receptionDisputes as $dispute) {
                $litigeService->putDisputeLine(LitigeService::PUT_LINE_RECEPTION, $output, $litigeRepository, $dispute);
            }
        }, "Export-Litiges" . $nowStr . ".csv", $headers);
    }

    /**
     * @Route("/supprime-pj-litige", name="litige_delete_attachement", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function deleteAttachementLitige(Request $request,
                                            EntityManagerInterface $entityManager)
	{
		if ($data = json_decode($request->getContent(), true)) {
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
			throw new BadRequestHttpException();
		}
	}

    /**
     * @Route("/histo/{dispute}", name="histo_litige_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function apiHistoricLitige(EntityManagerInterface $entityManager,
                                      Litige                 $dispute): Response
    {
        $rows = [];
        $disputeHistoryRecordRepository = $entityManager->getRepository(DisputeHistoryRecord::class);
        $disputeHistory = $disputeHistoryRecordRepository->findBy(['dispute' => $dispute]);

        foreach ($disputeHistory as $record)
        {
            $rows[] = [
                'user' => $record->getUser() ? $record->getUser()->getUsername() : '',
                'date' => $record->getDate() ? $record->getDate()->format('d/m/Y H:i') : '',
                'commentaire' => nl2br($record->getComment()),
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/add_Comment/{litige}", name="add_comment", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function addComment(Request $request, Litige $litige): Response
    {
        if ($data = (json_decode($request->getContent(), true) ?? [])) {
            $em = $this->getDoctrine()->getManager();

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $historyRecord = new DisputeHistoryRecord();
            $historyRecord
                ->setDispute($litige)
                ->setUser($currentUser)
                ->setDate(new DateTime('now'))
                ->setComment($data);
            $em->persist($historyRecord);
            $em->flush();

            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }

    /**
     * @Route("/modifier", name="litige_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::EDIT})
     */
	public function editLitige(Request $request): Response
	{
        $post = $request->request;
        $isArrivage = $post->get('isArrivage');

        $controller = $isArrivage ? 'App\Controller\ArrivageController' : 'App\Controller\ReceptionController';

        return $this->forward($controller . '::editLitige', [
            'request' => $request
        ]);
	}

    /**
     * @Route("/supprimer", name="litige_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $dispute = $litigeRepository->find($data['litige']);

            $articlesInDispute = $dispute->getArticles()->toArray();
            $controller = !empty($articlesInDispute) ? 'App\Controller\ReceptionController' : 'App\Controller\ArrivageController';


            return $this->forward($controller . '::deleteLitige', [
                'request' => $request
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_litige", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI}, mode=HasPermission::IN_JSON)
     */
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);
        $champs = array_keys($data);
        $user = $this->getUser();
        /** @var $user Utilisateur */
        $champs[] = "actions";
        $user->setColumnsVisibleForLitige($champs);
        $entityManager->flush();

        return new JsonResponse();
    }

    /**
     * @Route("/colonne-visible", name="get_column_visible_for_litige", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI}, mode=HasPermission::IN_JSON)
     */
    public function getColumnVisible(): Response
    {
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
     * @Route("/autocomplete", name="get_dispute_number", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getDisputeNumberAutoComplete(Request $request,
                                                 EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $utilisateurRepository = $entityManager->getRepository(Litige::class);
        $user = $utilisateurRepository->getIdAndDisputeNumberBySearch($search);
        return new JsonResponse([
            'results' => $user
        ]);
    }
}
