<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\PurchaseRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Iterator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * @Route("/achat/demande")
 */
class PurchaseRequestController extends AbstractController
{
    /**
     * @Route("/liste", name="purchase_request_index")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('purchase_request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST),
        ]);
    }

    /**
     * @Route("/api", name="purchase_request_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
     */
    public function api(Request $request,
                        PurchaseRequestService $purchaseRequestService): Response {
        $data = $purchaseRequestService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="purchase_request_show", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS})
     */
    public function show(PurchaseRequest $request,
                         PurchaseRequestService $purchaseRequestService): Response {
        $status = $request->getStatus();
        return $this->render('purchase_request/show.html.twig', [
            'request' => $request,
            'modifiable' => $status && $status->isDraft(),
            'detailsConfig' => $purchaseRequestService->createHeaderDetailsConfig($request)
        ]);
    }

    /**
     * @Route("/csv", name="purchase_request_export",options={"expose"=true}, methods="GET|POST" )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return Response
     * @throws Exception
     */
    public function export(Request $request,
                           EntityManagerInterface $entityManager,
                           PurchaseRequestService $purchaseRequestService,
                           CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("Y-m-d H:i:s", $dateMin . " 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("Y-m-d H:i:s", $dateMax . " 23:59:59");

        if(isset($dateTimeMin, $dateTimeMax)) {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            $requests = $purchaseRequestRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            $lines = $purchaseRequestLineRepository->iterateByPurchaseRequest($dateTimeMin, $dateTimeMax);

            $header = [
                "Numéro demande",
                "Statut",
                "Demandeur",
                "Acheteur",
                "Date de création",
                "Date de validation",
                "Date de prise en compte",
                "Commentaire",
                "Référence",
                "Code barre",
                "Libellé"
            ];

            return $CSVExportService->streamResponse(
                function ($output) use ($requests, $lines, $purchaseRequestService, $CSVExportService) {
                    foreach ($requests as $request) {
                        $lineAddedForRequest = false;
                        if ($lines instanceof Iterator && $lines->valid()) {
                            $line = $lines->current();
                            while ($lines->valid()
                                && $line
                                && $line['purchaseRequestId'] === $request['id']) {
                                $purchaseRequestService->putPurchaseRequestLine($output, $CSVExportService, $request, $line);
                                $lines->next();
                                $line = $lines->current();

                                if (!$lineAddedForRequest) {
                                    $lineAddedForRequest = true;
                                }
                            }
                        }

                        if (!$lineAddedForRequest) {
                            $purchaseRequestService->putPurchaseRequestLine($output, $CSVExportService, $request);
                        }
                    }
                },
                "export_demande_achat" . $now->format("d_m_Y") . ".csv",
                $header
            );
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="purchase_request_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $requestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $purchaseRequest = $requestRepository->find($data['request']);

            $status = $purchaseRequest->getStatus();
            if (!$status ||
                ($status->isDraft() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_DRAFT_PURCHASE_REQUEST)) ||
                ($status->isNotTreated() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_ONGOING_PURCHASE_REQUESTS)) ||
                ($status->isInProgress() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_ONGOING_PURCHASE_REQUESTS)) ||
                ($status->isTreated() && !$userService->hasRightFunction(Menu::DEM, Action::DELETE_TREATED_PURCHASE_REQUESTS))) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Vous n'avez pas le droit de supprimer cette demande"
                ]);
            }

            $entityManager->remove($purchaseRequest);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('purchase_request_index'),
                'msg' => "La demande d'achat a bien été supprimée"
            ]);

        }
        throw new BadRequestHttpException();

    }
    /**
     * @Route("/creer", name="purchase_request_new", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::CREATE_PURCHASE_REQUESTS})
     */
    public function new(PurchaseRequestService $purchaseRequestService,
                        EntityManagerInterface $entityManager): Response
    {

        /** @var Utilisateur $requester */
        $requester = $this->getUser();

        $status = $entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutState(CategorieStatut::PURCHASE_REQUEST, Statut::DRAFT);
        if (!$status) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Aucun statut brouillon crée pour les demandes d\'achat. Veuillez en paramétrer un.'
            ]);
        }
        $purchaseRequest = $purchaseRequestService->createPurchaseRequest($entityManager, $status, $requester);

        $entityManager->persist($purchaseRequest);

        try {
            $entityManager->flush();
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Une autre demande d\'achat est en cours de création, veuillez réessayer.'
            ]);
        }
        $number = $purchaseRequest->getNumber();
        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été créée"
        ]);
    }

    /**
     * @Route("/api-modifier", name="purchase_request_api_edit", options={"expose"=true},  methods="GET|POST")
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST})
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $statusRepository = $entityManager->getRepository(Statut::class);
            $statuses = $statusRepository->findByCategoryAndStates(CategorieStatut::PURCHASE_REQUEST, [Statut::IN_PROGRESS]);
            $purchaseRequest = $purchaseRequestRepository->find($data['id']);
            $json = $this->renderView('purchase_request/edit_content_modal.html.twig', [
                'purchaseRequest' => $purchaseRequest,
                'statuses' => $statuses
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="purchase_request_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::EDIT_DRAFT_PURCHASE_REQUEST})
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         AttachmentService $attachmentService): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $post = $request->request;

        $purchaseRequest = $purchaseRequestRepository->find($post->get('id'));

        /** @var Utilisateur $requester */
        $requester = $post->has('requester') ? $userRepository->find($post->get('requester')) : $purchaseRequest->getRequester();
        $comment = $post->get('comment') ?: '';
        $status = $statusRepository->find($post->get('status'));

        $purchaseRequest
            ->setComment($comment)
            ->setStatus($status)
            ->setRequester($requester);

        $purchaseRequest->removeIfNotIn($data['files'] ?? []);
        $attachmentService->manageAttachments($entityManager, $purchaseRequest, $request->files);

        $entityManager->flush();

        $number = $purchaseRequest->getNumber();

        return $this->json([
            'success' => true,
            'msg' => "La demande d'achat <strong>${number}</strong> a bien été modifiée"
        ]);
    }
}
