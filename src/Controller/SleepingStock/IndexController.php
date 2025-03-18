<?php

namespace App\Controller\SleepingStock;

use App\Controller\AbstractController;
use App\Entity\ReferenceArticle;
use App\Service\FormatService;
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
                          FormatService          $formatService): Response {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $user = $user->getUser();

        $sleepingReferenceArticlesData = $referenceArticleRepository->findSleepingReferenceArticlesByManager(
            $user,
            100,
        );

        $referenceArticles = Stream::from($sleepingReferenceArticlesData["referenceArticles"])
            ->map(fn(array $referenceArticle) => [
                "actions" => "gaaga", //TODO PUT BUTTONS
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
