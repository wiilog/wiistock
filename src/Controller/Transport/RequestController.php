<?php

namespace App\Controller\Transport;

use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use App\Annotation\HasPermission;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Utilisateur;
use App\Service\UniqueNumberService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/demande")]
class RequestController extends AbstractController {

    #[Route("/liste", name: "transport_request_index", methods: "GET")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT])]
    public function index(Request $request, EntityManagerInterface $manager): Response {
        $typeRepository = $manager->getRepository(Type::class);

        return $this->render('transport/request/index.html.twig', [
            'categories' => [
                [
                    "category" => CategoryType::DELIVERY_TRANSPORT_REQUEST,
                    "icon" => "cart-delivery",
                    "label" => "Livraison",
                ], [
                    "category" => CategoryType::COLLECT_TRANSPORT_REQUEST,
                    "icon" => "cart-collect",
                    "label" => "Collecte" ,
                ],
            ],
            'types' => $typeRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT_REQUEST, CategoryType::COLLECT_TRANSPORT_REQUEST,
            ]),
            'statuts' => [
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_TO_DELIVER,
                TransportRequest::STATUS_AWAITING_PLANNING,
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_ONGOING,
                TransportRequest::STATUS_FINISHED,
                TransportRequest::STATUS_DEPOSITED,
                TransportRequest::STATUS_CANCELLED,
                TransportRequest::STATUS_NOT_DELIVERED,
                TransportRequest::STATUS_NOT_COLLECTED,
            ],
            'newRequest' => new TransportDeliveryRequest(),
        ]);
    }

    #[Route("/voir/{transportRequest}", name: "transport_request_show", methods: "GET")]
    public function show(TransportRequest $transportRequest): Response {
        return $this->render('transport/request/show.html.twig', [
            'request' => $transportRequest,
        ]);
    }

    #[Route("/new", name: "transport_request_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::CREATE_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        UniqueNumberService $uniqueNumberService): JsonResponse {

        $typeRepository = $entityManager->getRepository(Type::class);

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $transportRequestType = $request->request->get('requestType');
        if (!in_array($transportRequestType, [TransportRequest::DISCR_COLLECT, TransportRequest::DISCR_DELIVERY])) {
            return $this->json([
                "message" => "Veuillez sélectionner un type de demande de transport",
                "success" => false,
            ]);
        }

        $typeStr = $request->request->get('type');

        if ($transportRequestType === TransportRequest::DISCR_DELIVERY) {
            $transportRequest = new TransportDeliveryRequest();
            $type = $typeRepository->findOneByCategoryLabel(CategoryType::DELIVERY_TRANSPORT_REQUEST, $typeStr);
        }
        else { //if ($requestType === TransportRequest::DISCR_COLLECT)
            $transportRequest = new TransportCollectRequest();
            $type = $typeRepository->findOneByCategoryLabel(CategoryType::COLLECT_TRANSPORT_REQUEST, $typeStr);
        }

        if (!isset($type)) {
            return $this->json([
                "message" => "Veuillez sélectionner un type pour votre demande de transport",
                "success" => false,
            ]);
        }

        $number = $uniqueNumberService->create($entityManager, TransportRequest::NUMBER_PREFIX, TransportRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT_REQUEST);
        $transportRequest
            ->setType($type)
            ->setNumber($number)
            ->setCreatedAt(new DateTime())
            ->setCreatedBy($loggedUser);

        $contact = $transportRequest->getContact();
        $contact
            ->setName($request->request->get('contactName'))
            ->setFileNumber($request->request->get('contactFileNumber'))
            ->setContact($request->request->get('contactContact'))
            ->setAddress($request->request->get('contactAddress'))
            ->setPersonToContact($request->request->get('contactPersonToContact'))
            ->setObservation($request->request->get('contactObservation'));



        $entityManager->persist($transportRequest);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été créée",
            "redirect" => $this->generateUrl('transport_request_show', [
                "transportRequest" => $transportRequest->getId()
            ])
        ]);
    }

    #[Route('/api', name: 'transport_request_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $transportRepository = $manager->getRepository(TransportRequest::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_REQUESTS, $this->getUser());

        $queryResult = $transportRepository->findByParamAndFilters($request->request, $filters);
dump($queryResult);
        $transportRequests = $queryResult['data'];

        $rows = [];
        $previousDate = null;
        $currentRow = [];

        function insertCurrentRow(&$rows, &$currentRow) {
            if($currentRow) {
                $rows[] = [
                    "content" => "<div class='transport-request-row'>" . join($currentRow) . "</div>",
                ];

                $currentRow = [];
            }
        }

        foreach ($transportRequests as $request) {
            if($request->getExpectedAt()->format("dmY") != $previousDate) {
                if($previousDate != null) {
                    insertCurrentRow($rows, $currentRow);
                }

                $previousDate = $request->getExpectedAt()->format("dmY");
                $rows[] = [
                    "content" => "<span class='transport-list-date'>" . FormatHelper::longDate($request->getExpectedAt()) . "</span>",
                ];
            }

            if(count($currentRow) == 2) {
                insertCurrentRow($rows, $currentRow);
            }

            $currentRow[] = $this->renderView("transport/request/list_card.html.twig", [
                "request" => $request,
            ]);
        }

        insertCurrentRow($rows, $currentRow);

        return $this->json([
            "data" => $rows,
            "recordsTotal" => $queryResult["total"],
            "recordsFiltered" => $queryResult["count"],
        ]);
    }

}
