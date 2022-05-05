<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Transport\TransportRound;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/tournee")]
class RoundController extends AbstractController {

    #[Route("/liste", name: "transport_round_index", methods: "GET")]
    public function index(): Response {
        // TODO
        return $this->render('transport/round/index.html.twig');
    }

    #[Route('/api', name: 'transport_round_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_TRANSPORT], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);
        $roundRepository = $manager->getRepository(TransportRound::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSPORT_ROUNDS, $this->getUser());
        $queryResult = $roundRepository->findByParamAndFilters($request->request, $filters);

        $transportRounds = [];
        foreach ($queryResult["data"] as $transportRound) {
            $beganAtStr = $transportRound->getBeganAt()?->format("dmY");
            if ($beganAtStr) {
                $transportRounds[$beganAtStr][] = $transportRound;
            }
        }

        $rows = [];
        $currentRow = [];
        foreach ($transportRounds as $date => $rounds) {
            $date = DateTime::createFromFormat("dmY", $date);
            $date = FormatHelper::longDate($date);

            $row = "<div class='transport-list-date px-1 pb-2 pt-3'>$date</div>";
            if(!$rows) {
                $export = "<span>
                    <button type='button' class='btn btn-primary mr-1'
                            onclick='saveExportFile(`transport_rounds_export`)'>
                        <i class='fa fa-file-csv mr-2' style='padding: 0 2px'></i>
                        Exporter au format CSV
                    </button>
                </span>";

                $row = "<div class='d-flex flex-column-reverse flex-md-row justify-content-between'>$row $export</div>";
            }

            $rows[] = [
                "content" => $row,
            ];

            /** @var TransportRound $transportRound */
            foreach ($rounds as $transportRound) {
                $hours = null;
                $minutes = null;
                if($transportRound->getEndedAt()) {
                    $timestamp = $transportRound->getEndedAt()->getTimestamp() - $transportRound->getBeganAt()->getTimestamp();
                    $hours = floor(($timestamp / 60) / 60);
                    $minutes = floor($timestamp / 60) - ($hours * 60);
                }

                $currentRow[] = $this->renderView("transport/round/list_card.html.twig", [
                    "prefix" => "T",
                    "round" => $transportRound,
                    "realTime" => $transportRound->getEndedAt()
                        ? $hours . "h" . $minutes . "min"
                        : '-'
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
            "recordsTotal" => $queryResult["total"],
            "recordsFiltered" => $queryResult["count"],
        ]);
    }

    #[Route("/planning-api/form", name: "plan_round_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::ORDRE, Action::SCHEDULE_TRANSPORT_ROUND], mode: HasPermission::IN_JSON)]
    public function planRoundApi(): JsonResponse
    {
        return $this->json([
            "success" => true,
            "html" => $this->renderView('transport/round/round-content.html.twig'),
        ]);
    }
    #[Route("/voir/{transportRound}", name: "transport_round_show", methods: "GET")]
    public function show(TransportRound $transportRound): Response {
        // TODO Faire la page de show
        return $this->render('transport/round/show.html.twig');
    }

    #[Route("/planifier", name: "transport_round_plan", options: ['expose' => true], methods: "GET")]
    #[HasPermission([Menu::ORDRE, Action::SCHEDULE_TRANSPORT_ROUND])]
    public function plan(Request $request, EntityManagerInterface $entityManager): Response
    {
        // TODO Faire la page planifier
        return $this->render('transport/round/plan.html.twig');
    }
}
