<?php

namespace App\Controller\Settings;

use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\ImportService;
use App\Service\RefArticleDataService;
use App\Service\ScheduledExportService;
use App\Service\Transport\TransportRoundService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/parametrage")]
class DataExportController extends AbstractController {

    public const EXPORT_UNIQUE = "exportUnique";
    public const EXPORT_SCHEDULED = "exportScheduled";

    public const ENTITY_REFERENCE = "references";
    public const ENTITY_ARTICLE = "articles";
    public const ENTITY_TRANSPORT_ROUNDS = "transportRounds";
    public const ENTITY_ARRIVALS = "arrivals";

    #[Route("/export/api", name: "settings_export_api", options: ["expose" => true], methods: "POST")]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $exportRepository = $manager->getRepository(Export::class);
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_EXPORT, $user);
        $queryResult = $exportRepository->findByParamsAndFilters($request->request, $filters);
        $exports = $queryResult["data"];

        $rows = [];
        /** @var Export $export */
        foreach ($exports as $export) {
            $rows[] = [
                "actions" => $this->renderView("settings/donnees/export/action.html.twig", [
                    "export" => $export,
                ]),
                "status" => $export->getStatus()->getNom(),
                "creationDate" => $export->getCreatedAt()->format("d/m/Y"),
                "startDate" => $export->getBeganAt()->format("d/m/Y"),
                "endDate" => $export->getEndedAt()->format("d/m/Y"),
                "nextRun" => $export->getNextExecution()?->format("d/m/Y"),
                "frequency" => $export->getExportScheduleRule()?->getFrequency(), //TODO: formatter = probleme de marwane
                "user" => FormatHelper::user($export->getCreator()),
                "type" => FormatHelper::type($export->getType()),
                "entity" => Export::ENTITY_LABELS[$export->getEntity()],
            ];
        }

        return $this->json([
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"] ?? 0,
            "recordsTotal" => $queryResult["total"] ?? 0,
        ]);
    }

    #[Route("/export/submit", name: "settings_submit_export", options: ["expose" => true], methods: "POST")]
    public function submitExport(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        if(!isset($data["entityToExport"])) {
            return $this->json([
                "success" => false,
                "msg" => "Veuillez sélectionner un type de données à exporter",
            ]);
        }

        $type = $data["exportTypeContainer"];
        $entity = $data["entityToExport"];

        if($type === self::EXPORT_UNIQUE) {
            //do nothing the export has been done in JS
        } else {

        }

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/export/unique/reference", name: "settings_export_references", options: ["expose" => true], methods: "GET")]
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

        return $csvService->streamResponse(function($output) use ($manager, $csvService, $user, $freeFieldsConfig, $refArticleDataService) {
            $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);
            $start = new DateTime();

            $managersByReference = $manager
                ->getRepository(Utilisateur::class)
                ->getUsernameManagersGroupByReference();

            $suppliersByReference = $manager
                ->getRepository(Fournisseur::class)
                ->getCodesAndLabelsGroupedByReference();
            dump("a!");

            $references = $referenceArticleRepository->iterateAll($user);
            foreach($references as $reference) {
                $refArticleDataService->putReferenceLine($output, $managersByReference, $reference, $suppliersByReference, $freeFieldsConfig);
            }
dump("ok?");
            $csvService->createUniqueExportLine(Export::ENTITY_REFERENCE, $start);
            dump("no");
        }, "export-references-$today.csv", $header);
    }

    #[Route("/export/unique/articles", name: "settings_export_articles", options: ["expose" => true], methods: "GET")]
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
            $start = new DateTime();

            $articles = $articleRepository->iterateAll($user);
            foreach($articles as $article) {
                $articleDataService->putArticleLine($output, $article, $freeFieldsConfig);
            }

            $csvService->createUniqueExportLine(Export::ENTITY_ARTICLE, $start);
            $entityManager->flush();
        }, "export-articles-$today.csv", $header);
    }


    #[Route("/export/unique/rounds", name: "settings_export_round", options: ["expose" => true], methods: "GET")]
    public function exportRounds(CSVExportService       $csvService,
                                 TransportRoundService  $transportRoundService,
                                 EntityManagerInterface $entityManager,
                                 Request                $request): Response {

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $transportRoundRepository = $entityManager->getRepository(TransportRound::class);
        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $nameFile = "export-tournees-$today.csv";
        $csvHeader = $transportRoundService->getHeaderRoundAndRequestExport();

        $transportRoundsIterator = $transportRoundRepository->iterateFinishedTransportRounds($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($entityManager, $csvService, $transportRoundService, $transportRoundsIterator) {
            $start = new DateTime();

            /** @var TransportRound $round */
            foreach ($transportRoundsIterator as $round) {
                $transportRoundService->putLineRoundAndRequest($output, $round);
            }

            $csvService->createUniqueExportLine(Export::ENTITY_DELIVERY_ROUND, $start);
            $entityManager->flush();
        }, $nameFile, $csvHeader);
    }


    #[Route("/modale-new-export", name: "new_export_modal", options: ["expose" => true], methods: "GET")]
    public function getFirstModalContent(): JsonResponse
    {
        return new JsonResponse($this->renderView('settings/donnees/export/modalNewExportContent.html.twig'));
    }

    #[Route("/annuler-export/{export}", name: "export_cancel", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    public function cancel(Export $export, Request $request,
                           EntityManagerInterface $manager,
                           ScheduledExportService $scheduledExportService): JsonResponse {
        $statusRepository = $manager->getRepository(Statut::class);

        $exportType = $export->getType();
        $exportStatus = $export->getStatus();
        if ($exportStatus && $exportType && $exportType->getLabel() == Type::LABEL_SCHEDULED_EXPORT && $exportStatus->getNom() == Export::STATUS_SCHEDULED) {
            $export->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::EXPORT, Export::STATUS_CANCELLED));
            $manager->flush();
            $scheduledExportService->saveScheduledExportsCache($manager);
        }

        return new JsonResponse();
    }
}
