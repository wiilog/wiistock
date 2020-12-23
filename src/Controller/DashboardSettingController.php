<?php

namespace App\Controller;

use App\Entity\Dashboard\Component;
use App\Entity\Dashboard\Page as DashboardPage;
use App\Entity\Dashboard\PageRow;
use App\Entity\Dashboard\PageRow as DashboardPageRow;
use App\Entity\Dashboard\Component as DashboardComponent;
use App\Entity\Dashboard\ComponentType as DashboardComponentType;
use App\Helper\Stream;
use App\Repository\Dashboard\PageRepository as DashboardPageRepository;
use App\Repository\Dashboard\PageRowRepository as DashboardPageRowRepository;
use App\Repository\Dashboard\ComponentRepository as DashboardComponentRepository;
use App\Repository\Dashboard\ComponentTypeRepository as DashboardComponentTypeRepository;
use App\Service\DashboardSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage-global")
 */
class DashboardSettingController extends AbstractController {

    /**
     * @Route("/dashboard", name="dashboard_settings")
     */
    public function settings(DashboardSettingsService $service): Response {
        return $this->render("dashboard/settings.html.twig", [
            "dashboards" => $service->serialize(),
        ]);
    }

    /**
     * @Route("/dashboard/save", name="save_dashboard_settings", options={"expose"=true})
     */
    public function save(Request $request, EntityManagerInterface $manager, DashboardSettingsService $service): Response {
        $pageRepository = $manager->getRepository(DashboardPage::class);
        $pageRowRepository = $manager->getRepository(DashboardPageRow::class);
        $componentRepository = $manager->getRepository(DashboardComponent::class);
        $componentTypeRepository = $manager->getRepository(DashboardComponentType::class);
        $dashboards = json_decode($request->request->get("dashboards"), true);

        $pagesToDelete = $service->byId($pageRepository->findAll());
        $pageRowsToDelete = $service->byId($pageRowRepository->findAll());
        $componentsToDelete = $service->byId($componentRepository->findAll());

        foreach($dashboards as $jsonPage) {
            [$update, $page] = $service->getEntity(DashboardPage::class, $jsonPage);
            if($update && $page) {
                $page->setName($jsonPage["name"]);
            }

            if(isset($jsonPage["id"], $pagesToDelete[$jsonPage["id"]])) {
                dump($jsonPage["id"], $pagesToDelete[$jsonPage["id"]]);
                unset($pagesToDelete[$jsonPage["id"]]);
            } else if(isset($jsonPage["id"])) {
                dump($jsonPage["id"]);
            }

            foreach($jsonPage["rows"] as $jsonRow) {
                [$update, $row] = $service->getEntity(DashboardPageRow::class, $jsonRow);
                if($update && $row) {
                    $row->setPage($page);
                    $row->setSize($jsonRow["size"]);
                }

                if(isset($jsonRow["id"], $pageRowsToDelete[$jsonRow["id"]])) {
                    unset($pageRowsToDelete[$jsonRow["id"]]);
                }

                foreach($jsonRow["components"] as $jsonComponent) {
                    [$update, $component] = $service->getEntity(DashboardComponent::class, $jsonComponent);
                    if($update && $component) {
                        $type = $componentTypeRepository->find($jsonComponent["type"]);
                        if(!$type) {
                            return $this->json([
                                "success" => false,
                                "msg" => "Type de composant ${jsonComponent["type"]} inconnu"
                            ]);
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
        }
dump($pagesToDelete, $pageRowsToDelete, $componentsToDelete);
        Stream::from($pagesToDelete, $pageRowsToDelete, $componentsToDelete)
            ->each(function($entity) use ($manager) {
                $manager->remove($entity);
            });

        $manager->flush();

        return $this->json([
            "success" => true,
            "dashboards" => $service->serialize(),
        ]);
    }

}
