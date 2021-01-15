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
     */
    public function settings(DashboardSettingsService $dashboardSettingsService,
                             EntityManagerInterface $entityManager): Response {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $componentTypes = $componentTypeRepository->findAll();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        return $this->render("dashboard/settings.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($entityManager, $loggedUser, DashboardSettingsService::MODE_EDIT),
            "token" => $_SERVER["APP_DASHBOARD_TOKEN"],
            "componentTypeConfig" => [
                // component types group by category
                "componentTypes" => Stream::from($componentTypes)
                    ->reduce(function(array $carry, Dashboard\ComponentType $componentType) {
                        $category = $componentType->getCategory();
                        if(!isset($carry[$category])) {
                            $carry[$category] = [];
                        }

                        $carry[$category][] = $componentType;

                        return $carry;
                    }, [])
            ]
        ]);
    }

    /**
     * @Route("/save", name="save_dashboard_settings", options={"expose"=true}, methods={"POST"})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_DASHBOARDS}, mode=HasPermission::IN_JSON)
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
            "dispatchTypes" => [],
            "arrivalStatuses" => [],
            "dispatchStatuses" => [],
            "natures" => [],
            'tooltip' => $componentType->getHint()
        ];

        foreach(["locations", "firstOriginLocation", "secondOriginLocation", "firstDestinationLocation", "secondDestinationLocation"] as $field) {
            if(!empty($values[$field])) {
                $locationRepository = $entityManager->getRepository(Emplacement::class);
                $values[$field] = $locationRepository->findBy(['id' => $values[$field]]);
            }
        }

        if(!empty($values['carriers'])) {
            $carrierRepository = $entityManager->getRepository(Transporteur::class);
            $values['carriers'] = $carrierRepository->findBy(['id' => $values['carriers']]);
        }

        if(!empty($values['arrivalTypes'])) {
            $values['arrivalTypes'] = $typeRepository->findBy(['id' => $values['arrivalTypes']]);
        }

        if(!empty($values['dispatchTypes'])) {
            $values['dispatchTypes'] = $typeRepository->findBy(['id' => $values['dispatchTypes']]);
        }

        if(!empty($values['arrivalStatuses'])) {
            $values['arrivalStatuses'] = $statusRepository->findBy(['id' => $values['arrivalStatuses']]);
        }

        if(!empty($values['dispatchStatuses'])) {
            $values['dispatchStatuses'] = $statusRepository->findBy(['id' => $values['dispatchStatuses']]);
        }

        if(!empty($values['natures'])) {
            $values['natures'] = $natureRepository->findBy(['id' => $values['natures']]);
        }

        $arrivalTypes = $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]);
        $dispatchTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
        $arrivalStatuses = $statusRepository->findByCategorieName(CategorieStatut::ARRIVAGE);
        $dispatchStatuses = $statusRepository->findByCategorieName(CategorieStatut::DISPATCH);
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
                    'dispatchTypes' => $dispatchTypes,
                    'arrivalStatuses' => $arrivalStatuses,
                    'dispatchStatuses' => $dispatchStatuses,
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
