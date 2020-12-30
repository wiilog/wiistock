<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\Transporteur;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Entity\Dashboard as Dashboard;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class DashboardSettingsService {

    const UNKNOWN_COMPONENT = 'unknown-component';

    public function serialize(EntityManagerInterface $entityManager, bool $example = false): string {
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);
        $pages = Stream::from($pageRepository->findAll());

        $pageIndex = 0;
        $dashboards = $pages->map(function(Dashboard\Page $page) use (&$pageIndex, $entityManager, $example) {
            $rowIndex = 0;
            return [
                "id" => $page->getId(),
                "name" => $page->getName(),
                "index" => $pageIndex++,
                "rows" => $page->getRows()
                    ->map(function(Dashboard\PageRow $row) use (&$rowIndex, $entityManager, $example) {
                        return [
                            "id" => $row->getId(),
                            "size" => $row->getSize(),
                            "index" => $rowIndex++,
                            "components" => Stream::from($row->getComponents())
                                ->map(function(Dashboard\Component $component) use ($entityManager, $example) {
                                    $type = $component->getType();
                                    return [
                                        "id" => $component->getId(),
                                        "type" => $component->getType()->getId(),
                                        "index" => $component->getColumnIndex(),
                                        "template" => $type->getTemplate(),
                                        "config" => $component->getConfig(),
                                        "meterKey" => $type->getMeterKey(),
                                        "initData" => $this->serializeValues($entityManager, $component->getType(), $component->getConfig(), $example),
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
     * @return array
     */
    public function serializeValues(EntityManagerInterface $entityManager,
                                    Dashboard\ComponentType $componentType,
                                    array $config,
                                    bool $example = false): array {
        $values = [];
        $meterKey = $componentType->getMeterKey();

        $values['title'] = !empty($config['title']) ? $config['title'] : $componentType->getName();
        $values['tooltip'] = !empty($config['tooltip']) ? $config['tooltip'] : $componentType->getHint();

        if ($meterKey === Dashboard\ComponentType::ONGOING_PACKS) {
            $values += $this->serializeOngoingPacks($entityManager, $componentType, $config, $example);
        } else if ($meterKey === Dashboard\ComponentType::CARRIER_TRACKING) {
            $values += $this->serializeCarrierIndicator($entityManager, $componentType, $config, $example);
        } else {
            //TODO:remove
            $values += $componentType->getExampleValues();
        }

        return $values;
    }

    private function serializeOngoingPacks(EntityManagerInterface $manager,
                                           Dashboard\ComponentType $componentType,
                                           array $config,
                                           bool $example = false): array {
        $values = [];
        $shouldShowTreatmentDelay = isset($config['withTreatmentDelay']) && $config['withTreatmentDelay'];
        $shouldShowLocationLabels = isset($config['withLocationLabels']) && $config['withLocationLabels'];

//      if($example) {
            $values = $componentType->getExampleValues();

            if(!$shouldShowTreatmentDelay) {
                unset($values['delay']);
            }

            if(!$shouldShowLocationLabels || empty($config['locations'])) {
                unset($values['subtitle']);
            }

            if(!empty($config['locations'])) {
                $locationRepository = $manager->getRepository(Emplacement::class);
                $locations = $locationRepository->findBy(['id' => $config['locations']]);

                if(empty($locations)) {
                    unset($values['subtitle']);
                } else {
                    $values['subtitle'] = FormatHelper::locations($locations);
                }
            }
//      } else {
//          TODO: get real values
//      }

        return $values;
    }

    private function serializeCarrierIndicator(EntityManagerInterface $manager,
                                               Dashboard\ComponentType $componentType,
                                               array $config,
                                               bool $example = false): array {
        $values = [];

        if (isset($config["carriers"])) {
            $carrierRepository = $manager->getRepository(Transporteur::class);

            if($example) {
                $carriers = $carrierRepository->findByIds($config['carriers']);
            } else {
                $carriers = $carrierRepository->getDailyArrivalCarriersLabel($config['carriers']);
            }

            $values["carriers"] = FormatHelper::locations($carriers);
        } else {
            $values = $componentType->getExampleValues();
        }
        dump($values);
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

        foreach($jsonDashboard as $jsonPage) {
            [$updatePage, $page] = $this->getEntity($entityManager, Dashboard\Page::class, $jsonPage);

            if ($page) {
                if ($updatePage) {
                    $page->setName($jsonPage["name"]);

                    foreach($jsonPage["rows"] as $jsonRow) {
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
                            }
                            else {
                                $this->ignoreRow($row, $componentsToDelete);
                            }
                        }

                        if(isset($jsonRow["id"], $pageRowsToDelete[$jsonRow["id"]])) {
                            unset($pageRowsToDelete[$jsonRow["id"]]);
                        }
                    }
                }
                else {
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
        if(!$json) {
            return [false, null];
        }

        if(isset($json["id"])) {
            $dashboard = $entityManager->find($class, $json["id"]);
        }

        if (!isset($dashboard)) {
            $set = true;
            $dashboard = new $class();
            $entityManager->persist($dashboard);
        }

        return [$set, $dashboard ?? null];
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
