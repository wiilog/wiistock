<?php

namespace App\Controller;

use App\Helper\Stream;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Dashboard;

/**
 * @Route("/parametrage-global/dashboard")
 */
class DashboardSettingController extends AbstractController {

    /**
     * @Route("/", name="dashboard_settings", methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function settings(EntityManagerInterface $entityManager): Response {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $componentTypes = $componentTypeRepository->findAll();
        return $this->render("dashboard/settings.html.twig", [
            'componentTypeConfig' => [
                // component types group by category
                'componentTypes' => Stream::from($componentTypes)
                    ->reduce(function(array $carry, Dashboard\ComponentType $componentType) {
                        $category = $componentType->getCategory();
                        if (!isset($carry[$category])) {
                            $carry[$category] = [];
                        }

                        $carry[$category][] = $componentType;

                        return $carry;
                    }, [])
            ]
        ]);
    }

    /**
     * @Route("/api-component-type/{componentType}", name="dashboard_component_type_form", methods={"GET"}, options={"expose"=true})
     * @param Dashboard\ComponentType $componentType
     * @return JsonResponse
     */
    public function apiComponentTypeForm(Dashboard\ComponentType $componentType): JsonResponse {
        $templateName = $componentType->getTemplate();
        $templateDirectory = "dashboard/component_type/content/$templateName.html.twig";
        return $this->json([
            'html' => $this->renderView($templateDirectory, [
                'values' => [
                    // "fieldName" => value
                ]
            ])
        ]);
    }

}
