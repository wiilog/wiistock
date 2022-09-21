<?php

namespace App\Controller\Settings;

use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Transport\TransportRound;
use App\Entity\Utilisateur;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\ImportService;
use App\Service\RefArticleDataService;
use App\Service\Transport\TransportRoundService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/parametrage")]
class DataExportController extends AbstractController {

    #[Route("/export/api", name: "export_api", options: ["expose" => true], methods: "POST")]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        //$exportRepository = $manager->getRepository(Export::class);
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_EXPORT, $user);
        //$queryResult = $exportRepository->findByParamsAndFilters($request->request, $filters);
        //$exports = $queryResult["data"];

        //TODO: supprimer
        $exports = [""];

        $rows = [];
        foreach ($exports as $export) {
            $rows[] = [
                "actions" => $this->renderView("settings/donnees/export/action.html.twig", [
                    "export" => new Export(),
                ]),
                "status" => null,
                "creationDate" => null,
                "startDate" => null,
                "endDate" => null,
                "nextRun" => null,
                "frequency" => null,
                "user" => null,
                "type" => null,
                "dataType" => null,
            ];
        }

        return $this->json([
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"] ?? 0,
            "recordsTotal" => $queryResult["total"] ?? 0,
        ]);
    }

    #[Route("/references/csv", name: "export_references", options: ["expose" => true], methods: "GET")]
    public function exportReferences(EntityManagerInterface $manager,
                                     CSVExportService       $csvService,
                                     UserService            $userService,
                                     RefArticleDataService  $refArticleDataService,
                                     FreeFieldService       $freeFieldService): StreamedResponse {
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($manager, [CategorieCL::REFERENCE_ARTICLE], [CategoryType::ARTICLE]);

        $header = array_merge([
            'reference',
            'libellé',
            'quantité',
            'type',
            'acheteur',
            'type quantité',
            'statut',
            'commentaire',
            'emplacement',
            'seuil sécurite',
            'seuil alerte',
            'prix unitaire',
            'code barre',
            'catégorie inventaire',
            'date dernier inventaire',
            'synchronisation nomade',
            'gestion de stock',
            'gestionnaire(s)',
            'Labels Fournisseurs',
            'Codes Fournisseurs',
            'Groupe de visibilité',
            'date de création',
            'crée par',
            'date de dérniere modification',
            'modifié par',
            "date dernier mouvement d'entrée",
            "date dernier mouvement de sortie",
        ], $freeFieldsConfig['freeFieldsHeader']);

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $user = $userService->getUser();

        return $csvService->streamResponse(function($output) use ($manager, $user, $freeFieldsConfig, $refArticleDataService) {
            $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);
            $managersByReference = $manager
                ->getRepository(Utilisateur::class)
                ->getUsernameManagersGroupByReference();

            $suppliersByReference = $manager
                ->getRepository(Fournisseur::class)
                ->getCodesAndLabelsGroupedByReference();

            $references = $referenceArticleRepository->iterateAll($user);
            foreach($references as $reference) {
                $refArticleDataService->putReferenceLine($output, $managersByReference, $reference, $suppliersByReference, $freeFieldsConfig);
            }
        }, "export-references-$today.csv", $header);
    }

    #[Route("/articles/csv", name: "export_articles", options: ["expose" => true], methods: "GET")]
    public function exportArticles(EntityManagerInterface $entityManager,
                                   FreeFieldService       $freeFieldService,
                                   ArticleDataService     $articleDataService,
                                   UserService            $userService,
                                   CSVExportService       $csvService): StreamedResponse {
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARTICLE], [CategoryType::ARTICLE]);
        $header = array_merge([
            'reference',
            'libelle',
            'quantité',
            'type',
            'statut',
            'commentaire',
            'emplacement',
            'code barre',
            'date dernier inventaire',
            'lot',
            'date d\'entrée en stock',
            'date de péremption',
            'groupe de visibilité'
        ], $freeFieldsConfig['freeFieldsHeader']);

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $user = $userService->getUser();

        return $csvService->streamResponse(function($output) use ($freeFieldsConfig, $entityManager, $csvService, $freeFieldService, $user, $articleDataService) {
            $articleRepository = $entityManager->getRepository(Article::class);

            $articles = $articleRepository->iterateAll($user);
            foreach($articles as $article) {
                $articleDataService->putArticleLine($output, $article, $freeFieldsConfig);
            }
        }, "export-articles-$today.csv", $header);
    }


    #[Route("/rounds/csv", name: "export_round", options: ["expose" => true], methods: "GET")]
    public function exportRounds(CSVExportService       $CSVExportService,
                                 TransportRoundService  $transportRoundService,
                                 EntityManagerInterface $entityManager,
                                 Request $request): Response {

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        $transportRoundRepository = $entityManager->getRepository(TransportRound::class);
        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $nameFile = "export-tournees-$today.csv";
        $csvHeader = $transportRoundService->getHeaderRoundAndRequestExport();

        $transportRoundsIterator = $transportRoundRepository->iterateFinishedTransportRounds($dateTimeMin, $dateTimeMax);
        return $CSVExportService->streamResponse(function ($output) use ($transportRoundService, $transportRoundsIterator) {
            /** @var TransportRound $round */
            foreach ($transportRoundsIterator as $round) {
                $transportRoundService->putLineRoundAndRequest($output, $round);
            }
        }, $nameFile, $csvHeader);
    }


    #[Route("/modale-new-export", name: "new_export_modal", options: ["expose" => true], methods: "GET")]
    public function getFirstModalContent(): JsonResponse
    {
        return new JsonResponse($this->renderView('settings/donnees/export/modalNewExportContent.html.twig'));
    }
}
