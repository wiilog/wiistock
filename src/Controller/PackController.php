<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\Project;
use App\Entity\ReceptionLine;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Service\CSVExportService;
use App\Service\LanguageService;
use App\Service\PackService;
use App\Service\PDFGeneratorService;
use App\Service\ProjectHistoryRecordService;
use App\Service\TrackingDelayService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;
use App\Entity\Dashboard;

#[Route("/unite-logistique", name: 'pack_')]
class PackController extends AbstractController {

    #[Route("/liste/{code}", name: "index", options: ["expose" => true], defaults: ["code" => null], methods: [self::GET])]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_PACK])]
    public function index(EntityManagerInterface $entityManager,
                          Request                $request,
                          LanguageService        $languageService,
                          PackService            $packService,
                                                 $code): Response {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $projectRepository = $entityManager->getRepository(Project::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $filterSupRepository = $entityManager->getRepository(FiltreSup::class);
        $dashboardComponentRepository = $entityManager->getRepository(Dashboard\Component::class);

        $fields = $packService->getPackListColumnVisibleConfig($this->getUser());

        $data = $request->query;

        $dashboardComponentId = $data->get("dashboardComponentId");
        $natureLabel = $data->get("natureLabel");

        /** @var Dashboard\Component $dashboardComponent */
        $dashboardComponent = $dashboardComponentId
            ? $dashboardComponentRepository->find($dashboardComponentId)
            : null;
        $packWithTracking = (bool)$filterSupRepository->findOnebyFieldAndPageAndUser("packWithTracking", 'pack', $this->getUser());

        if ($dashboardComponent?->getType()?->getMeterKey() === Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY) {
            $fromDashboard = true;
            $config = $dashboardComponent->getConfig();
            $locationsFilter = !empty($config["locations"])
                ? $locationRepository->findBy(['id' => $config["locations"]])
                : [];
            $naturesFilter = !empty($config["natures"])
                ? Stream::from($natureRepository->findBy(['id' => $config["natures"]]))
                    ->filter(static fn(Nature $nature) => !isset($natureLabel) || $nature->getLabel() === $natureLabel)
                    ->map(static fn(Nature $nature) => $nature->getId())
                    ->toArray()
                : [];
            $packWithTracking = true;
        }

        return $this->render('pack/index.html.twig', [
            'userLanguage' => $this->getUser()->getLanguage(),
            'defaultLanguage' => $languageService->getDefaultLanguage(),
            "fields" => $fields,
            'natures' => $natureRepository->findBy([], ['label' => 'ASC']),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]),
            'projects' => $projectRepository->findActive(),
            'code' => $code,
            'locationsFilter' => $locationsFilter ?? [],
            'naturesFilter' => $naturesFilter ?? [],
            'fromDashboard' => $fromDashboard ?? false,
            'packWithTracking' => $packWithTracking,
        ]);
    }

    #[Route('/voir/{id}', name: 'show', methods: [self::GET])]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_PACK])]
    public function show(Pack                   $logisticUnit,
                         EntityManagerInterface $entityManager,
                         PackService            $packService): Response {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $movements = $trackingMovementRepository->findChildArticleMovementsBy($logisticUnit);

        $arrival = $logisticUnit->getArrivage();

        $truckArrival = $arrival
            ? $arrival->getTruckArrival() ?? ($arrival->getTruckArrivalLines()->first() ? $arrival->getTruckArrivalLines()->first()?->getTruckArrival() : null)
            : null ;

        $fields = $packService->getPackListColumnVisibleConfig($this->getUser());
        $lastMessage = $logisticUnit->getLastMessage();
        $hasPairing = !$logisticUnit->getPairings()->isEmpty() || $lastMessage;

        return $this->render('pack/show.html.twig', [
            "packActionButtons" => $packService->getActionButtons($logisticUnit, $hasPairing),
            "logisticUnit" => $logisticUnit,
            "fields" => $fields,
            "movements" => $movements,
            "arrival" => $arrival,
            "truckArrival" => $truckArrival,
            "barcode" => [
                "code" => $logisticUnit->getCode(),
                "height" => 10,
                "width" => 10,
                "type" => 'qrcode',
            ],
            "trackingDelay" => $packService->formatTrackingDelayData($logisticUnit),
            "hasPairing" => $hasPairing,
        ]);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: [self::GET, self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_PACK], mode: HasPermission::IN_JSON)]
    public function api(Request $request, PackService $packService): JsonResponse {
        $data = $packService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    #[Route("/{pack}/contenu", name: "content", options: ["expose" => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_PACK], mode: HasPermission::IN_JSON)]
    public function logisticUnitContent(EntityManagerInterface $manager,
                                        Pack                    $pack,
                                        LanguageService         $languageService): JsonResponse {
        $longFormat = $languageService->getCurrentUserLanguageSlug() === Language::FRENCH_SLUG;

        $trackingMovementRepository = $manager->getRepository(TrackingMovement::class);
        $movements = $trackingMovementRepository->findChildArticleMovementsBy($pack);

        return $this->json([
            "success" => true,
            "html" => $this->renderView("pack/logistic-unit-content.html.twig", [
                "pack" => $pack,
                "movements" => $movements,
                "use_long_format" => $longFormat,
            ]),
        ]);
    }

    #[Route("/csv", name: "export", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::TRACA, Action::EXPORT])]
    public function printCSVPacks(Request                   $request,
                                  PackService               $packService,
                                  CSVExportService          $CSVExportService,
                                  EntityManagerInterface    $entityManager): StreamedResponse {


        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        return $CSVExportService->streamResponse(
            $packService->getExportPacksFunction(
                $dateTimeMin,
                $dateTimeMax,
                $entityManager,
            ), 'export_UL.csv',
            $packService->getCsvHeader()
        );
    }

    #[Route("/api-modifier/{pack}", name: "edit_api", options: ["expose" => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(Pack                   $pack,
                            EntityManagerInterface $entityManager): JsonResponse {
        $preparationOrderArticleLineRepository = $entityManager->getRepository(PreparationOrderArticleLine::class);
        $deliveryRequestArticleLineRepository = $entityManager->getRepository(DeliveryRequestArticleLine::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $projectRepository = $entityManager->getRepository(Project::class);

        $projects = Stream::from($projectRepository->findActive())
            ->map(fn(Project $project) => [
                "label" => $project->getCode(),
                "value" => $project->getId(),
                "selected" => $pack->getProject() === $project
            ]);

        $disabledProject = (
            $preparationOrderArticleLineRepository->isOngoingAndUsingPack($pack)
            || $deliveryRequestArticleLineRepository->isOngoingAndUsingPack($pack)
            || Stream::from($pack->getChildArticles())->some(fn(Article $article) => $article->getCarts()->count())
            || !empty($pack->getArticle())
        );

        $html = $this->renderView('pack/modalEditPackContent.html.twig', [
            'natures' => $natureRepository->findBy([], ['label' => 'ASC']),
            'pack' => $pack,
            'projects' => $projects,
            'disabledProject' => !empty($disabledProject)
        ]);

        return new JsonResponse([
            "html" => $html
        ]);
    }

    #[Route("/modifier", name: "edit", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         PackService            $packService,
                         TranslationService     $translation): JsonResponse {
        $data = $request->request;
        $response = [];
        $packRepository = $entityManager->getRepository(Pack::class);
        $pack = $packRepository->find($data->get('id'));
        $isGroup = $pack->getGroupIteration() || !empty($pack->getChildren);

        $packDataIsValid = $packService->checkPackDataBeforeEdition($data, $isGroup);
        if (!empty($pack) && $packDataIsValid['success']) {
            $packService->editPack($entityManager, $data, $pack, $isGroup);

            $entityManager->flush();
            $response = [
                'success' => true,
                'msg' => $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "L'unité logistique {1} a bien été modifiée", [
                    1 => $pack->getCode()
                ])

            ];
        } else if (!$packDataIsValid['success']) {
            $response = $packDataIsValid;
        }
        return new JsonResponse($response);
    }

    #[Route("/supprimer/{pack}", name: "delete", options: ["expose" => true], methods: [self::DELETE], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::TRACA, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                  $request,
                           Pack                     $pack,
                           EntityManagerInterface   $entityManager,
                           TranslationService       $translation): JsonResponse {
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);

        $packCode = $pack->getCode();
        $arrivage = $request->query->getInt('arrivage') ? $arrivageRepository->find($request->query->getInt('arrivage')) : null;
        if (!$pack->getTrackingMovements()->isEmpty()) {
            $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Cette unité logistique est référencée dans un ou plusieurs mouvements de traçabilité");
        }

        if (!$pack->getDispatchPacks()->isEmpty()) {
            $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Cette unité logistique est référencée dans un ou plusieurs acheminements");
        }

        if (!$pack->getDisputes()->isEmpty()) {
            $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "Cette unité logistique est référencée dans un ou plusieurs litiges");
        }
        if ($pack->getArrivage() && $arrivage !== $pack->getArrivage()) {
            $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Cette unité logistique est utilisé dans l\'arrivage UL {1}', [
                1 => $pack->getArrivage()->getNumeroArrivage()
            ]);
        }
        if ($pack->getTransportDeliveryOrderPack()) {
            $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Cette unité logistique est utilisé dans un ' . mb_strtolower($translation->translate("Ordre", "Livraison", "Ordre de livraison", false)));
        }
        if (!$pack->getChildArticles()->isEmpty()) {
            $msg = $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', 'Cette unité logistique contient des articles');
        }

        if (isset($msg)) {
            return $this->json([
                "success" => false,
                "msg" => $msg
            ]);
        }

        $receptionLine = $receptionLineRepository->findOneBy(['pack' => $pack]);
        if ($receptionLine) {
            $reception = $receptionLine->getReception();
            $reception?->removeLine($receptionLine);
            $receptionLine->setPack(null);
            $entityManager->flush();
            $entityManager->remove($receptionLine);
        }

        $entityManager->remove($pack);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true, "",
            'msg' => $translation->translate('Traçabilité', 'Unités logistiques', 'Onglet "Unités logistiques"', "L'unité logistique {1} a bien été supprimée", [
                1 => $packCode
            ])
        ]);

    }

    #[Route("/group_history/{pack}", name: "group_history_api", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function groupHistory(Request $request, PackService $packService, $pack): JsonResponse {
        if ($request->isXmlHttpRequest()) {
            $data = $packService->getGroupHistoryForDatatable($pack, $request->request);
            return $this->json($data);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/{logisticUnit}/group_content", name: "group_content_api", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function groupContentApi(Request                $request,
                                    EntityManagerInterface $entityManager,
                                    PackService            $packService,
                                    Pack                   $logisticUnit): JsonResponse {

        $packRepository = $entityManager->getRepository(Pack::class);

        $groupContent = $packRepository->getPackContentFiltered($request->request, $logisticUnit);

        $showPageMode = $request->query->getBoolean("showPageMode");
        $itemColor = $showPageMode ? "" : "bg-white";

        if ($groupContent["total"] === 0) {
            return $this->json([
                "data" => [
                    [
                        "content" => "
                            <div class='bold w-100 text-center m-3 allow-expand'>
                                Ce groupe est vide
                            </div>
                        ",
                    ]
                ],
                "recordsFiltered" => 1,
                "recordsTotal" => 1,
            ]);
        }


        return $this->json([
            "data" => Stream::from($groupContent["data"])
                ->map(function(Pack $pack) use ($packService, $showPageMode, $itemColor) {
                    $trackingDelay = $packService->formatTrackingDelayData($pack);
                    return [
                        "content" => $this->renderView('pack/content_group.html.twig', [
                            "pack" => $pack,
                            "trackingDelay" => $trackingDelay["delayHTMLRaw"] ?? null,
                            "itemBgColor" => $itemColor,
                        ]),
                    ];
                })
                ->toArray(),
            "recordsFiltered" => $groupContent["count"],
            "recordsTotal" => $groupContent["total"],
        ]);
    }
    #[Route("/project_history/{pack}", name: "project_history_api", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    public function projectHistory(Request                     $request,
                                   EntityManagerInterface      $entityManager,
                                   ProjectHistoryRecordService $projectHistoryRecordService,
                                   Pack                        $pack): JsonResponse {
        $data = $projectHistoryRecordService->getProjectHistoryForDatatable($entityManager, $pack, $request->request);
        return $this->json($data);
    }

    #[Route("/print-single-logistic-unit/{pack}", name: "print_single", options: ["expose" => true])]
    public function printSingleLogisticUnit(Pack $pack, PackService $packService, PDFGeneratorService $PDFGeneratorService): PdfResponse {
        if ($pack->getNature() && !$pack->getNature()->getTags()->isEmpty()) {
            $tag = $pack->getNature()->getTags()->first();
        } else {
            $tag = null;
        }
        $config = $packService->getBarcodePackConfig($pack);
        $fileName = $PDFGeneratorService->getBarcodeFileName($config, 'UL', $tag?->getPrefix() ?? 'ETQ');
        $render = $PDFGeneratorService->generatePDFBarCodes($fileName, [$config], false, $tag);
        return new PdfResponse(
            $render,
            $fileName
        );
    }

    #[Route("/get-location", name: "get_location", options: ["expose" => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    public function getLocation(Request                 $request,
                                EntityManagerInterface  $entityManager): JsonResponse {
        $pack = $entityManager->getRepository(Pack::class)->findOneBy(['code' => $request->query->get('pack')]);
        $location = $pack?->getLastOngoingDrop()?->getEmplacement();
        return $this->json([
            'success' => true,
            'location' => $location?->getId(),
        ]);
    }

    #[Route("/{id}/tracking-history-api", name: "tracking_history_api", options: ['expose' => true], methods: [self::POST])]
    public function trackingHistoryApi(Pack                   $logisticUnit,
                                       Request                $request,
                                       EntityManagerInterface $entityManager,
                                       PackService            $packService): JsonResponse {
        $logisticUnitHistoryRecordsRepository = $entityManager->getRepository(LogisticUnitHistoryRecord::class);

        $queryResult = $logisticUnitHistoryRecordsRepository->findByParamsAndFilters($request->request, $logisticUnit);

        if ($queryResult["total"] === 0) {
            return $this->json([
                "data" => [
                    [
                        "history" => "Aucun historique trouvé",
                    ]
                ],
                "recordsFiltered" => 1,
                "recordsTotal" => 1,
            ]);
        }

        $latestRecord = $logisticUnitHistoryRecordsRepository->findOneRecord($logisticUnit, "last");
        $firstRecord = $logisticUnitHistoryRecordsRepository->findOneRecord($logisticUnit, "first");

        return $this->json([
            "data" => Stream::from($queryResult["data"])
                ->map(static fn(LogisticUnitHistoryRecord $record) => [
                    "history" => $packService->generateTrackingHistoryHtml($record, $firstRecord->getId(), $latestRecord->getId()),
                ])
                ->toArray(),
            "recordsFiltered" => $queryResult["count"],
            "recordsTotal" => $queryResult["total"],
        ]);
    }

    #[Route("/{logisticUnit}/tracking-delay", name: "force_tracking_delay_calculation", options: ['expose' => true], methods: [self::POST])]
    public function postTrackingDelay(EntityManagerInterface $entityManager,
                                      TrackingDelayService   $trackingDelayService,
                                      Pack                   $logisticUnit): JsonResponse {
        $trackingDelayService->updateTrackingDelay($entityManager, $logisticUnit);

        $entityManager->flush();

        return $this->json([
            "success" =>true,
        ]);
    }

}
