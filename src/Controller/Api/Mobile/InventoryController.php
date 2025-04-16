<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Zone;
use App\EventListener\ArticleQuantityNotifier;
use App\EventListener\RefArticleQuantityNotifier;
use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Repository\ArticleRepository;
use App\Service\ExceptionLoggerService;
use App\Service\FormatService;
use App\Service\InventoryService;
use App\Service\MailerService;
use App\Service\MobileApiService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use App\Annotation as Wii;

#[Route("/api/mobile")]
class InventoryController extends AbstractController {

    #[Route("/addInventoryEntries", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function addInventoryEntries(Request $request, EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $numberOfRowsInserted = 0;

        $entries = json_decode($request->request->get('entries'), true);
        $newAnomalies = [];

        foreach ($entries as $entry) {
            $mission = $inventoryMissionRepository->find($entry['mission_id']);
            $location = $emplacementRepository->findOneBy(['label' => $entry['location']]);

            $articleToInventory = $entry['is_ref']
                ? $referenceArticleRepository->findOneBy(['barCode' => $entry['bar_code']])
                : $articleRepository->findOneBy(['barCode' => $entry['bar_code']]);

            $criteriaInventoryEntry = ['mission' => $mission];

            if (isset($articleToInventory)) {
                if ($articleToInventory instanceof ReferenceArticle) {
                    $criteriaInventoryEntry['refArticle'] = $articleToInventory;
                } else { // ($articleToInventory instanceof Article)
                    $criteriaInventoryEntry['article'] = $articleToInventory;
                }
            }

            $inventoryEntry = $inventoryEntryRepository->findOneBy($criteriaInventoryEntry);

            // On inventorie l'article seulement si les infos sont valides et si aucun inventaire de l'article
            // n'a encore été fait sur cette mission
            if (isset($mission) &&
                isset($location) &&
                isset($articleToInventory) &&
                !isset($inventoryEntry)) {
                $newDate = new DateTime($entry['date']);
                $inventoryEntry = new InventoryEntry();
                $inventoryEntry
                    ->setMission($mission)
                    ->setDate($newDate)
                    ->setQuantity($entry['quantity'])
                    ->setOperator($nomadUser)
                    ->setLocation($location);

                if ($articleToInventory instanceof ReferenceArticle) {
                    $inventoryEntry->setRefArticle($articleToInventory);
                    $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantiteStock());
                } else {
                    $inventoryEntry->setArticle($articleToInventory);
                    $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantite());
                }
                $inventoryEntry->setAnomaly($isAnomaly);

                if (!$isAnomaly) {
                    $articleToInventory->setDateLastInventory($newDate);
                }
                $entityManager->persist($inventoryEntry);

                if ($inventoryEntry->getAnomaly()) {
                    $newAnomalies[] = $inventoryEntry;
                }
                $numberOfRowsInserted++;
            }
        }
        $entityManager->flush();

        $newAnomaliesIds = array_map(
            function (InventoryEntry $inventory) {
                return $inventory->getId();
            },
            $newAnomalies
        );

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $data['success'] = true;
        $data['data']['status'] = ($numberOfRowsInserted === 0)
            ? "Aucune saisie d'inventaire à synchroniser."
            : ($numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s);
        $data['data']['anomalies'] = $inventoryEntryRepository->getAnomalies(true, $newAnomaliesIds);

        return $this->json($data);
    }

    #[Route("/treatAnomalies", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function treatAnomalies(Request                $request,
                                   InventoryService       $inventoryService,
                                   ExceptionLoggerService $exceptionLoggerService,
                                   TranslationService     $translation): Response
    {

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;

        $anomalies = json_decode($request->request->get('anomalies'), true);
        $errors = [];
        $success = [];
        foreach ($anomalies as $anomaly) {
            try {
                $res = $inventoryService->doTreatAnomaly(
                    $anomaly['id'],
                    $anomaly['barcode'],
                    $anomaly['is_ref'],
                    $anomaly['quantity'],
                    $anomaly['comment'] ?? null,
                    $nomadUser
                );

                $success = array_merge($success, $res['treatedEntries']);

                $numberOfRowsInserted++;
            } catch (ArticleNotAvailableException|RequestNeedToBeProcessedException) {
                $errors[] = $anomaly['id'];
            } catch (Throwable $throwable) {
                $exceptionLoggerService->sendLog($throwable);
                throw $throwable;
            }
        }

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $data = [];
        $data['success'] = $success;
        $data['errors'] = $errors;
        $data['data']['status'] = ($numberOfRowsInserted === 0)
            ? ($anomalies > 0
                ? 'Une ou plusieus erreurs, des ' . mb_strtolower($translation->translate("Ordre", "Livraison", "Ordre de livraison", false)) . ' sont en cours pour ces articles ou ils ne sont pas disponibles, veuillez recharger vos données'
                : "Aucune anomalie d'inventaire à synchroniser.")
            : ($numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s);

        return $this->json($data);
    }


    #[Route("/finish-mission", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishMission(Request                $request,
                                  EntityManagerInterface $entityManager,
                                  InventoryService       $inventoryService,
                                  MailerService          $mailerService,
                                  TranslationService     $translationService,
                                  FormatService          $formatService,
                                  SettingsService        $settingsService,
                                  MobileApiService       $mobileApiService,
                                  Twig_Environment       $templating): Response
    {
        $missionRepository = $entityManager->getRepository(InventoryMission::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $rfidPrefix = $settingsService->getValue($entityManager, Setting::RFID_PREFIX) ?: '';

        $mission = $missionRepository->find($request->request->get('mission'));
        $locations = $mission->getInventoryLocationMissions()
            ->map(fn(InventoryLocationMission $line) => $line->getLocation())
            ->toArray();

        $tags = Stream::from(json_decode($request->request->get('tags')))
            ->filter(fn(string $tag) => str_starts_with($tag, $rfidPrefix))
            ->toArray();

        $validatedAtDates = Stream::from(json_decode($request->request->get('validatedAtDates'), true))
            ->keymap(fn($strDate, $locationId) => [$locationId, $formatService->parseDatetime($strDate)])
            ->toArray();

        $now = new DateTime('now');
        $validator = $this->getUser();

        $inventoryService->clearInventoryZone($mission);

        $zonesData = [];
        foreach ($mission->getInventoryLocationMissions() as $inventoryLocationMission) {
            if ($inventoryLocationMission->getLocation()?->getId()) {
                $zoneId = $inventoryLocationMission->getLocation()->getZone()?->getId();
                $locationId = $inventoryLocationMission->getLocation()?->getId();
                if (!isset($zonesData[$zoneId])) {
                    $zonesData[$zoneId] = [
                        "zone" => $inventoryLocationMission->getLocation()->getZone(),
                        "lines" => [],
                        "validatedAt" => null
                    ];
                }

                $zonesData[$zoneId]["lines"][] = $inventoryLocationMission;
                $zonesData[$zoneId]["validatedAt"] = $zonesData[$zoneId]["validatedAt"]
                    ?? $validatedAtDates[$locationId]
                    ?? null;
            }
        }

        // save stats
        foreach ($zonesData as $zoneDatum) {
            /** @var InventoryLocationMission[] $lines */
            $lines = $zoneDatum["lines"] ?? [];
            /** @var Zone $zone */
            $zone = $zoneDatum["zone"] ?? null;
            $validatedAt = $zoneDatum["validatedAt"] ?? null;
            if ($zone && $lines) {
                ['inventoryData' => $inventoryData] = $inventoryService->summarizeLocationInventory($entityManager, $mission, $zone, $tags);

                foreach ($lines as $line) {
                    $locationId = $line->getLocation()?->getId();
                    $scannedAt = $validatedAt ?? $now;
                    $inventoryDatum = $inventoryData[$locationId] ?? null;

                    $line
                        ->setOperator($validator)
                        ->setScannedAt($scannedAt)
                        ->setPercentage($inventoryDatum["ratio"] ?? 0)
                        ->setArticles($inventoryDatum["articles"] ?? [])
                        ->setDone(true);
                }
            }
        }

        RefArticleQuantityNotifier::$disableReferenceUpdate = true;
        ArticleQuantityNotifier::$disableArticleUpdate = true;
        ArticleQuantityNotifier::$referenceArticlesUpdating = true;

        $entityManager->flush();

        $articlesOnLocations = $articleRepository->findAvailableArticlesToInventory($tags, $locations, ['mode' => ArticleRepository::INVENTORY_MODE_FINISH]);

        $mobileApiService->treatInventoryArticles($entityManager, $articlesOnLocations, $tags, $validator, $now);

        $mission
            ->setValidatedAt($now)
            ->setValidator($validator)
            ->setDone(true);

        $entityManager->flush();

        RefArticleQuantityNotifier::$disableReferenceUpdate = false;
        ArticleQuantityNotifier::$disableArticleUpdate = false;
        ArticleQuantityNotifier::$referenceArticlesUpdating = false;

        if ($mission->getRequester()) {
            $mailerService->sendMail(
                $entityManager,
                $translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . "Validation d’une mission d’inventaire",
                $templating->render('mails/contents/mailInventoryMissionValidation.html.twig', [
                    'mission' => $mission,
                ]),
                $mission->getRequester()
            );
        }

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/inventory-missions/{inventoryMission}/summary/{zone}", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function rfidSummary(Request                $request,
                                InventoryMission       $inventoryMission,
                                Zone                   $zone,
                                EntityManagerInterface $entityManager,
                                InventoryService       $inventoryService): Response {


        $rfidTagsStr = $request->request->get('rfidTags');

        $data = $inventoryService->summarizeLocationInventory(
            $entityManager,
            $inventoryMission,
            $zone,
            json_decode($rfidTagsStr ?: '[]', true) ?: []
        );

        unset($data['inventoryData']);

        return $this->json([
            "success" => true,
            "data" => $data
        ]);
    }
}
