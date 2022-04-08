<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Attachment;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use DateTime;
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\NotificationService;
use App\Service\TransferOrderService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;



#[Route("transport/sous-traitance")]
class SubcontractController extends AbstractController {

    private $userService;
    private $service;

    public function __construct(UserService $us, TransferOrderService $service) {
        $this->userService = $us;
        $this->service = $service;
    }

    #[Route("/liste", name: "transport_subcontract_index", methods: "GET")]
    public function index(EntityManagerInterface $em): Response {
        $statusRepository = $em->getRepository(Statut::class);
        $typesRepository = $em->getRepository(Type::class);

        return $this->render('transport/subcontract/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSPORT_ORDER_DELIVERY),
            'types' => $typesRepository->findByCategoryLabels([CategoryType::DELIVERY_TRANSPORT])
        ]);
    }

    #[Route('/api', name: 'transport_subcontract_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_TRANSPORT_SUBCONTRACT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $statusRepository = $manager->getRepository(Statut::class);
        $transportRequestRepository = $manager->getRepository(TransportRequest::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SUBCONTRACT_ORDERS, $this->getUser());

        $request->request->add(["status" => $statusRepository->findByCategorieNamesAndStatusCodes(
            [CategorieStatut::TRANSPORT_REQUEST_DELIVERY, CategorieStatut::TRANSPORT_REQUEST_COLLECT],
            [TransportRequest::STATUS_AWAITING_VALIDATION])]);
        $queryResultRequest = $transportRequestRepository->findByParamAndFilters($request->request, $filters);

        $request->request->remove('status');
        $request->request->add(["subcontracted" => true]);
        $queryResultOrder = $transportRequestRepository->findByParamAndFilters($request->request, $filters);
        $transportRequestsUp = [];
        $transportRequestsDown = [];
        foreach ($queryResultRequest["data"] as $requestUp) {
            $requestUp->setExpectedAt(new DateTime());
            $transportRequestsUp["A valider"][] = $requestUp;
        }
        $i = 2;
        foreach ($queryResultOrder["data"] as $requestDown) {
            $requestDown->setExpectedAt(new DateTime("+".$i." days"));
            $transportRequestsDown[$requestDown->getExpectedAt()->format("dmY")][] = $requestDown;
            $i++;
        }

        $rows = [];
        $currentRow = [];

        foreach ($transportRequestsUp as $toValidate => $requests) {
            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$toValidate</div>";

            $rows[] = [
                "content" => $row,
            ];

            foreach ($requests as $request) {
                $currentRow[] = $this->renderView("transport/subcontract/card_to_validate.html.twig", [
                    "prefix" => "DTR",
                    "request" => $request,
                ]);
            }

            if ($currentRow) {
                $row = "<div class='transport-row row no-gutters'>" . join($currentRow) . "</div>";
                $rows[] = [
                    "content" => $row,
                ];

                $currentRow = [];
            }
        }

        foreach ($transportRequestsDown as $date => $requests) {
            $date = DateTime::createFromFormat("dmY", $date);
            $date = FormatHelper::longDate($date);

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date</div>";

            $rows[] = [
                "content" => $row,
            ];

            foreach ($requests as $request) {
                $currentRow[] = $this->renderView("transport/subcontract/list_card.html.twig", [
                    "prefix" => "DTR",
                    "request" => $request,

                ]);
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
            "recordsTotal" => $queryResultRequest["total"]+$queryResultOrder["total"],
            "recordsFiltered" => $queryResultRequest["count"]+$queryResultOrder["count"],
        ]);
    }

}
