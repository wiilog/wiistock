<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\Stream;
use App\Service\DashboardSettingsService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/parametrage-global/dashboard")
 */
class DashboardSettingsController extends AbstractController {

    private $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="dashboard_settings", methods={"GET"})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_DASHBOARDS})
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function settings(DashboardSettingsService $dashboardSettingsService,
                             EntityManagerInterface $entityManager): Response {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $componentTypes = $componentTypeRepository->findAll();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $orderedComponentCategories = [
            Dashboard\ComponentType::CATEGORY_TRACKING,
            Dashboard\ComponentType::CATEGORY_ORDERS,
            Dashboard\ComponentType::CATEGORY_STOCK,
            Dashboard\ComponentType::CATEGORY_REQUESTS
        ];

        $componentTypes = Stream::from($componentTypes)
            ->reduce(function(array $carry, Dashboard\ComponentType $componentType) {
                $category = $componentType->getCategory();
                if(!isset($carry[$category])) {
                    $carry[$category] = [];
                }

                $carry[$category][] = $componentType;

                return $carry;
            }, []);

        return $this->render("dashboard/settings.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($entityManager, $loggedUser, DashboardSettingsService::MODE_EDIT),
            "token" => $_SERVER["APP_DASHBOARD_TOKEN"],
            "componentTypeConfig" => [
                // component types group by category
                "componentTypes" => array_merge(array_flip($orderedComponentCategories), $componentTypes)
            ]
        ]);
    }

    /**
     * @Route("/save", name="save_dashboard_settings", options={"expose"=true}, methods={"POST"})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_DASHBOARDS}, mode=HasPermission::IN_JSON)
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
     */
    public function save(Request $request,
                         EntityManagerInterface $entityManager,
                         DashboardSettingsService $dashboardSettingsService): Response {
        $dashboards = json_decode($request->request->get("dashboards"), true);
        try {
            $dashboardSettingsService->save($entityManager, $dashboards);
        } catch(InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $unknownComponentCode = DashboardSettingsService::UNKNOWN_COMPONENT;
            if(preg_match("/$unknownComponentCode-(.*)/", $message, $matches)) {
                $unknownComponentLabel = $matches[1] ?? '';
                return $this->json([
                    "success" => false,
                    "msg" => "Type de composant ${unknownComponentLabel} inconnu"
                ]);
            } else {
                $invalidSegmentsEntry = DashboardSettingsService::INVALID_SEGMENTS_ENTRY;
                if (preg_match("/$invalidSegmentsEntry-(.*)/", $message, $matches)) {
                    $title = $matches[1] ?? '';
                    return $this->json([
                        "success" => false,
                        "msg" => 'Les valeurs de segments renseignées pour le composant "' . $title . '" ne sont pas valides'
                    ]);
                } else {
                    throw $exception;
                }
            }
        }

        $entityManager->flush();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        return $this->json([
            "success" => true,
            "dashboards" => $dashboardSettingsService->serialize($entityManager, $loggedUser, DashboardSettingsService::MODE_EDIT),
        ]);
    }

    /**
     * @Route("/api-component-type/{componentType}", name="dashboard_component_type_form", methods={"POST"}, options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_DASHBOARDS}, mode=HasPermission::IN_JSON)
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\ComponentType $componentType
     * @return Response
     */
    public function apiComponentTypeForm(Request $request,
                                         EntityManagerInterface $entityManager,
                                         Dashboard\ComponentType $componentType): Response {
        $templateName = $componentType->getTemplate();

        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $values = json_decode($request->request->get('values'), true);
        $values += [ //default values should be initialized hered
            "locations" => [],
            "firstOriginLocation" => [],
            "secondOriginLocation" => [],
            "firstDestinationLocation" => [],
            "secondDestinationLocation" => [],
            "carriers" => [],
            "arrivalTypes" => [],
            "handlingTypes" => [],
            "arrivalStatuses" => [],
            "handlingStatuses" => [],
            "natures" => [],
            'tooltip' => $componentType->getHint()
        ];

        foreach(["locations", "firstOriginLocation", "secondOriginLocation", "firstDestinationLocation", "secondDestinationLocation"] as $field) {
            if(!empty($values[$field])) {
                $locationRepository = $entityManager->getRepository(Emplacement::class);
                $values[$field] = $locationRepository->findByIds($values[$field]);
            }
        }

        if(!empty($values['carriers'])) {
            $carrierRepository = $entityManager->getRepository(Transporteur::class);
            $values['carriers'] = $carrierRepository->findByIds($values['carriers']);
        }

        if(!empty($values['arrivalTypes'])) {
            $values['arrivalTypes'] = $typeRepository->findByIds($values['arrivalTypes']);
        }

        if(!empty($values['handlingTypes'])) {
            $values['handlingTypes'] = $typeRepository->findByIds($values['handlingTypes']);
        }

        if(!empty($values['arrivalStatuses'])) {
            $values['arrivalStatuses'] = $statusRepository->findByIds($values['arrivalStatuses']);
        }

        if(!empty($values['handlingStatuses'])) {
            $values['handlingStatuses'] = $statusRepository->findByIds($values['handlingStatuses']);
        }

        if(!empty($values['natures'])) {
            $values['natures'] = $natureRepository->findByIds($values['natures']);
        }

        $arrivalTypes = $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]);
        $arrivalStatuses = $statusRepository->findByCategorieName(CategorieStatut::ARRIVAGE);
        $handlingTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
        $handlingStatuses = $statusRepository->findByCategorieName(CategorieStatut::HANDLING);
        $natures = $natureRepository->findAll();

        if($templateName) {
            return $this->json([
                'success' => true,
                'html' => $this->renderView('dashboard/component_type/form.html.twig', [
                    'componentType' => $componentType,
                    'templateName' => $templateName,
                    'rowIndex' => $request->request->get('rowIndex'),
                    'componentIndex' => $request->request->get('componentIndex'),
                    'arrivalTypes' => $arrivalTypes,
                    'handlingTypes' => $handlingTypes,
                    'arrivalStatuses' => $arrivalStatuses,
                    'handlingStatuses' => $handlingStatuses,
                    'natures' => $natures,
                    'values' => $values
                ])
            ]);
        } else {
            return $this->json([
                'success' => true
            ]);
        }
    }

    /**
     * @Route("/api-component-type/{componentType}/example-values", name="dashboard_component_type_example_values", methods={"POST"}, options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_DASHBOARDS}, mode=HasPermission::IN_JSON)
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @param Dashboard\ComponentType $componentType
     * @return Response
     */
    public function apiComponentTypeExample(Request $request,
                                            EntityManagerInterface $entityManager,
                                            DashboardSettingsService $dashboardSettingsService,
                                            Dashboard\ComponentType $componentType): Response {
        if($request->request->has("values")) {
            $values = json_decode($request->request->get("values"), true);
        } else {
            $values = $componentType->getExampleValues();
        }

        return $this->json([
            'success' => true,
            'exampleValues' => $dashboardSettingsService->serializeValues($entityManager, $componentType, $values, true),
        ]);
    }

}
