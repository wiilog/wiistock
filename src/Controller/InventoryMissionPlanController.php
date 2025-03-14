<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Menu;
use App\Entity\ScheduledTask\InventoryMissionPlan;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\MailerService;
use App\Service\ScheduledTaskService;
use App\Service\ScheduleRuleService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route('/inventaires/missions/planifier')]
class InventoryMissionPlanController extends AbstractController
{

    #[Route('/form-template', name: 'mission_plans_form_template', options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function getEditFormTemplate(EntityManagerInterface $entityManager,
                                        Request                $request): JsonResponse {
        $inventoryMissionPlanRepository = $entityManager->getRepository(InventoryMissionPlan::class);

        $missionPlanId = $request->query->getInt('missionPlan');
        $missionPlan = $missionPlanId ? $inventoryMissionPlanRepository->find($missionPlanId) : new InventoryMissionPlan();

        $initialLocations = Stream::from($missionPlan?->getLocations() ?? [])
            ->map(fn($location) => [
                'id' => $location->getId(),
                'zone' => $this->formatService->zone($location->getZone()),
                'location' => $this->formatService->location($location),
            ])
            ->toArray();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('settings/stock/inventaires/form/form.html.twig', [
                'missionPlan' => $missionPlan,
                'initialLocations' => $initialLocations,
            ])
        ]);
    }

    #[Route('/save', name: 'mission_plans_form', options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function save(EntityManagerInterface $entityManager,
                         Request                $request,
                         TranslationService     $translationService,
                         ScheduleRuleService    $scheduleRuleService,
                         ScheduledTaskService   $scheduledTaskService,
                         MailerService          $mailerService): JsonResponse {
        $data = $request->request->all();

        $inventoryMissionPlanRepository = $entityManager->getRepository(InventoryMissionPlan::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        if (isset($data['ruleId'])) {
            /** @var InventoryMissionPlan $missionPlan */
            $missionPlan = $inventoryMissionPlanRepository->find($data['ruleId']);
            $edit = true;
            if (!isset($missionPlan)) {
                throw new FormException("La planification d'inventaire n'existe plus, veuillez actualiser la page.");
            }
        }
        else {
            if(!$scheduledTaskService->canSchedule($entityManager, InventoryMissionPlan::class)){
                throw new FormException("Vous avez déjà planifié " . ScheduledTaskService::MAX_ONGOING_SCHEDULED_TASKS . " planifications. Pensez à supprimer celles qui sont terminées en fréquence \"une fois\".");            }

            $missionPlan = new InventoryMissionPlan();
            $edit = false;
            if (isset($data['missionType']) && in_array($data['missionType'], [InventoryMission::ARTICLE_TYPE, InventoryMission::LOCATION_TYPE])) {
                $missionPlan->setMissionType($data['missionType']);
            } else {
                throw new FormException('Une erreur est survenu, le champ type de mission est invalide');
            }

            $missionPlan->setCreator($this->getUser());
            $entityManager->persist($missionPlan);
        }

        $scheduleRule = $scheduleRuleService->updateRule($missionPlan->getScheduleRule(), new ParameterBag([
            "startDate" => $data["startDate"] ?? null,
            "frequency" => $data["frequency"] ?? null,
            "repeatPeriod" => $data["repeatPeriod"] ?? null,
            "intervalTime" => $data["intervalTime"] ?? null,
            "intervalPeriod" => $data["intervalPeriod"] ?? null,
            "months" => $data["months"] ?? null,
            "weekDays" => $data["weekDays"] ?? null,
            "monthDays" => $data["monthDays"] ?? null,
        ]));


        $missionPlan
            ->setLabel($data['label'])
            ->setDescription($data['description'])
            ->setDuration($data['duration'])
            ->setScheduleRule($scheduleRule);

        if (isset($data['requester'])) {
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $requester = $userRepository->find($data['requester']);
            $missionPlan->setRequester($requester);
        } else {
            throw new FormException("Veuillez sélectionner un demandeur.");
        }


        if (isset($data['durationUnit']) && in_array($data['durationUnit'], InventoryMissionPlan::DURATION_UNITS)) {
            $missionPlan->setDurationUnit($data['durationUnit']);
        } else {
            throw new FormException('Une erreur est survenu, le champ durée est invalide');
        }

        if ($missionPlan->getMissionType() === InventoryMission::ARTICLE_TYPE) {
            $missionPlan
                ->setCategories([])
                ->setLocations([]);
            if (isset($data['categories'])) {
                $categoriesId = explode(",", $data['categories']);
                foreach ($categoriesId as $categoryId) {
                    $category = $inventoryCategoryRepository->find($categoryId);
                    if ($category) {
                        $missionPlan->addCategory($category);
                    }
                }
            }
            if ($missionPlan->getCategories()->isEmpty()) {
                throw new FormException("Vous devez sélectionner au moins une catégorie d'inventaire");
            }
        } else if ($missionPlan->getMissionType() === InventoryMission::LOCATION_TYPE) {
            $missionPlan
                ->setCategories([])
                ->setLocations([]);
            if (isset($data['locations'])) {
                $locationIds = !empty($data['locations'])
                    ? Stream::explode(',', $data['locations'])
                        ->filterMap(fn(string $id) => trim($id) ?: null)
                        ->toArray()
                    : [];
                $locations = !empty($locationIds)
                    ? $locationRepository->findBy(['id' => $locationIds])
                    : [];
                $missionPlan->setLocations($locations);
            }
            if ($missionPlan->getLocations()->isEmpty()) {
                throw new FormException("Veuillez renseigner des emplacements à ajouter.");
            }
        }

        $entityManager->flush();

        $scheduledTaskService->deleteCache(InventoryMissionPlan::class);

        $subject = $translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . (
            $edit
                ? 'Modification planification mission d’inventaire'
                : 'Création planification mission d’inventaire'
            );

        $mailerService->sendMail(
            $entityManager,
            $subject,
            $this->renderView('mails/contents/mailScheduledInventory.html.twig', [
                'edit' => $edit,
                'requester' => $missionPlan->getRequester(),
                'creator' => $missionPlan->getCreator(),
                'missionType' => match ($missionPlan->getMissionType()) {
                    InventoryMission::ARTICLE_TYPE => "Quantité article",
                    InventoryMission::LOCATION_TYPE => "Article sur emplacement",
                },
                'missionLabel' => $missionPlan->getLabel(),
                'describe' => $missionPlan->getDescription(),
                "frequency" => match ($scheduleRule->getFrequency()) {
                    ScheduleRule::ONCE => "une fois",
                    ScheduleRule::HOURLY => "chaque heure",
                    ScheduleRule::DAILY => "chaque jour",
                    ScheduleRule::WEEKLY => "chaque semaine",
                    ScheduleRule::MONTHLY => "chaque mois",
                    default => null,
                },
                'locations' => $missionPlan->getLocations(),
            ]),
            [$missionPlan->getCreator(), $missionPlan->getRequester()],
        );

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route('/{mission}/delete', name: 'mission_plan_delete', options: ["expose" => true], methods: "DELETE", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function delete(EntityManagerInterface $entityManager,
                           ScheduledTaskService   $scheduledTaskService,
                           InventoryMissionPlan   $mission): JsonResponse {
        if(!$mission->getCreatedMissions()->isEmpty()) {
            throw new FormException("Vous ne pouvez pas supprimer cette planification d'inventaire car des missions d'inventaires ont déjà été créées à partir de celle-ci");
        } else {
            $entityManager->remove($mission);
            $entityManager->flush();

            $scheduledTaskService->deleteCache(InventoryMissionPlan::class);

            return $this->json([
                'success' => true,
                'msg' => "La planification de mission d'inventaire a été supprimée avec succès"
            ]);
        }
    }

    #[Route('/{mission}/cancel', name: 'mission_plan_cancel', options: ["expose" => true], methods: [self::PATCH], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function cancel(EntityManagerInterface $entityManager,
                           ScheduledTaskService   $scheduledTaskService,
                           InventoryMissionPlan   $mission): JsonResponse {
        $mission->setActive(false);
        $entityManager->flush();

        $scheduledTaskService->deleteCache(InventoryMissionPlan::class);

        return $this->json([
            'success' => true,
            'msg' => "La planification de mission d'inventaire a été annulée avec succès"
        ]);

    }
}
