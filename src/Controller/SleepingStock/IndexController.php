<?php

namespace App\Controller\SleepingStock;

use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Entity\RequestTemplate\RequestTemplateLineArticle;
use App\Entity\RequestTemplate\RequestTemplateLineReference;
use App\Entity\SleepingStockRequestInformation;
use App\Service\CacheService;
use App\Service\FormatService;
use App\Service\FormService;
use App\Service\IOT\IOTService;
use App\Service\SleepingStockPlanService;
use App\Service\UserService;
use DateInterval;
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
    private const MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM = 1000;

    #[Route("/", name: "_index", methods: [self::GET])]
    public function index(EntityManagerInterface   $entityManager,
                          UserService              $userService,
                          FormatService            $formatService,
                          CacheService             $cacheService,
                          SleepingStockPlanService $sleepingStockPlanService,
                          FormService              $formService): Response {
        $sleepingStockRequestInformationRepository = $entityManager->getRepository(SleepingStockRequestInformation::class);
        $movementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $user = $userService->getUser();

        $sleepingReferenceArticlesData = $movementStockRepository->findForSleepingStock(
            $user,
            self::MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM,
            $sleepingStockPlanService
        );

        $actionButtonsItems = $cacheService->get(CacheService::COLLECTION_SETTINGS, SleepingStockRequestInformation::class, static function () use ($sleepingStockRequestInformationRepository): array {
            return Stream::from($sleepingStockRequestInformationRepository->findAll())
                ->map(fn(SleepingStockRequestInformation $sleepingStockRequestInformation, int $index) => [
                    "label" => $sleepingStockRequestInformation->getButtonActionLabel(),
                    "value" => $sleepingStockRequestInformation->getDeliveryRequestTemplate()->getId(),
                    "iconUrl" => $sleepingStockRequestInformation->getDeliveryRequestTemplate()?->getButtonIcon()?->getFullPath(),
                    "checked" => $index === 0
                ])
                ->toArray();
        });

        $referenceArticles = Stream::from($sleepingReferenceArticlesData["referenceArticles"])
            ->map(static function (array $referenceArticle) use ($formatService, $actionButtonsItems, $formService) {
                $switchs = $formService->macro(
                    "switch",
                    "template-" . $referenceArticle["id"],
                    null,
                    true,
                    $actionButtonsItems,
                    [
                        "labelClass" => "full-width",
                    ]
                );
                $inputId = $formService->macro("hidden", "id", $referenceArticle["id"]);
                $inputEntity = $formService->macro("hidden", "entity", $referenceArticle["entity"]);
                $deleteRowButton = "<button class='btn btn-silent delete-row mr-2' data-id='{$referenceArticle["id"]}'><i class='wii-icon wii-icon-trash text-primary wii-icon-17px-danger'></i></button>";
                $maxStorageDate = $referenceArticle["lastMovementDate"]->sub(new DateInterval("PT{$referenceArticle["maxStorageTime"]}S"));
                return [
                    "actions" => "<div class='d-flex full-width'> $deleteRowButton $switchs</div>$inputId $inputEntity",
                    "maxStorageDate" => $formatService->dateTime($maxStorageDate),
                    ...$referenceArticle
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
    public function submit(EntityManagerInterface $entityManager,
                           Request $request,
                           IOTService $IOTService): JsonResponse {

        // TODO GERAX LE TOKEN

        $deliveryRequestTemplateSleepingStockRepository = $entityManager->getRepository(DeliveryRequestTemplateSleepingStock::class);
        $now = new DateTime();
        $actions =  Stream::from(json_decode($request->request->get("actions"), true))
            ->reduce(
                function (array $carry, array $action) use ($now, $entityManager): array {
                    $requestTemplateLine = match ($action["entity"]) {
                        ReferenceArticle::class => $this->createRequestTemplateLineReference($entityManager, $action, $now),
                        Article::class => $this->createRequestTemplateLineArticle($entityManager, $action, $now),
                        default => throw new Exception("Unknown entity type " . $action["entity"]),
                    };
                    $carry[$action["templateId"]][] = $requestTemplateLine;
                    return $carry;
                },
                []
            );

        // TODO GERAX les droit de creation de demance avec des articles dedans

        foreach ($actions as $templateId => $lines) {
            $lines = new ArrayCollection($lines);
            $deliveryRequestTemplateSleepingStock = $deliveryRequestTemplateSleepingStockRepository->find($templateId);
            $deliveryRequestTemplateSleepingStock->setLines($lines);
            $IOTService // TODO bouger la fonction dans un utre service
                ->treatRequestTemplateTriggerType(
                    $deliveryRequestTemplateSleepingStock,
                    $entityManager
               );
        }

        return new JsonResponse([
            "success" => true,
            "msg" => "Votre demande a bien été prise en compte."
        ]);
    }

    private function createRequestTemplateLineReference(EntityManagerInterface $entityManager, array $action, DateTime $now): RequestTemplateLineReference { // TODO move
        $referenceArticle = $entityManager->getReference(ReferenceArticle::class, $action["id"]);
        $referenceArticle->setLastSleepingStockAlertAnswer($now);
        return (New RequestTemplateLineReference())
            ->setReference($referenceArticle)
            ->setQuantityToTake($referenceArticle->getQuantiteDisponible());
    }
    private function createRequestTemplateLineArticle(EntityManagerInterface $entityManager, array $action, DateTime $now): RequestTemplateLineArticle { // TODO move
        $article = $entityManager->find(Article::class, $action["id"]);
        $article->getReferenceArticle()->setLastSleepingStockAlertAnswer($now);
        return (New RequestTemplateLineArticle())
            ->setArticle($article)
            ->setQuantityToTake($article->getQuantite());
    }
}
