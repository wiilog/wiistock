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
    private const MAX_SLEEPING_REFERENCE_ARTICLES_ON_FORM = 100;

    #[Route("/", name: "_index", methods: [self::GET])]
    public function index(EntityManagerInterface $entityManager,
                          UserService            $user,
                          FormatService          $formatService,
                          CacheService           $cacheService,
                          FormService            $formService): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $sleepingStockRequestInformationRepository = $entityManager->getRepository(SleepingStockRequestInformation::class);
        $user = $user->getUser();

        $sleepingReferenceArticlesData = $referenceArticleRepository->findSleepingReferenceArticlesByManager(
            $user,
            50,
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
            ->map(fn(array $referenceArticle) => [
                "actions" => $formService->macro(
                    "switch",
                    "choice-" . $referenceArticle["id"],
                    null,
                    true,
                    $actionButtonsItems,
                    [
                        "labelClass" => "full-width",
                    ]
                ) . $formService->macro("hidden", "refId", $referenceArticle["id"]),
                "maxStorageDate" => $formatService->dateTime(
                    (new DateTime($referenceArticle["lastMovementDate"]))
                        ->sub(new DateInterval("PT{$referenceArticle["maxStorageTime"]}S"))
                ),
                ...$referenceArticle
            ])
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
