<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\Statut;
use App\Service\PurchaseRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Generator;
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
     */
    public function index(EntityManagerInterface $entityManager,
                          UserService $userService): Response
    {
        if(!$userService->hasRightFunction(Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS)) {
            return $this->redirectToRoute('access_denied');
        }

        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('purchase_request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST),
        ]);
    }

    /**
     * @Route("/api", name="purchase_request_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     */
    public function api(Request $request,
                        PurchaseRequestService $purchaseRequestService,
                        UserService $userService): Response {
        if($request->isXmlHttpRequest()) {
            if(!$userService->hasRightFunction(Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $purchaseRequestService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/voir/{id}", name="purchase_request_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param PurchaseRequest $request
     * @return Response
     */
    public function show(UserService $userService): Response {
        if(!$userService->hasRightFunction(Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS)) {
            return $this->redirectToRoute('access_denied');
        }

        /*return $this->render('transfer/request/show.html.twig', [
            'transfer' => $transfer,
            'modifiable' => FormatHelper::status($transfer->getStatus()) == TransferRequest::DRAFT,
            'detailsConfig' => $this->service->createHeaderDetailsConfig($transfer)
        ]);*/
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
                        if ($lines instanceof Generator && $lines->valid()) {
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
}
