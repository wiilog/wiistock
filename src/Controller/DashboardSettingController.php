<?php

namespace App\Controller;

use App\Helper\Stream;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Request;

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
     * @Route("/api-component-type/{componentType}", name="dashboard_component_type_form", methods={"POST"}, options={"expose"=true})
     * @param Request $request
     * @param Dashboard\ComponentType $componentType
     * @return JsonResponse
     */
    public function apiComponentTypeForm(Request $request,
                                         Dashboard\ComponentType $componentType): JsonResponse {
        $templateName = $componentType->getTemplate();

        $values = json_decode($request->request->get('values'), true); // TODO

        return $this->json([
            'html' => $this->renderView('dashboard/component_type/form.html.twig', [
                'componentTypeId' => $componentType->getId(),
                'templateName' => $templateName,
                'rowIndex' => $request->request->get('rowIndex'),
                'componentIndex' => $request->request->get('componentIndex'),
                'values' => [
                    // "fieldName" => value
                ]
            ])
        ]);
    }

}
