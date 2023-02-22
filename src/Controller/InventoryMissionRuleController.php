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
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/inventaires/missions/planifier')]
class InventoryMissionRuleController extends AbstractController
{

    #[Route('/form-template', name: 'mission_rules_form_template', options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function getEditFormTemplate(EntityManagerInterface $entityManager,
                                        Request                $request): JsonResponse {
        $missionRuleRepository = $entityManager->getRepository(InventoryMissionRule::class);

        $missionRuleId = $request->query->get('missionRule');
        $missionRule = $missionRuleId ? $missionRuleRepository->find($missionRuleId) : new InventoryMissionRule();

        $initialLocations = Stream::from($missionRule?->getLocations() ?? [])
            ->map(fn($location) => [
                'id' => $location->getId(),
                'zone' => $this->formatService->zone($location->getZone()),
                'location' => $this->formatService->location($location),
            ])
            ->toArray();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('settings/stock/inventaires/form/form.html.twig', [
                'missionRule' => $missionRule,
                'initialLocations' => $initialLocations,
            ])
        ]);
    }

    // TODO has rights
    #[Route('/save', name: 'mission_rules_form', options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function save(EntityManagerInterface $entityManager,
                         Request                $request): JsonResponse
    {
        $data = $request->request->all();

        $missionRuleRepository = $entityManager->getRepository(InventoryMissionRule::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        if (isset($data['ruleId'])) {
            $missionRule = $missionRuleRepository->find($data['ruleId']);
            if (!isset($missionRule)) {
                throw new FormException("La planification d'inventaire n'existe plus, veuillez actualiser la page.");
            }
        }
        else {
            $missionRule = new InventoryMissionRule();

            if (isset($data['missionType']) && in_array($data['missionType'], [InventoryMission::ARTICLE_TYPE, InventoryMission::LOCATION_TYPE])) {
                $missionRule->setMissionType($data['missionType']);
            } else {
                throw new FormException('Une erreur est survenu, le champ type de mission est invalide');
            }

            $entityManager->persist($missionRule);
        }

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
            ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null)
            ->setCreator($this->getUser());


        if (isset($data['durationUnit']) && in_array($data['durationUnit'], InventoryMissionRule::DURATION_UNITS)) {
            $missionRule->setDurationUnit($data['durationUnit']);
        } else {
            throw new FormException('Une erreur est survenu, le champ durée est invalide');
        }

        if (isset($data['frequency']) && in_array($data['frequency'], ScheduleRule::FREQUENCIES)) {
            $missionRule->setFrequency($data['frequency']);
        } else {
            throw new FormException('Une erreur est survenu, le champ fréquence est invalide');
        }

        if ($missionRule->getMissionType() === InventoryMission::ARTICLE_TYPE) {
            $missionRule
                ->setCategories([])
                ->setLocations([]);
            if (isset($data['categories'])) {
                $categoriesId = explode(",", $data['categories']);
                foreach ($categoriesId as $categoryId) {
                    $category = $inventoryCategoryRepository->find($categoryId);
                    if ($category) {
                        $missionRule->addCategory($category);
                    }
                }
            }
            if ($missionRule->getCategories()->isEmpty()) {
                throw new FormException("Vous devez sélectionner au moins une catégorie d'inventaire");
            }
        } else if ($missionRule->getMissionType() === InventoryMission::LOCATION_TYPE) {
            $missionRule
                ->setCategories([])
                ->setLocations([]);
            if (isset($data['locations'])) {
                $locationIds = !empty($data['locations'])
                    ? Stream::explode(',', $data['locations'])
                        ->map('trim')
                        ->filter()
                        ->toArray()
                    : [];
                $locations = !empty($locationIds)
                    ? $locationRepository->findBy(['id' => $locationIds])
                    : [];
                $missionRule->setLocations($locations);
            }
            if ($missionRule->getLocations()->isEmpty()) {
                throw new FormException("Veuillez renseigner des emplacements à ajouter.");
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }
}
