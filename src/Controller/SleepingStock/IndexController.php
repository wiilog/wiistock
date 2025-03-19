<?php

namespace App\Controller\SleepingStock;

use App\Controller\AbstractController;
use App\Entity\ReferenceArticle;
use App\Entity\SleepingStockRequestInformation;
use App\Service\CacheService;
use App\Service\FormatService;
use App\Service\FormService;
use App\Service\UserService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route("/sleeping-stock", name: "sleeping_stock")]
class IndexController extends AbstractController {
    private const MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM = 50;

    #[Route("/", name: "_index", methods: [self::GET])]
    public function index(EntityManagerInterface $entityManager,
                          UserService            $userService,
                          FormatService          $formatService,
                          CacheService           $cacheService,
                          FormService            $formService): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $sleepingStockRequestInformationRepository = $entityManager->getRepository(SleepingStockRequestInformation::class);
        $user = $userService->getUser();

        $sleepingReferenceArticlesData = $referenceArticleRepository->findSleepingReferenceArticlesByManager(
            $user,
            $this::MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM,
        );

        $actionButtonsItems = $cacheService->get(CacheService::COLLECTION_SETTINGS, SleepingStockRequestInformation::class,static function () use ($sleepingStockRequestInformationRepository): array {
            return Stream::from($sleepingStockRequestInformationRepository->findAll())
                ->map(fn(SleepingStockRequestInformation $sleepingStockRequestInformation, int $index) => [
                    "label" => $sleepingStockRequestInformation->getButtonActionLabel(),
                    "value" => $sleepingStockRequestInformation->getId(),
                    "iconUrl" => $sleepingStockRequestInformation->getDeliveryRequestTemplate()?->getButtonIcon()?->getFullPath(),
                    "checked" => $index === 0
                ])
                ->toArray();
        });

        $referenceArticles = Stream::from($sleepingReferenceArticlesData["referenceArticles"])
            ->map(static function (array $referenceArticle) use ($formatService, $actionButtonsItems, $formService) {
                $switchs = $formService->macro(
                    "switch",
                    "choice-" . $referenceArticle["id"],
                    null,
                    true,
                    $actionButtonsItems,
                    [
                        "labelClass" => "full-width",
                    ]
                );
                $inputId = $formService->macro("hidden", "refId", $referenceArticle["id"]);
                $deleteRowButton = "<button class='btn btn-silent delete-row mr-2' data-id='{$referenceArticle["id"]}'><i class='wii-icon wii-icon-trash text-primary wii-icon-17px-danger'></i></button>";
                $lastMovementDate = new DateTime($referenceArticle["lastMovementDate"]);
                $maxStorageDate = (clone $lastMovementDate)->sub(new DateInterval("PT{$referenceArticle["maxStorageTime"]}S"));
                return [
                    "actions" => "<div class='d-flex full-width'> $deleteRowButton  $switchs<div>   $inputId",
                    "maxStorageDate" => $formatService->dateTime($maxStorageDate),
                    ...$referenceArticle
                ];
            })
            ->toArray();

        $countTotal = $sleepingReferenceArticlesData["countTotal"];

        return $this->render('sleeping_stock/index.html.twig', [
            "datatableInitialData" => json_encode([
                "data" => $referenceArticles,
                "recordsFiltered" => $countTotal,
                "recordsTotal" => $countTotal,
            ]),
        ]);
    }
}
