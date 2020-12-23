<?php

namespace App\Service;

use App\Entity\Dashboard\Page as DashboardPage;
use App\Entity\Dashboard\PageRow as DashboardPageRow;
use App\Entity\Dashboard\Component as DashboardComponent;
use App\Entity\Dashboard\ComponentType as DashboardComponentType;
use App\Helper\Stream;
use App\Repository\Dashboard\PageRepository as DashboardPageRepository;
use App\Repository\Dashboard\PageRowRepository as DashboardPageRowRepository;
use App\Repository\Dashboard\ComponentRepository as DashboardComponentRepository;
use App\Repository\Dashboard\ComponentTypeRepository as DashboardComponentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Traversable;

class DashboardSettingsService {

    private $entityManager;
    private $pageRepository;
    private $pageRowRepository;
    private $componentRepository;
    private $componentTypeRepository;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->pageRepository = $entityManager->getRepository(DashboardPage::class);
        $this->pageRowRepository = $entityManager->getRepository(DashboardPageRow::class);
        $this->componentRepository = $entityManager->getRepository(DashboardComponent::class);
        $this->componentTypeRepository = $entityManager->getRepository(DashboardComponentType::class);
    }

    public function byId($elements): array {
        return Stream::from($elements)
            ->keymap(function($element) {
                return [$element->getId(), $element];
            })
            ->toArray();
    }

    public function serialize(): string {
        $pages = Stream::from($this->pageRepository->findAll());

        $dashboards = $pages->map(function(DashboardPage $page) {
            return [
                "id" => $page->getId(),
                "name" => $page->getName(),
                "rows" => $page->getRows()->map(function(DashboardPageRow $row) {
                    return [
                        "id" => $row->getId(),
                        "size" => $row->getSize(),
                        "components" => Stream::from($row->getComponents())
                            ->keymap(function(DashboardComponent $component) {
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

    /**
     * @param string $class
     * @param array|null $json
     * @return array
     */
    public function getEntity(string $class, ?array $json): array {
        $set = $json["updated"] ?? false;
        if(!$json) {
            return [false, null];
        }

        if(isset($json["id"])) {
            $dashboard = $this->entityManager->getRepository($class)->find($json["id"]) ?? new $class();
        } else {
            $set = true;
            $dashboard = new $class();
            $this->entityManager->persist($dashboard);
        }

        return [$set, $dashboard ?? null];
    }

}
