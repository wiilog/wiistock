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
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Utilisateur;
use App\Service\AttachmentService;
use App\Service\Transport\TransportHistoryService;
use App\Service\Transport\TransportService;
use DateTime;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
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
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSPORT_ORDER_DELIVERY),
            'types' => $typesRepository->findByCategoryLabels([CategoryType::DELIVERY_TRANSPORT])
        ]);
    }

    #[Route('/api', name: 'transport_subcontract_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response
    {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $transportRequestRepository = $manager->getRepository(TransportRequest::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SUBCONTRACT_ORDERS, $this->getUser());

        $awaitingValidationResult = $transportRequestRepository->findByParamAndFilters(
            $request->request,
            $filters,
            [[
                "field" => FiltreSup::FIELD_STATUT,
                "value" => TransportRequest::STATUS_AWAITING_VALIDATION
            ]]
        );

        $subcontractOrderResult = $transportRequestRepository->findByParamAndFilters(
            $request->request,
            $filters,
            [[
                "field" => "subcontracted",
                "value" => true
            ]]
        );

        $transportRequests = [];
        foreach ($awaitingValidationResult["data"] as $requestUp) {
            $requestUp->setExpectedAt(new DateTime());
            $transportRequests["A valider"][] = $requestUp;
        }
        foreach ($subcontractOrderResult["data"] as $requestDown) {
            $requestDown->setExpectedAt(new DateTime());
            $transportRequests[$requestDown->getExpectedAt()->format("dmY")][] = $requestDown;
        }

        $rows = [];
        $currentRow = [];
        $prefix = "DTR";

        foreach ($transportRequests as $date => $requests) {
            if ($date !== "A valider") {
                $date = DateTime::createFromFormat("dmY", $date);
                $date = FormatHelper::longDate($date);
            }

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date</div>";

            $rows[] = [
                "content" => $row,
            ];

            foreach ($requests as $request) {
                if ($date !== "A valider") {
                    $currentRow[] = $this->renderView("transport/subcontract/list_card.html.twig", [
                        "prefix" => $prefix,
                        "request" => $request,

                    ]);
                } else {
                    $currentRow[] = $this->renderView("transport/subcontract/card_to_validate.html.twig", [
                        "prefix" => $prefix,
                        "request" => $request,
                    ]);
                }
            }

            if ($currentRow) {
                $row = "<div class='transport-row row no-gutters'>" . join($currentRow) . "</div>";
                $rows[] = [
                    "content" => $row,
                ];

                $currentRow = [];
            }
        }

        return $this->json([
            "data" => $rows,
            "recordsTotal" => $awaitingValidationResult["total"] + $subcontractOrderResult["total"],
            "recordsFiltered" => $awaitingValidationResult["count"] + $subcontractOrderResult["count"],
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
                            AttachmentService $attachmentService): ?Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        $transportRequestRepository = $entityManager->getRepository(TransportRequest::class);
        $data = $request->request;
        $transportRequest = $transportRequestRepository->find($data->get('id'));

        /** @var TransportOrder $transportOrder */
        $transportOrder = $transportRequest->getOrders()->last();

        $startedAt = FormatHelper::parseDatetime($data->get('delivery-start-date'));
        $statutRequest = $statutRepository->find($data->get('status') !== "null" ? $data->get('status') : $data->get('statut'));
        $statutOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, $statutRequest->getCode());

        $transportRequest->setStatus($statutRequest);
        $transportOrder->setStatus($statutOrder);

        $transportOrder->setSubcontractor($data->get('subcontractor'));
        $transportOrder->setRegistrationNumber($data->get('registrationNumber'));
        $transportOrder->setStartedAt($startedAt);
        $transportOrder->setComment($data->get('commentaire'));

        $attachmentService->manageAttachments($entityManager, $transportOrder, $request->files);

        $transportService->transportHistoryService->persistTransportHistory($entityManager, [$transportRequest, $transportOrder],
            ( ($statutRequest->getCode() === "Terminée" ? TransportHistoryService::TYPE_FINISHED :
                    ($statutRequest->getCode() === "En cours" ? TransportHistoryService::TYPE_ONGOING :
                            ($statutRequest->getCode() === "Sous-traitée" ? TransportHistoryService::TYPE_SUBCONTRACTED : TransportHistoryService::TYPE_NOT_DELIVERED)
                    )
            )
        ));

        $entityManager->flush();
        $json = $this->redirectToRoute('transport_subcontract_index');
        return new JsonResponse($json);
    }
}
