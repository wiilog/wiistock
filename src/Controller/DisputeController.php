<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispute;
use App\Entity\Menu;
use App\Entity\DisputeHistoryRecord;

use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\DisputeService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/litige")
 */
class DisputeController extends AbstractController
{

    /**
     * @Route("/liste", name="dispute_index", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI})
     */
    public function index(DisputeService         $disputeService,
                          EntityManagerInterface $entityManager)
    {

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('litige/index.html.twig',[
            'statuts' => $statutRepository->findByCategorieNames([CategorieStatut::DISPUTE_ARR, CategorieStatut::LITIGE_RECEPT]),
            'carriers' => $transporteurRepository->findAllSorted(),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]),
			'litigeOrigins' => $disputeService->getLitigeOrigin(),
            'fields' => $disputeService->getColumnVisibleConfig($user)
		]);
    }

    /**
     * @Route("/api", name="litige_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DISPLAY_LITI}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        DisputeService $disputeService) {

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $disputeService->getDataForDatatable($request->request);
        $columnVisible = $user->getColumnsVisibleForLitige();
        $data['visible'] = $columnVisible;
        return new JsonResponse($data);
	}

    /**
     * @Route("/csv", name="export_csv_dispute", options={"expose"=true}, methods={"GET","POST"})
     */
    public function exportCSVDispute(Request                $request,
                                     DisputeService         $disputeService,
                                     EntityManagerInterface $entityManager,
                                     CSVExportService       $CSVExportService): Response
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

        return $CSVExportService->streamResponse(function ($output) use ($disputeService, $entityManager, $dateTimeMin, $dateTimeMax) {

            $disputeRepository = $entityManager->getRepository(Dispute::class);

            $arrivalDisputes = $disputeRepository->iterateArrivalDisputesByDates($dateTimeMin, $dateTimeMax);
            /** @var Dispute $dispute */
            foreach ($arrivalDisputes as $dispute) {
                $disputeService->putDisputeLine(DisputeService::PUT_LINE_ARRIVAL, $output, $disputeRepository, $dispute);
            }

            $receptionDisputes = $disputeRepository->iterateReceptionDisputesByDates($dateTimeMin, $dateTimeMax);
            /** @var Dispute $dispute */
            foreach ($receptionDisputes as $dispute) {
                $disputeService->putDisputeLine(DisputeService::PUT_LINE_RECEPTION, $output, $disputeRepository, $dispute);
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
			$disputeId = (int)$data['disputeId'];
			$attachmentRepository = $entityManager->getRepository(Attachment::class);

			$attachements = $attachmentRepository->findOneByFileNameAndDisputeId($data['pjName'], $disputeId);
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
     * @Route("/histo/{dispute}", name="histo_dispute_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
	public function apiHistoricLitige(EntityManagerInterface $entityManager,
                                      Dispute                $dispute): Response
    {
        $rows = [];
        $disputeHistoryRecordRepository = $entityManager->getRepository(DisputeHistoryRecord::class);
        $disputeHistory = $disputeHistoryRecordRepository->findBy(['dispute' => $dispute]);

        foreach ($disputeHistory as $record)
        {
            $rows[] = [
                'user' => FormatHelper::user($record->getUser()),
                'date' => FormatHelper::datetime($record->getDate()),
                'commentaire' => nl2br($record->getComment()),
                'status' => FormatHelper::status($record->getStatusLabel()),
                'type' => FormatHelper::type($record->getTypeLabel())
            ];
        }
        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/add_Comment/{dispute}", name="add_comment", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function addComment(Request $request,
                               DisputeService $disputeService,
                               Dispute $dispute): Response
    {
        if ($data = (json_decode($request->getContent(), true) ?? [])) {
            $em = $this->getDoctrine()->getManager();

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $historyRecord = $disputeService->createDisputeHistoryRecord(
                $dispute,
                $currentUser,
                [$data]
            );

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
            $disputeRepository = $entityManager->getRepository(Dispute::class);
            $dispute = $disputeRepository->find($data['litige']);

            $articlesInDispute = $dispute->getArticles()->toArray();
            $controller = !empty($articlesInDispute) ? ReceptionController::class : ArrivageController::class;

            return $this->forward("$controller::deleteDispute", [
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
     * @Route("/article/{dispute}", name="article_dispute_api", options={"expose"=true}, methods="POST|GET", condition="request.isXmlHttpRequest()")
     */
    public function articlesByLitige(Dispute $dispute): Response
    {
        $rows = [];
        $articlesInLitige = $dispute->getFiveLastArticles();

        foreach ($articlesInLitige as $article) {
            $rows[] = [
                'codeArticle' => $article ? $article->getBarCode() : '',
                'status' => FormatHelper::status($article->getStatut()),
                'libelle' => $article->getLabel() ?: '',
                'reference' => $article->getReference() ?: '',
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

        $utilisateurRepository = $entityManager->getRepository(Dispute::class);
        $user = $utilisateurRepository->getIdAndDisputeNumberBySearch($search);
        return new JsonResponse([
            'results' => $user
        ]);
    }
}
