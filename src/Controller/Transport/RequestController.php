<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Reception;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestNature;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestNature;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\TransportService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\CategorieStatut;
use App\Entity\Statut;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/demande")]
class RequestController extends AbstractController {

    /**
     * Called in /index.html.twig
     */
    #[Route("/liste", name: "transport_request_index", methods: "GET")]
    public function index(EntityManagerInterface $entityManager): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        return $this->render('transport/request/index.html.twig', [
            'newRequest' => new TransportDeliveryRequest(),
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
                CategoryType::COLLECT_TRANSPORT_REQUEST,
                CategoryType::DELIVERY_TRANSPORT_REQUEST
            ]),
            'natures' => $natureRepository->findByAllowedForms([
                Nature::TRANSPORT_COLLECT_CODE,
                Nature::TRANSPORT_DELIVERY_CODE
            ]),
            'temperatures' => $temperatureRangeRepository->findAll(),
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
                        TransportService $transportService): JsonResponse {

        $transportService->persistTransportRequest($entityManager, $this->getUser(), $request);

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Votre demande de transport a bien été créée",
        ]);
    }

}
