<?php


namespace App\Service;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\InventoryMissionPlan;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class InvMissionService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public Security $security;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public UserService $userService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public InventoryService $inventoryService;

    public function getDataForMissionsDatatable($params = null): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_MISSIONS, $this->security->getUser());
        $queryResult = $inventoryMissionRepository->findMissionsByParamsAndFilters($params, $filters);

        $missions = $queryResult['data'];

        $rows = [];
        foreach ($missions as $mission) {
            $rows[] = $this->dataRowMission($mission);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowMission(InventoryMission $mission): array {
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

		$nbArtInMission = $articleRepository->countByMission($mission);
		$nbRefInMission = $referenceArticleRepository->countByMission($mission);
		$nbEntriesInMission = $inventoryEntryRepository->count(['mission' => $mission]);
        $treatedLocations = $mission->getInventoryLocationMissions()->filter(fn(InventoryLocationMission $line) => $line->isDone())->count();
        $lines = $mission->getInventoryLocationMissions()->count();
        $rateBar = $mission->getType() === InventoryMission::LOCATION_TYPE ?
            ($lines === 0 ? 0 : ($treatedLocations / $lines)*100)
            : ((($nbArtInMission + $nbRefInMission) != 0)
                ? ($nbEntriesInMission * 100 / ($nbArtInMission + $nbRefInMission))
                : 0);

        return [
            'name' => $mission->getName() ?? '',
            'start' => $this->formatService->date($mission->getStartPrevDate()),
            'end' => $this->formatService->date($mission->getEndPrevDate()),
            'anomaly' => $inventoryMissionRepository->countAnomaliesByMission($mission) > 0,
            'rate' => $this->templating->render('inventaire/datatableMissionsBar.html.twig', [
                'rateBar' => $rateBar,
            ]),
            'type' => $mission->getType() ? InventoryMission::TYPES_LABEL[$mission->getType()] ?? '' : '',
            'requester' => $this->formatService->user($mission->getRequester()),
            'actions' => $this->templating->render('inventaire/datatableMissionsRow.html.twig', [
                'id' => $mission->getId(),
                'type' => $mission->getType(),
            ]),
        ];
    }

    public function getDataForOneMissionDatatable(InventoryMission $mission,
                                                  ParameterBag     $params = null,
                                                                   $isArticle = true): array {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_SHOW_MISSION, $this->security->getUser());

        $queryResult = $isArticle
            ? $inventoryMissionRepository->findArtByMissionAndParamsAndFilters($mission, $params, $filters)
            : $inventoryMissionRepository->findRefByMissionAndParamsAndFilters($mission, $params, $filters);

        $refArray = $queryResult['data'];

        $rows = [];
        foreach ($refArray as $data) {
            $rows[] = $isArticle
                ? $this->dataRowArtMission($data, $mission)
                : $this->dataRowRefMission($data, $mission);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function getDataForOneLocationMissionDatatable(EntityManagerInterface $entityManager,
                                                          InventoryMission       $mission,
                                                          ParameterBag           $params = null) {
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_SHOW_MISSION, $this->security->getUser());

        $result = $inventoryLocationMissionRepository->findDataByMission($mission, $params, $filters);

        return [
            "data" => Stream::from($result['data'])
                ->map(fn(InventoryLocationMission $inventoryLocation) => [
                    'zone' => $this->formatService->zone($inventoryLocation->getLocation()?->getZone()),
                    'location' => $this->formatService->location($inventoryLocation->getLocation()),
                    'date' => $mission->isDone() ? $this->formatService->date($inventoryLocation->getScannedAt()) : null,
                    'operator' => $mission->isDone() ? $this->formatService->user($inventoryLocation->getOperator()) : null,
                    'percentage' => $mission->isDone() ? (($inventoryLocation->getPercentage() ?: 0) . '%') : null,
                    'actions' => $mission->isDone()
                        ? $this->templating->render("utils/action-buttons/dropdown.html.twig", [
                            "actions" => [
                                [
                                    "title" => "Voir les articles",
                                    "actionOnClick" => true,
                                    "attributes" => [
                                        "data-id" => $inventoryLocation->getId(),
                                        "onclick" => "openShowScannedArticlesModal($(this))",
                                    ],
                                ],
                                [
                                    "title" => "Voir les articles",
                                    "icon" => "fas fa-eye",
                                    "attributes" => [
                                        "class" => "pointer",
                                        "data-id" => $inventoryLocation->getId(),
                                        "onclick" => "openShowScannedArticlesModal($(this))",
                                    ],
                                ],
                            ],
                        ])
                        : null,
                ])
                ->toArray(),
            'recordsFiltered' => $result['recordsFiltered'],
            'recordsTotal' => $result['recordsTotal']
        ];

    }

    public function getDataForArticlesDatatable(EntityManagerInterface   $entityManager,
                                                InventoryLocationMission $mission,
                                                ParameterBag             $params = null) {
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);

        return $inventoryLocationMissionRepository->getArticlesByInventoryLocationMission($mission, $params);
    }

    public function dataRowRefMission(ReferenceArticle $ref, InventoryMission $mission): array {
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $inventoryEntry = $inventoryEntryRepository->findOneBy(['refArticle' => $ref, 'mission' => $mission]);
        $refDateAndQuantity = $inventoryEntryRepository->getEntryByMissionAndRefArticle($mission, $ref);

        $row = $this->dataRowMissionArtRef(
            $ref->getEmplacement(),
            $ref->getReference(),
            $ref->getBarCode(),
            $ref->getLibelle(),
            (!empty($refDateAndQuantity) && isset($refDateAndQuantity['date'])) ? $refDateAndQuantity['date'] : null,
            $inventoryEntryRepository->countInventoryAnomaliesByRef($ref) > 0 ? 'oui' : ($refDateAndQuantity ? 'non' : '-'),
            $ref->getQuantiteStock(),
            (!empty($refDateAndQuantity) && isset($refDateAndQuantity['quantity'])) ? $refDateAndQuantity['quantity'] : null,
        );

        $actionData = [
            "referenceId" => $ref->getId(),
            "missionId" => $mission->getId(),
        ];

        if ($inventoryEntry) {
            $actionData['inventoryEntryId'] = $inventoryEntry->getId();
        }

        $row['Actions'] = $this->templating->render('saisie_inventaire/inventoryEntryRefArticleRow.html.twig', [
            'inventoryData' => Stream::from($actionData)
                ->map(fn(string $value, string $key) => "{$key}: {$value}")
                ->join(';'),
        ]);

        return $row;
    }

	/**
	 * @param Article $art
	 * @param InventoryMission $mission
	 * @return array
     */
    public function dataRowArtMission($art, $mission) {
        $inventoryEntryRepository = $this->entityManager->getRepository(InventoryEntry::class);

        $artDateAndQuantity = $inventoryEntryRepository->getEntryByMissionAndArticle($mission, $art);
        return $this->dataRowMissionArtRef(
            $art->getEmplacement(),
            $art->getArticleFournisseur()->getReferenceArticle(),
            $art->getBarCode(),
            $art->getlabel(),
            !empty($artDateAndQuantity) ? $artDateAndQuantity['date'] : null,
            $inventoryEntryRepository->countInventoryAnomaliesByArt($art) > 0 ? 'oui' : ($artDateAndQuantity ? 'non' : '-'),
            $art->getQuantite(),
            (!empty($artDateAndQuantity) && isset($artDateAndQuantity['quantity'])) ? $artDateAndQuantity['quantity'] : null,
            $art->getCurrentLogisticUnit()
        );
    }

    private function dataRowMissionArtRef(?Emplacement       $emplacement,
                                          ?string            $reference,
                                          ?string            $codeBarre,
                                          ?string            $label,
                                          ?DateTimeInterface $date,
                                          ?string            $anomaly,
                                          ?string            $quantiteStock,
                                          ?string            $quantiteComptee,
                                          ?Pack              $pack = null): array {
        if ($emplacement) {
            $location = $emplacement->getLabel();
            $emptyLocation = false;
        }
        else {
            $location = '<i class="fas fa-exclamation-triangle red" title="Aucun emplacement défini : n\'apparaîtra sur le nomade."></i>';
            $emptyLocation = true;
        }

        return [
            'reference' => $reference,
            'barcode' => $codeBarre,
            'label' => $label,
            'logisticUnit' => $pack ? $pack->getCode() : '',
            'location' => $location,
            'date' => isset($date) ? $date->format('d/m/Y') : '',
            'anomaly' => $anomaly,
            'stockQuantity' => $quantiteStock,
            'countedQuantity' => $quantiteComptee,
            'emptyLocation' => $emptyLocation,
        ];
    }

    public function generateMission(EntityManagerInterface $entityManager,
                                    InventoryMissionPlan   $inventoryMissionPlan,
                                    DateTime               $taskExecution): void {
        $now = new DateTime();
        $now->setTime($now->format('H'), $now->format('i'), 0, 0);

        $inventoryMission = new InventoryMission();
        $inventoryMission->setCreator($inventoryMissionPlan);
        $inventoryMission->setDescription($inventoryMissionPlan->getDescription());
        $inventoryMission->setName($inventoryMissionPlan->getLabel());
        $inventoryMission->setCreatedAt($now);
        $inventoryMission->setStartPrevDate(new DateTime("now"));
        $inventoryMission->setEndPrevDate(new DateTime("now +{$inventoryMissionPlan->getDuration()} {$inventoryMissionPlan->getDurationUnit()}"));
        $inventoryMission->setRequester($inventoryMissionPlan->getRequester());
        $inventoryMission->setType($inventoryMissionPlan->getMissionType());
        $inventoryMission->setDone(false);

        if ($inventoryMission->getType() === InventoryMission::LOCATION_TYPE) {
            foreach ($inventoryMissionPlan->getLocations() as $location) {
                $missionLocation = new InventoryLocationMission();
                $missionLocation->setLocation($location);
                $missionLocation->setInventoryMission($inventoryMission);
                $missionLocation->setDone(false);
                $this->entityManager->persist($missionLocation);
            }
        } else {
            $this->createMissionArticleType($inventoryMissionPlan, $inventoryMission);
        }

        $inventoryMissionPlan->addCreatedMission($inventoryMission);
        $inventoryMissionPlan->getScheduleRule()?->setLastRun($taskExecution);
        $entityManager->persist($inventoryMission);
        $entityManager->flush();
    }

    public function createMissionArticleType(InventoryMissionPlan $rule, InventoryMission $mission): void
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);

        $frequencies = Stream::from($rule->getCategories())
            ->map(fn(InventoryCategory $category) => $category->getFrequency())
            ->toArray();

        $this->entityManager->persist($mission);

        foreach ($frequencies as $frequency) {
            // récupération des réf et articles à inventorier (fonction date dernier inventaire)
            $referencesToInventory = $referenceArticleRepository->iterateReferencesToInventory($frequency,
                $mission);
            $articlesToInventory = $articleRepository->iterateArticlesToInventory($frequency, $mission);

            $treated = 0;

            foreach ($referencesToInventory as $reference) {
                $reference->addInventoryMission($mission);
                $treated++;
                if ($treated >= 500) {
                    $treated = 0;
                    $this->entityManager->flush();
                }
            }

            $treated = 0;
            $this->entityManager->flush();

            /** @var Article $article */
            foreach ($articlesToInventory as $article) {
                $article->addInventoryMission($mission);
                $treated++;
                if ($treated >= 500) {
                    $treated = 0;
                    $this->entityManager->flush();
                }
            }

            $this->entityManager->flush();

            // lissage des réf et articles jamais inventoriés
            $nbRefAndArtToInv = $referenceArticleRepository->countActiveByFrequencyWithoutDateInventory($frequency);
            $nbToInv = $nbRefAndArtToInv['nbRa'] + $nbRefAndArtToInv['nbA'];

            $limit = (int)($nbToInv / ($frequency->getNbMonths() * 4));

            $listRefNextMission = $referenceArticleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency,
                $limit / 2);
            $listArtNextMission = $articleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency,
                $limit / 2);

            /** @var ReferenceArticle $ref */
            foreach ($listRefNextMission as $ref) {
                $alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($ref, $mission, true);
                if (!$alreadyInMission) {
                    $ref->addInventoryMission($mission);
                }
            }
            /** @var Article $art */
            foreach ($listArtNextMission as $art) {
                $alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($art, $mission, false);
                if (!$alreadyInMission) {
                    $art->addInventoryMission($mission);
                }
            }
        }
    }
}
