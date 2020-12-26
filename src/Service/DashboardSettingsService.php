<?php

namespace App\Service;

use App\Helper\Stream;
use App\Entity\Dashboard as Dashboard;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class DashboardSettingsService {
    const UNKNOWN_COMPONENT = 'unknown-component';

    public function serialize(EntityManagerInterface $entityManager): string {
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);
        $pages = Stream::from($pageRepository->findAll());

        $dashboards = $pages->map(function(Dashboard\Page $page) {
            return [
                "id" => $page->getId(),
                "name" => $page->getName(),
                "rows" => $page->getRows()->map(function(Dashboard\PageRow $row) {
                    return [
                        "id" => $row->getId(),
                        "size" => $row->getSize(),
                        "components" => Stream::from($row->getComponents())
                            ->keymap(function(Dashboard\Component $component) {
                                $json = [
                                    "id" => $component->getId(),
                                    "type" => $component->getType()->getId(),
                                    "title" => $component->getTitle(),
                                    "index" => $component->getColumnIndex(),
                                    "config" => $component->getConfig(),
                                ];

                                return [$component->getColumnIndex(), $json];
                            })
                            ->toArray(),
                    ];
                })->toArray(),
            ];
        })->toArray();

        return json_encode($dashboards);
    }

    public function treatJsonDashboard(EntityManagerInterface $entityManager,
                                       $jsonDashboard) {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);
        $pageRowRepository = $entityManager->getRepository(Dashboard\PageRow::class);
        $componentRepository = $entityManager->getRepository(Dashboard\Component::class);

        $pagesToDelete = $this->byId($pageRepository->findAll());
        $pageRowsToDelete = $this->byId($pageRowRepository->findAll());
        $componentsToDelete = $this->byId($componentRepository->findAll());

        foreach($jsonDashboard as $jsonPage) {
            [$update, $page] = $this->getEntity($entityManager, Dashboard\Page::class, $jsonPage);
            if ($update && $page) {
                $page->setName($jsonPage["name"]);

                foreach($jsonPage["rows"] as $jsonRow) {
                    [$update, $row] = $this->getEntity($entityManager, Dashboard\PageRow::class, $jsonRow);
                    if($update && $row) {
                        $row->setPage($page);
                        $row->setSize($jsonRow["size"]);
                        foreach($jsonRow["components"] as $jsonComponent) {
                            [$update, $component] = $this->getEntity($entityManager, Dashboard\Component::class, $jsonComponent);
                            if($update && $component) {
                                $type = $componentTypeRepository->find($jsonComponent["type"]);
                                if(!$type) {
                                    throw new InvalidArgumentException(self::UNKNOWN_COMPONENT . '-' . $jsonComponent["type"]);
                                }

                                $component->setType($type);
                                $component->setRow($row);
                                $component->setTitle($jsonComponent["title"]);
                                $component->setColumnIndex($jsonComponent["index"]);
                                $component->setConfig($jsonComponent["config"]);
                            }

                            if(isset($jsonComponent["id"], $componentsToDelete[$jsonComponent["id"]])) {
                                unset($componentsToDelete[$jsonComponent["id"]]);
                            }
                        }
                    }

                    if(isset($jsonRow["id"], $pageRowsToDelete[$jsonRow["id"]])) {
                        unset($pageRowsToDelete[$jsonRow["id"]]);
                    }
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
}
