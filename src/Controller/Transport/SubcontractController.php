<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\StatusHistoryService;
use App\Service\Transport\TransportHistoryService;
use App\Service\Transport\TransportService;
use DateTime;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;



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

        return $this->render('transport/subcontract/index.html.twig', [
            'statuts' => $statusRepository->findByCategoryNameAndStatusCodes(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, [
                TransportRequest::STATUS_SUBCONTRACTED, TransportRequest::STATUS_ONGOING, TransportRequest::STATUS_FINISHED, TransportRequest::STATUS_NOT_DELIVERED
            ]),
            'types' => $typesRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT,
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
        foreach ($awaitingValidationResult as $requestUp) {
            $transportRequests["A valider"][] = $requestUp;
        }
        foreach ($subcontractOrderResult["data"] as $requestDown) {
            $transportRequests[$requestDown->getExpectedAt()->format("dmY")][] = $requestDown;
        }

        $rows = [];

        foreach ($transportRequests as $date => $requests) {
            if ($date !== "A valider") {
                $date = DateTime::createFromFormat("dmY", $date);
                $date = FormatHelper::longDate($date);
            }

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date</div>";

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
    public function acceptTransportRequest(Request          $request, EntityManagerInterface $manager,
                                           TransportService $transportService): ?Response
    {
        $transportRequestRepository = $manager->getRepository(TransportRequest::class);
        $statutRepository = $manager->getRepository(Statut::class);

        $requestId = $request->query->getInt('requestId');
        $buttonType = $request->query->get('buttonType');
        $transportRequest = $transportRequestRepository->findOneBy(['id' => $requestId]);
        $subcontracted = false;

        if($buttonType === self::VALIDATE) {
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(
                (
                $transportRequest instanceof TransportCollectRequest ?
                    CategorieStatut::TRANSPORT_REQUEST_COLLECT :
                    CategorieStatut::TRANSPORT_REQUEST_DELIVERY
                ),
                (
                $transportRequest instanceof TransportCollectRequest ?
                    TransportRequest::STATUS_AWAITING_PLANNING :
                    TransportRequest::STATUS_TO_PREPARE
                ));
        } else {
            $subcontracted = true;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::TRANSPORT_REQUEST_DELIVERY,
                TransportRequest::STATUS_SUBCONTRACTED);
        }

        $transportRequest->setStatus($statut);
        if ($transportRequest->getOrders()->isEmpty()) {
            $transportService->persistTransportOrder($manager, $transportRequest, $subcontracted);
        }

        $manager->flush();

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

            /** @var TransportOrder $transportOrder */
            $transportOrder = $transportRequest->getOrders()->last();

            $statusForSelect =
                [($transportRequest->getStatus()->getCode() == TransportRequest::STATUS_SUBCONTRACTED ? TransportRequest::STATUS_ONGOING : ""),
                TransportRequest::STATUS_FINISHED, TransportRequest::STATUS_NOT_DELIVERED];

            $json = $this->renderView('transport/subcontract/modalEditSubcontractedRequestContent.html.twig', [
                'transportRequest' => $transportRequest,
                'transportOrder' => $transportOrder,
                'subcontractTransportStatus' => $statutRepository->findByCategoryNameAndStatusCodes(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, $statusForSelect),
                'statutRequest' => $transportRequest->getStatus(),
                'attachments' => $transportOrder->getAttachments()

            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/modifier', name: 'subcontract_request_edit', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::EDIT_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function edit(EntityManagerInterface $entityManager,
                            Request $request,
                            TransportService $transportService,
                            AttachmentService $attachmentService,
                            StatusHistoryService $statusHistoryService): ?Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transportRequestRepository = $entityManager->getRepository(TransportRequest::class);
        $data = $request->request;
        $transportRequest = $transportRequestRepository->find($data->get('id'));

        /** @var TransportOrder $transportOrder */
        $transportOrder = $transportRequest->getOrders()->last();

        $startedAt = FormatHelper::parseDatetime($data->get('delivery-start-date'));
        $statutRequest = $statutRepository->find($data->get('status') !== "null" ? $data->get('status') : $data->get('statut'));

        $statutOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, match($statutRequest->getCode()) {
            TransportRequest::STATUS_ONGOING => TransportOrder::STATUS_ONGOING,
            TransportRequest::STATUS_SUBCONTRACTED => TransportOrder::STATUS_SUBCONTRACTED,
            TransportRequest::STATUS_FINISHED => TransportOrder::STATUS_FINISHED,
            TransportRequest::STATUS_NOT_DELIVERED => TransportOrder::STATUS_NOT_DELIVERED,
            default => throw new RuntimeException("Unhandled status code"),
        });

        $transportRequest->setStatus($statutRequest);
        $transportOrder->setStatus($statutOrder);

        $transportOrder->setSubcontractor($data->get('subcontractor'));
        $transportOrder->setRegistrationNumber($data->get('registrationNumber'));
        $transportOrder->setStartedAt($startedAt);
        $transportOrder->setComment($data->get('commentaire'));

        $attachmentService->manageAttachments($entityManager, $transportOrder, $request->files);

        $statusHistoryRequest = $statusHistoryService->updateStatus($entityManager, $transportRequest, $statutRequest);
        $statusHistoryOrder = $statusHistoryService->updateStatus($entityManager, $transportOrder, $statutOrder);

        $historyType = match($statutRequest->getCode()) {
            TransportRequest::STATUS_ONGOING => TransportHistoryService::TYPE_ONGOING,
            TransportRequest::STATUS_SUBCONTRACTED => TransportHistoryService::TYPE_SUBCONTRACTED,
            TransportRequest::STATUS_FINISHED => TransportHistoryService::TYPE_FINISHED,
            TransportRequest::STATUS_NOT_DELIVERED => TransportHistoryService::TYPE_NOT_DELIVERED,
            default => throw new RuntimeException("Unhandled status code"),
        };;

        $transportService->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, $historyType, [
            'history' => $statusHistoryRequest
        ]);

        $transportService->transportHistoryService->persistTransportHistory($entityManager, $transportOrder, $historyType, [
            'history' => $statusHistoryOrder
        ]);

        $entityManager->flush();

        return $this->json($this->redirectToRoute('transport_subcontract_index'));
    }
}
