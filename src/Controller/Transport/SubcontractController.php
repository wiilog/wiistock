<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\StatusHistoryService;
use App\Service\TranslationService;
use App\Service\Transport\TransportHistoryService;
use App\Service\Transport\TransportService;
use DateTime;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\StringHelper;


#[Route("transport/sous-traitance")]
class SubcontractController extends AbstractController
{

    public const VALIDATE = 'validate';
    public const SUBCONTRACT = 'subcontract';

    #[Route("/liste", name: "transport_subcontract_index", methods: "GET")]
    public function index(EntityManagerInterface $em): Response
    {
        $statusRepository = $em->getRepository(Statut::class);
        $typesRepository = $em->getRepository(Type::class);

        $statuses =  [
            TransportRequest::STATUS_SUBCONTRACTED,
            TransportRequest::STATUS_ONGOING,
            TransportRequest::STATUS_FINISHED,
            TransportRequest::STATUS_NOT_DELIVERED
        ];

        $statuses = $statusRepository->findByCategoryNameAndStatusCodes(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, $statuses);
        foreach($statuses as $index => $status){
            if($status->getCode() === TransportRequest::STATUS_SUBCONTRACTED){
                unset($statuses[$index]);
                array_unshift($statuses, $status);
            }
        }

        return $this->render('transport/subcontract/index.html.twig', [
            'statuts' => $statuses,
            'types' => $typesRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT
            ]),
        ]);
    }

    #[Route('/api', name: 'transport_subcontract_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response
    {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $transportRequestRepository = $manager->getRepository(TransportRequest::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SUBCONTRACT_ORDERS, $this->getUser());

        $awaitingValidationResult = $transportRequestRepository->findAwaitingValidation();

        $subcontractOrderResult = $transportRequestRepository->findByParamAndFilters(
            $request->request,
            $filters,
            [[
                "field" => "subcontracted",
                "value" => true
            ]]
        );

        $transportRequests = [];
        if (!empty($awaitingValidationResult)) {
            $transportRequests["A valider"] = $awaitingValidationResult;
        }

        foreach ($subcontractOrderResult["data"] as $requestDown) {
            $transportRequests[$requestDown->getExpectedAt()->format("dmY")][] = $requestDown;
        }

        $rows = [];
        $class = "";

        foreach ($transportRequests as $date => $requests) {
            if ($date !== "A valider") {
                $class = "pt-3";
                $date = DateTime::createFromFormat("dmY", $date);
                $date = FormatHelper::longDate($date);
            }

            $row = "<div class='transport-list-date px-1 pb-2 {$class}'>$date</div>";

            $rows[] = [
                "content" => $row,
            ];
            $currentRow = [];

            foreach ($requests as $request) {
                if ($date !== "A valider") {
                    $currentRow[] = $this->renderView("transport/subcontract/list_card.html.twig", [
                        "prefix" => TransportRequest::NUMBER_PREFIX,
                        "request" => $request,
                        "historyType" => TransportHistoryService::TYPE_FINISHED,
                    ]);
                } else {
                    $currentRow[] = $this->renderView("transport/subcontract/card_to_validate.html.twig", [
                        "prefix" => TransportRequest::NUMBER_PREFIX,
                        "request" => $request,
                    ]);
                }
            }

            if ($currentRow) {
                $row = "<div class='transport-row row no-gutters'>" . join($currentRow) . "</div>";
                $rows[] = [
                    "content" => $row,
                ];
            }
        }

        return $this->json([
            "data" => $rows,
            "recordsTotal" => count($awaitingValidationResult) + $subcontractOrderResult["total"],
            "recordsFiltered" => count($awaitingValidationResult) + $subcontractOrderResult["count"],
        ]);
    }

    #[Route('/treat', name: 'transport_request_treat', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function acceptTransportRequest(Request                 $request,
                                           StatusHistoryService    $statusHistoryService,
                                           TransportHistoryService $transportHistoryService,
                                           EntityManagerInterface  $entityManager,
                                           TransportService        $transportService): Response {
        $transportRequestRepository = $entityManager->getRepository(TransportRequest::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $requestId = $request->query->getInt('requestId');
        $buttonType = $request->query->get('buttonType');
        $transportRequest = $transportRequestRepository->findOneBy(['id' => $requestId]);

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $transportOrder = $transportRequest->getOrder();

        if($buttonType === self::VALIDATE) {
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(
                $transportRequest instanceof TransportCollectRequest
                    ? CategorieStatut::TRANSPORT_REQUEST_COLLECT
                    : CategorieStatut::TRANSPORT_REQUEST_DELIVERY,
                $transportRequest instanceof TransportCollectRequest
                    ? TransportRequest::STATUS_AWAITING_PLANNING
                    : (($transportOrder && !$transportOrder->getPacks()->isEmpty())
                        ? TransportRequest::STATUS_TO_DELIVER
                        : TransportRequest::STATUS_TO_PREPARE)
                );
            $transportHistoryType = TransportHistoryService::TYPE_ACCEPTED;
        } else {
            $status = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::TRANSPORT_REQUEST_DELIVERY,
                TransportRequest::STATUS_SUBCONTRACTED
            );
            $transportHistoryType = TransportHistoryService::TYPE_SUBCONTRACTED;

            $transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_NO_MONITORING, [
                'message' => $settingRepository->getOneParamByLabel(Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
            ]);
        }

        $statusHistory = $statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
        $transportHistoryService->persistTransportHistory($entityManager, $transportRequest, $transportHistoryType, [
            'history' => $statusHistory,
        ]);

        if (!$transportOrder) {
            $transportService->persistTransportOrder($entityManager, $transportRequest, $loggedUser);
        }
        else {
            $transportService->updateOrderInitialStatus($entityManager, $transportRequest, $transportOrder, $loggedUser);
        }

        $entityManager->flush();

        $json = $this->redirectToRoute('transport_subcontract_index');
        return new JsonResponse($json);
    }

    #[Route('/api-modifier', name: 'subcontract_request_edit_api', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::EDIT_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $transportRequestRepository = $entityManager->getRepository(TransportRequest::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $transportRequest = $transportRequestRepository->find($data['id']);

            $transportOrder = $transportRequest->getOrder();

            $statusesForSelect = match ($transportRequest->getStatus()?->getCode()) {
                TransportRequest::STATUS_SUBCONTRACTED => [
                    TransportRequest::STATUS_SUBCONTRACTED,
                    TransportRequest::STATUS_ONGOING,
                    TransportRequest::STATUS_FINISHED,
                    TransportRequest::STATUS_NOT_DELIVERED
                ],
                TransportRequest::STATUS_ONGOING => [
                    TransportRequest::STATUS_ONGOING,
                    TransportRequest::STATUS_FINISHED,
                    TransportRequest::STATUS_NOT_DELIVERED
                ],
                default => [
                    TransportRequest::STATUS_FINISHED,
                    TransportRequest::STATUS_NOT_DELIVERED
                ]
            };

            $json = $this->renderView('transport/subcontract/modalEditSubcontractedRequestContent.html.twig', [
                'transportRequest' => $transportRequest,
                'transportOrder' => $transportOrder,
                'subcontractStatuses' => $statutRepository->findByCategoryNameAndStatusCodes(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, $statusesForSelect),
                'attachments' => $transportOrder->getAttachments()
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/modifier', name: 'subcontract_request_edit', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::EDIT_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function edit(EntityManagerInterface  $entityManager,
                         Request                 $request,
                         TransportService        $transportService,
                         AttachmentService       $attachmentService,
                         TransportHistoryService $transportHistoryService,
                         TranslationService      $translation): ?Response {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transportRequestRepository = $entityManager->getRepository(TransportRequest::class);
        $data = $request->request;
        $transportRequest = $transportRequestRepository->find($data->get('id'));

        $transportOrder = $transportRequest->getOrder();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $startedAt = FormatHelper::parseDatetime($data->get('delivery-start-date'));
        $treatedAt = FormatHelper::parseDatetime($data->get('delivery-end-date'));
        $statusRequest = $statutRepository->find($data->get('status'));

        if ( ($statusRequest == TransportRequest::STATUS_FINISHED || $transportRequest == TransportRequest::STATUS_CANCELLED) && $treatedAt && $startedAt && $startedAt > $treatedAt) {
            return $this->json([
                "success" => false,
                "errors" => [
                    "delivery-start-date" => "La date de début de " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . " ne peut être supérieure à la date de fin de " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)),
                    "delivery-end-date" => "La date de fin de " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . " ne peut être inférieure à la date de début de " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)),
                ]
            ]);
        }

        $statusOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, match($statusRequest->getCode()) {
            TransportRequest::STATUS_ONGOING => TransportOrder::STATUS_ONGOING,
            TransportRequest::STATUS_SUBCONTRACTED => TransportOrder::STATUS_SUBCONTRACTED,
            TransportRequest::STATUS_FINISHED => TransportOrder::STATUS_FINISHED,
            TransportRequest::STATUS_NOT_DELIVERED => TransportOrder::STATUS_NOT_DELIVERED,
            default => throw new RuntimeException("Unhandled status code"),
        });

        $oldRequestStatus = $transportRequest->getStatus();
        $oldStartedDate = $transportOrder->getStartedAt();
        $oldTreatedDate = $transportOrder->getTreatedAt();
        $oldComment = $transportOrder->getComment();

        $transportOrder
            ->setSubcontractor($data->get('subcontractor'))
            ->setRegistrationNumber($data->get('registrationNumber'))
            ->setStartedAt($startedAt)
            ->setTreatedAt($treatedAt);

        $comment = $data->get('commentaire');
        if (strip_tags($comment)) {
            $transportOrder->setComment(StringHelper::cleanedComment($comment));
        }

        $addedAttachments = $attachmentService->manageAttachments($entityManager, $transportOrder, $request->files);

        $date = in_array($statusRequest->getCode(), [TransportRequest::STATUS_FINISHED, TransportRequest::STATUS_NOT_DELIVERED])
            ? $treatedAt
            : $startedAt;

        $ongoingStatusRequest = $statutRepository->findOneByCategorieNameAndStatutCode($statusRequest->getCategorie()->getNom(), TransportRequest::STATUS_ONGOING);
        $ongoingStatusOrder = $statutRepository->findOneByCategorieNameAndStatutCode($statusOrder->getCategorie()->getNom(), TransportOrder::STATUS_ONGOING);

        if ($oldRequestStatus?->getId() !== $statusRequest->getId()) {
            // if we jump ongoing status and we set directly a end status
            // we create ongoing status history
            // call before current status for sort in the timeline
            if ($oldRequestStatus?->getCode() !== TransportRequest::STATUS_ONGOING
                && in_array($statusRequest->getCode(), [TransportRequest::STATUS_FINISHED, TransportRequest::STATUS_NOT_DELIVERED])) {

                $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportRequest, $ongoingStatusRequest, $startedAt, false);
                $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportOrder, $ongoingStatusOrder, $startedAt, false);
            }

            $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportRequest, $statusRequest, $date, true);
            $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportOrder, $statusOrder, $date, true);

        }

        // if we change treatedAt without changing status
        if ($oldTreatedDate && $oldTreatedDate != $treatedAt) {
            $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportRequest, $transportRequest->getStatus(), $treatedAt, false);
            $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportOrder, $transportOrder->getStatus(), $treatedAt, false);
        }

        // if we change startedAt without changing status
        if ($oldStartedDate && $oldStartedDate != $startedAt) {
            $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportRequest, $ongoingStatusRequest, $startedAt, false);
            $transportService->updateSubcontractedRequestStatus($entityManager, $loggedUser, $transportOrder, $ongoingStatusOrder, $startedAt, false);
        }

        if ($oldComment !== $transportOrder->getComment()) {
            $transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_ADD_COMMENT, [
                'user' => $loggedUser,
                'comment' => $transportOrder->getComment()
            ]);
        }

        if (!empty($addedAttachments)) {
            $transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_ADD_ATTACHMENT, [
                'user' => $loggedUser,
                'attachments' => $addedAttachments
            ]);
        }

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La sous-traitance a bien été mise à jour",
        ]);
    }
}
