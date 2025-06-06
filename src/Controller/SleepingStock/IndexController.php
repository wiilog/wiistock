<?php

namespace App\Controller\SleepingStock;

use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Entity\SleepingStockRequestInformation;
use App\Service\Cache\CacheNamespaceEnum;
use App\Service\Cache\CacheService;
use App\Service\FormatService;
use App\Service\FormService;
use App\Service\RequestTemplateLineService;
use App\Service\RequestTemplateService;
use App\Service\UserService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route("/sleeping-stock", name: "sleeping_stock")]
class IndexController extends AbstractController {
    private const MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM = 50;

    #[Route("/", name: "_index", methods: [self::GET])]
    public function index(EntityManagerInterface   $entityManager,
                          UserService              $userService,
                          FormatService            $formatService,
                          CacheService             $cacheService,
                          FormService              $formService): Response {
        $sleepingStockRequestInformationRepository = $entityManager->getRepository(SleepingStockRequestInformation::class);
        $movementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $user = $userService->getUser();

        $sleepingReferenceArticlesData = $movementStockRepository->findForSleepingStock(
            $user,
            self::MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM,
        );

        /**
         * @var $actionButtonsItems array{
         *     "withTemplate": array<string, string>,
         *     "withoutTemplate": array<string, string>,
         * }
         */
        $actionButtonsItems = $cacheService->get(
            CacheNamespaceEnum::SETTINGS,
            SleepingStockRequestInformation::class,
            static function () use ($sleepingStockRequestInformationRepository): array {
                return Stream::from($sleepingStockRequestInformationRepository->findAll())
                    ->keymap(
                        function (SleepingStockRequestInformation $sleepingStockRequestInformation): array {
                            $deliveryRequestTemplateId = $sleepingStockRequestInformation->getDeliveryRequestTemplate()?->getId();
                            $arrayKey = $deliveryRequestTemplateId ? "withTemplate" : "withoutTemplate";
                            return [
                                $arrayKey,
                                [
                                    "label" => $sleepingStockRequestInformation->getButtonActionLabel(),
                                    "value" => $deliveryRequestTemplateId ?: -1,
                                    "iconUrl" => $sleepingStockRequestInformation->getDeliveryRequestTemplate()?->getButtonIcon()?->getFullPath(),
                                ]
                            ];
                        },
                        true
                    )
                    ->toArray();
            }
        );

        $actionButtonsItems["withTemplate"] ??= [];
        $actionButtonsItems["withoutTemplate"] ??= [];

        $referenceArticles = Stream::from($sleepingReferenceArticlesData["referenceArticles"])
            ->map(static function (array $referenceArticle) use ($formatService, $actionButtonsItems, $formService) {
                $switchesItems = array_merge(
                    !$referenceArticle["isSleeping"] ? $actionButtonsItems["withoutTemplate"] : [],
                    $actionButtonsItems["withTemplate"],
                );
                $switches = $formService->macro(
                    "switch",
                    "template-" . $referenceArticle["id"],
                    null,
                    true,
                    $switchesItems,
                    [
                        "labelClass" => "full-width",
                    ]
                );
                $inputId = $formService->macro("hidden", "id", $referenceArticle["id"]);
                $inputEntity = $formService->macro("hidden", "entity", $referenceArticle["entity"]);
                $deleteRowButton = "<button class='btn btn-silent delete-row mr-2' data-id='{$referenceArticle["id"]}'><i class='wii-icon wii-icon-trash text-primary wii-icon-17px-danger'></i></button>";
                $maxStorageDate = $referenceArticle["maxStorageDate"];
                return [
                    ...$referenceArticle,
                    "actions" => "<div class='d-flex full-width'> $deleteRowButton $switches</div>$inputId $inputEntity",
                    "maxStorageDate" => $formatService->dateTime($maxStorageDate),

                ];
            })
            ->toArray();

        $countTotal = $sleepingReferenceArticlesData["countTotal"];

        return $this->render('sleeping_stock/index.html.twig', [
            "datatableInitialData" => json_encode([
                "data" => $referenceArticles,
                "recordsTotal" => $countTotal,
            ]),
        ]);
    }

    #[Route("/", name: "_submit", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    public function submit(EntityManagerInterface     $entityManager,
                           Request                    $request,
                           RequestTemplateService     $requestTemplateService,
                           RequestTemplateLineService $requestTemplateLineService): JsonResponse {
        $deliveryRequestTemplateSleepingStockRepository = $entityManager->getRepository(DeliveryRequestTemplateSleepingStock::class);
        $now = new DateTime();
        $actions = Stream::from(json_decode($request->request->get("actions"), true))
            ->keymap(
                function (array $action) use ($requestTemplateLineService, $now, $entityManager): array {
                    $id = $action["id"];
                    $requestTemplateLine = match ($action["entity"]) {
                        ReferenceArticle::class => $requestTemplateLineService->createRequestTemplateLineReference($entityManager, $id, $now),
                        Article::class => $requestTemplateLineService->createRequestTemplateLineArticle($entityManager, $id, $now),
                        default => throw new Exception("Unknown entity type " . $action["entity"]),
                    };
                    return [
                        $action["templateId"],
                        $requestTemplateLine
                    ];
                },
                true
            )
            ->toArray();

        foreach ($actions as $templateId => $lines) {
            if ($templateId > 0) {
                $lines = new ArrayCollection($lines);
                $deliveryRequestTemplateSleepingStock = $deliveryRequestTemplateSleepingStockRepository->find($templateId);
                $deliveryRequestTemplateSleepingStock->setLines($lines);
                $requestTemplateService->treatRequestTemplateTriggerType($entityManager, $deliveryRequestTemplateSleepingStock);
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "msg" => "Votre demande a bien été prise en compte."
        ]);
    }
}
