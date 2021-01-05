<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\LocationCluster;
use App\Entity\Menu;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Entity\Dashboard as Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class DashboardSettingsService {

    const MODE_EDIT = 0;
    const MODE_DISPLAY = 1;
    const MODE_EXTERNAL = 2;

    const UNKNOWN_COMPONENT = 'unknown-component';

    private $enCoursService;
    private $dashboardService;

    public function __construct(EnCoursService $enCoursService, DashboardService $dashboardService) {
        $this->enCoursService = $enCoursService;
        $this->dashboardService = $dashboardService;
    }

    public function serialize(EntityManagerInterface $entityManager, ?Utilisateur $user, int $mode): string {
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);

        if($mode === self::MODE_DISPLAY) {
            $pages = Stream::from($pageRepository->findAllowedToAccess($user));
        } else {
            $pages = Stream::from($pageRepository->findAll());
        }

        $pageIndex = 0;
        $dashboards = $pages->map(function(Dashboard\Page $page) use (&$pageIndex, $entityManager, $mode) {
            $rowIndex = 0;
            return [
                "id" => $page->getId(),
                "name" => $page->getName(),
                "index" => $pageIndex++,
                "rows" => $page->getRows()
                    ->map(function(Dashboard\PageRow $row) use (&$rowIndex, $entityManager, $mode) {
                        return [
                            "id" => $row->getId(),
                            "size" => $row->getSize(),
                            "index" => $rowIndex++,
                            "components" => Stream::from($row->getComponents())
                                ->map(function(Dashboard\Component $component) use ($entityManager, $mode) {
                                    $type = $component->getType();
                                    return [
                                        "id" => $component->getId(),
                                        "type" => $component->getType()->getId(),
                                        "index" => $component->getColumnIndex(),
                                        "template" => $type->getTemplate(),
                                        "config" => $component->getConfig(),
                                        "meterKey" => $type->getMeterKey(),
                                        "initData" => $this->serializeValues($entityManager, $component->getType(), $component->getConfig(), $mode === self::MODE_EDIT, $component->getMeter()),
                                    ];
                                })
                                ->toArray(),
                        ];
                    })
                    ->toArray(),
            ];
        })->toArray();

        return json_encode($dashboards);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @param DashboardMeter\Chart|DashboardMeter\Indicator $meter
     * @return array
     */
    public function serializeValues(EntityManagerInterface $entityManager,
                                    Dashboard\ComponentType $componentType,
                                    array $config,
                                    bool $example = false,
                                    $meter = null): array {
        $values = [];
        $meterKey = $componentType->getMeterKey();

        $values['title'] = !empty($config['title']) ? $config['title'] : $componentType->getName();
        $values['tooltip'] = !empty($config['tooltip']) ? $config['tooltip'] : $componentType->getHint();

        if ($meterKey === Dashboard\ComponentType::ONGOING_PACKS) {
            $values += $this->serializeOngoingPacks($entityManager, $componentType, $config, $example);
        }
        else if ($meterKey === Dashboard\ComponentType::CARRIER_TRACKING) {
            $values += $this->serializeCarrierIndicator($entityManager, $componentType, $config, $example);
        }
        else if ($meterKey === Dashboard\ComponentType::ENTRIES_TO_HANDLE) {
            $values['linesCountTooltip'] = !empty($config['linesCountTooltip']) ? $config['linesCountTooltip'] : '';
            $values['nextLocationTooltip'] = !empty($config['nextLocationTooltip']) ? $config['linesCountTooltip'] : '';
            $values += $componentType->getExampleValues();
        }
        else if ($meterKey === Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS
                 || $meterKey === Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS) {
            $values += $this->serializeArrivalsAndPacks($componentType, $config, $example, $meter);
        }
        else if ($meterKey === Dashboard\ComponentType::RECEIPT_ASSOCIATION) {
            $values += $this->serializeDailyReceptions($componentType, $config, $example);
        }
        else if ($meterKey === Dashboard\ComponentType::DAILY_ARRIVALS) {
            $values += $this->serializeDailyArrivals($componentType, $config, $example);
        } else if ($meterKey === Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS) {
            $values += $this->serializeDroppedPacks($entityManager, $componentType, $config, $example, $meter);
        } else if ($meterKey === Dashboard\ComponentType::PACK_TO_TREAT_FROM) {
            $values += $this->serializePacksToTreatFrom($entityManager, $componentType, $config, $example, $meter);
        } else {
            //TODO:remove
            $values += $componentType->getExampleValues();
        }

        return $values;
    }

    /**
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @return array
     */
    private function serializeDailyArrivals(Dashboard\ComponentType $componentType,
                                            array $config,
                                            bool $example = false): array {
        $values = $componentType->getExampleValues();
        if (!$example) {
            $chartValues = $this->dashboardService->getWeekArrival(
                isset($config['firstDay']) ? $config['firstDay'] : date("d/m/Y", strtotime('monday this week')),
                isset($config['lastDay']) ? $config['lastDay'] : date("d/m/Y", strtotime('sunday this week')),
                isset($config['beforeAfter']) ? $config['beforeAfter'] : 'now'
            );
            $chartData = Stream::from($chartValues['data'])
                ->map(function(array $value) {
                    return $value['count'];
                })->toArray();
            $values['chartData'] = $chartData;
            unset($chartValues['data']);
            $values += $chartValues;
        }
        return $values;
    }

    private function serializeOngoingPacks(EntityManagerInterface $manager,
                                           Dashboard\ComponentType $componentType,
                                           array $config,
                                           bool $example = false,
                                          DashboardMeter\Indicator $meter = null): array {
        $shouldShowTreatmentDelay = isset($config['withTreatmentDelay']) && $config['withTreatmentDelay'];
        $shouldShowLocationLabels = isset($config['withLocationLabels']) && $config['withLocationLabels'];

        if ($example || !$meter) {
            $values = $componentType->getExampleValues();

            if (!$shouldShowTreatmentDelay) {
                unset($values['delay']);
            }

            if (!$shouldShowLocationLabels || empty($config['locations'])) {
                unset($values['subtitle']);
            }
            else {
                if (!empty($config['locations'])) {
                    $locationRepository = $manager->getRepository(Emplacement::class);
                    $locations = $locationRepository->findBy(['id' => $config['locations']]);

                    if (empty($locations)) {
                        unset($values['subtitle']);
                    }
                    else {
                        $values['subtitle'] = FormatHelper::locations($locations);
                    }
                }
            }
        }
        else {
            $values = [
                'subtitle' => $meter->getSubtitle(),
                'delay' => $meter->getDelay(),
                'count' => $meter->getCount()
            ];
        }

        return $values;
    }

    private function serializeCarrierIndicator(EntityManagerInterface $manager,
                                               Dashboard\ComponentType $componentType,
                                               array $config,
                                               bool $example = false): array {
        $values = [];

        if (!empty($config["carriers"])) {
            $carrierRepository = $manager->getRepository(Transporteur::class);

            if ($example) {
                $carriers = $carrierRepository->findByIds($config['carriers']);
            } else {
                $carriers = $carrierRepository->getDailyArrivalCarriersLabel($config['carriers']);
            }

            $values["carriers"] = FormatHelper::carriers($carriers);
        }
        else if($example) {
            $values = $componentType->getExampleValues();
        }
        else {
            $values["carriers"] = '';
        }

        return $values;
    }

    public function serializeArrivalsAndPacks(Dashboard\ComponentType $componentType,
                                              array $config,
                                              bool $example = false,
                                              DashboardMeter\Chart $meterChart = null): array {
        $values = $example ? $componentType->getExampleValues() : [];

        $displayPackNatures = $config['displayPackNatures'] ?? false;

        $values['stack'] = true;

        $dailyRequest = ($componentType->getMeterKey() === Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS);
        if($dailyRequest) {
            $scale = $config['daysNumber'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        } else {
            $scale = DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE;
        }

        // arrivals column
        if (!$example && isset($meterChart)) {
            $values['chartData'] = $meterChart->getData();
        }
        else {
            $chartData = $values['chartData'] ?? [];
            $keysToKeep = array_slice(array_keys($chartData), 0, $scale);
            $keysToKeep[] = 'stack';
            $chartData = Stream::from($keysToKeep)
                ->reduce(function (array $carry, string $key) use ($chartData) {
                    if (isset($chartData[$key])) {
                        $carry[$key] = $chartData[$key];
                    }
                    return $carry;
                }, []);

            // packs column
            if(isset($chartData['stack'])) {
                if ($displayPackNatures && $scale) {
                    foreach ($chartData['stack'] as $natureData) {
                        $natureData['data'] = array_slice($natureData['data'], 0, $scale);
                    }
                } else if (isset($chartData['stack'])) {
                    unset($chartData['stack']);
                }
            }

            $values['chartData'] = $chartData;
        }

        return $values;
    }

    private function serializeDroppedPacks(EntityManagerInterface $entityManager,
                                           Dashboard\ComponentType $componentType,
                                           array $config,
                                           bool $example = false,
                                           ?Dashboard\Meter\Chart $chart = null): array {

        if (!$example) {
            return ["chartData" => $chart->getData()];
        } else {
            return $componentType->getExampleValues();
        }
    }

    private function serializePacksToTreatFrom(EntityManagerInterface $entityManager,
                                           Dashboard\ComponentType $componentType,
                                           array $config,
                                           bool $example = false,
                                           ?Dashboard\Meter\Chart $chart = null): array {

        if (!$example) {
            return $chart->getData();
        } else {
            return $componentType->getExampleValues();
        }
    }

    private function serializeDailyReceptions(Dashboard\ComponentType $componentType,
                                              array $config,
                                              bool $example = false): array {

        $values = $componentType->getExampleValues();
        if (!$example) {
            $chartValues = $this->dashboardService->getWeekAssoc(
                isset($config['firstDay']) ? $config['firstDay'] : date("d/m/Y", strtotime('monday this week')),
                isset($config['lastDay']) ? $config['lastDay'] : date("d/m/Y", strtotime('sunday this week')),
                isset($config['beforeAfter']) ? $config['beforeAfter'] : 'now'
            );

            $values['chartData'] = $chartValues['data'];

            unset($chartValues['data']);
            $values += $chartValues;
        }
        return $values;
    }

    public function save(EntityManagerInterface $entityManager, array $jsonDashboard) {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);
        $pageRowRepository = $entityManager->getRepository(Dashboard\PageRow::class);
        $componentRepository = $entityManager->getRepository(Dashboard\Component::class);

        $pagesToDelete = $this->byId($pageRepository->findAll());
        $pageRowsToDelete = $this->byId($pageRowRepository->findAll());
        $componentsToDelete = $this->byId($componentRepository->findAll());

        foreach ($jsonDashboard as $jsonPage) {
            [$updatePage, $page] = $this->getEntity($entityManager, Dashboard\Page::class, $jsonPage);
            if ($page) {
                if ($updatePage) {
                    $page->setName($jsonPage["name"]);

                    foreach ($jsonPage["rows"] as $jsonRow) {
                        [$updateRow, $row] = $this->getEntity($entityManager, Dashboard\PageRow::class, $jsonRow);
                        if ($row) {
                            if ($updateRow) {
                                $row->setPage($page);
                                $row->setSize($jsonRow["size"]);

                                foreach ($jsonRow["components"] as $jsonComponent) {
                                    [$updateComponent, $component] = $this->getEntity($entityManager, Dashboard\Component::class, $jsonComponent);

                                    if ($updateComponent && $component) {
                                        $type = $componentTypeRepository->find($jsonComponent["type"]);
                                        if (!$type) {
                                            throw new InvalidArgumentException(self::UNKNOWN_COMPONENT . '-' . $jsonComponent["type"]);
                                        }
                                        $component->setType($type);
                                        $component->setRow($row);
                                        $component->setColumnIndex($jsonComponent["index"]);
                                        $component->setConfig($jsonComponent["config"]);
                                    }

                                    if (isset($jsonComponent["id"], $componentsToDelete[$jsonComponent["id"]])) {
                                        unset($componentsToDelete[$jsonComponent["id"]]);
                                    }
                                }
                            } else {
                                $this->ignoreRow($row, $componentsToDelete);
                            }
                        }

                        if (isset($jsonRow["id"], $pageRowsToDelete[$jsonRow["id"]])) {
                            unset($pageRowsToDelete[$jsonRow["id"]]);
                        }
                    }
                } else {
                    $this->ignorePage($page, $pageRowsToDelete, $componentsToDelete);
                }
            }

            if (isset($jsonPage["id"], $pagesToDelete[$jsonPage["id"]])) {
                unset($pagesToDelete[$jsonPage["id"]]);
            }
        }
        Stream::from($pagesToDelete, $pageRowsToDelete, $componentsToDelete)
            ->each(function($entity) use ($entityManager) {
                $entityManager->remove($entity);
            });
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $class
     * @param array|null $json
     * @return array
     */
    private function getEntity(EntityManagerInterface $entityManager,
                               string $class,
                               ?array $json): array {
        $set = $json["updated"] ?? false;
        if (!$json) {
            return [false, null];
        }

        if (isset($json["id"])) {
            $entity = $entityManager->find($class, $json["id"]);
        }

        if (!isset($entity)) {
            $set = true;
            $entity = new $class();
            if ($entity instanceof Dashboard\Page) {
                $menu = $entityManager->getRepository(Menu::class)
                    ->findOneBy(["label" => Menu::DASHBOARDS]);

                $action = new Action();
                $action->setMenu($menu);

                $entity->setAction($action);
            }

            $entityManager->persist($entity);
        }

        return [$set, $entity ?? null];
    }

    private function byId($elements): array {
        return Stream::from($elements)
            ->keymap(function($element) {
                return [$element->getId(), $element];
            })
            ->toArray();
    }

    private function ignorePage(Dashboard\Page $page,
                                array &$pageRowsToDelete,
                                array &$componentsToDelete) {
        foreach ($page->getRows() as $row) {
            $rowId = $row->getId();
            if (isset($pageRowsToDelete[$rowId])) {
                unset($pageRowsToDelete[$rowId]);
            }
            $this->ignoreRow($row, $componentsToDelete);
        }
    }

    private function ignoreRow(Dashboard\PageRow $component,
                               array &$componentsToDelete) {

        foreach ($component->getComponents() as $component) {
            $componentId = $component->getId();
            if (isset($componentsToDelete[$componentId])) {
                unset($componentsToDelete[$componentId]);
            }
        }
    }

}
