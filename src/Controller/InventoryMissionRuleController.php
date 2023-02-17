<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\Menu;
use App\Entity\ScheduleRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/inventaires/missions/planifier')]
class InventoryMissionRuleController extends AbstractController
{

    #[Route('/api/get-form', name: 'get_mission_rules_form', options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function getForm(EntityManagerInterface $entityManager,
                            Request                $request): JsonResponse
    {
        $missionRuleRepository = $entityManager->getRepository(InventoryMissionRule::class);

        $missionRuleId = $request->query->get('missionRuleId');
        $missionRule = $missionRuleRepository->find($missionRuleId);

        $initialLocations = Stream::from($missionRule?->getLocations() ?? [])
            ->map(fn($location) => [
                'id' => $location->getId(),
                'zone' => $this->formatService->zone($location->getZone()),
                'location' => $this->formatService->location($location),
            ])
            ->toArray();

        return new JsonResponse([
            'success' => true,
            'html' => $this->renderView('settings/stock/inventaires/form/form.html.twig', [
                'missionRule' => $missionRule,
                'initialLocations' => ['data' => $initialLocations],
            ])
        ]);
    }

    // TODO has rights
    #[Route('/api/post-form', name: 'post_mission_rules_form', options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function postForm(EntityManagerInterface $entityManager,
                             Request                $request): JsonResponse
    {
        $data = $request->request->all();
        dump($data);

        $missionRuleRepository = $entityManager->getRepository(InventoryMissionRule::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);


        $missionRule = isset($data['ruleId']) ? $missionRuleRepository->find($data['ruleId']) : new InventoryMissionRule();
        $missionRule
            ->setLabel($data['label'])
            ->setDescription($data['description'])
            ->setDuration($data['duration'])
            ->setBegin($this->formatService->parseDatetime($data['startDate']))
            ->setIntervalPeriod($data['intervalPeriod'] ?? null)
            ->setIntervalTime($data['intervalTime'] ?? null)
            ->setPeriod($data['repeatPeriod'] ?? null)
            ->setMonths(isset($data["months"]) ? explode(",", $data["months"]) : null)
            ->setWeekDays(isset($data["weekDays"]) ? explode(",", $data["weekDays"]) : null)
            ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null);


        if (isset($data['durationUnit']) && in_array($data['durationUnit'], InventoryMissionRule::DURATION_UNITS)) {
            $missionRule->setDurationUnit($data['durationUnit']);
        } else {
            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur est survenu, le champ durée est invalide',
            ]);
        }

        if (isset($data['frequency']) && in_array($data['frequency'], ScheduleRule::FREQUENCIES)) {
            $missionRule->setFrequency($data['frequency']);
        } else {
            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur est survenu, le champ fréquence est invalide',
            ]);
        }

        if (isset($data['missionType']) && in_array($data['missionType'], [InventoryMission::ARTICLE_TYPE, InventoryMission::LOCATION_TYPE])) {
            $missionRule->setMissionType($data['missionType']);
        } else {
            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur est survenu, le champ type de mission est invalide',
            ]);
        }

        $missionRule->setCreator($this->getUser());

        if ($missionRule->getMissionType() === InventoryMission::ARTICLE_TYPE) {
            $missionRule->setLocations([]);
            if (isset($data['categories'])) {
                $missionRule->setCategories([]);
                $categoriesId = explode(",", $data['categories']);
                foreach ($categoriesId as $categoryId) {
                    $category = $inventoryCategoryRepository->find($categoryId);
                    if ($category) {
                        $missionRule->addCategory($category);
                    }
                }
            }
        } elseif ($missionRule->getMissionType() === InventoryMission::LOCATION_TYPE) {
            $missionRule->setCategories([]);
            if (isset($data['locations'])) {
                $locationsId = json_decode($data['locations']);
                foreach ($locationsId as $locationId) {
                    $location = $locationRepository->find($locationId);
                    if ($location) {
                        $missionRule->addLocation($location);
                    }
                }
            }
            if ($missionRule->getLocations()->isEmpty()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Vous devez sélectionner au moins un emplacement',
                ]);
            }
        }

        $entityManager->persist($missionRule);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
