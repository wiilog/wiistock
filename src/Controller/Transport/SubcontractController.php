<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Transport\TransportRequest;
use DateTime;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;



#[Route("transport/sous-traitance")]
class SubcontractController extends AbstractController {

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

        foreach ($transportRequests as $date => $requests) {
            if ($date !== "A valider"){
                $date = DateTime::createFromFormat("dmY", $date);
                $date = FormatHelper::longDate($date);
            }

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date</div>";

            $rows[] = [
                "content" => $row,
            ];
            $currentRow = [];

            foreach ($requests as $request) {
                if ($date !== "A valider"){
                    $currentRow[] = $this->renderView("transport/subcontract/list_card.html.twig", [
                        "prefix" => TransportRequest::NUMBER_PREFIX,
                        "request" => $request,
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
            "recordsTotal" => $awaitingValidationResult["total"] + $subcontractOrderResult["total"],
            "recordsFiltered" => $awaitingValidationResult["count"] + $subcontractOrderResult["count"],
        ]);
    }

}
