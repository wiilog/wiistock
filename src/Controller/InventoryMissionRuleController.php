<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\Menu;
use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\MailerService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
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

    #[Route('/save', name: 'mission_rules_form', options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function save(EntityManagerInterface $entityManager,
                         Request                $request,
                         TranslationService     $translationService,
                         MailerService          $mailerService): JsonResponse
    {

        $data = $request->request->all();

        $missionRuleRepository = $entityManager->getRepository(InventoryMissionRule::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        if (isset($data['ruleId'])) {
            $missionRule = $missionRuleRepository->find($data['ruleId']);
            $edit = true;
            if (!isset($missionRule)) {
                throw new FormException("La planification d'inventaire n'existe plus, veuillez actualiser la page.");
            }
        }
        else {
            $missionRule = new InventoryMissionRule();
            $edit = false;
            if (isset($data['missionType']) && in_array($data['missionType'], [InventoryMission::ARTICLE_TYPE, InventoryMission::LOCATION_TYPE])) {
                $missionRule->setMissionType($data['missionType']);
            } else {
                throw new FormException('Une erreur est survenu, le champ type de mission est invalide');
            }

            $missionRule->setCreator($this->getUser());
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
            ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null);

        if (isset($data['requester'])) {
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $requester = $userRepository->find($data['requester']);
            $missionRule->setRequester($requester);
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => "Veuillez sélectionner un demandeur."
            ]);
        }


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
                        ->filterMap(fn(string $id) => trim($id) ?: null)
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

        $subject = $translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR . (
            $edit
                ? 'Modification planification mission d’inventaire'
                : 'Création planification mission d’inventaire'
            );

        $mailerService->sendMail(
            $subject,
            $this->renderView('mails/contents/mailScheduledInventory.html.twig', [
                'edit' => $edit,
                'requester' => $missionRule->getRequester(),
                'creator' => $missionRule->getCreator(),
                'missionType' => match ($missionRule->getMissionType()) {
                    InventoryMission::ARTICLE_TYPE => "Quantité article",
                    InventoryMission::LOCATION_TYPE => "Article sur emplacement",
                },
                'missionLabel' => $missionRule->getLabel(),
                'describe' => $missionRule->getDescription(),
                "frequency" => match ($missionRule->getFrequency()) {
                    ScheduleRule::ONCE => "une fois",
                    ScheduleRule::HOURLY => "chaque heure",
                    ScheduleRule::DAILY => "chaque jour",
                    ScheduleRule::WEEKLY => "chaque semaine",
                    ScheduleRule::MONTHLY => "chaque mois",
                    default => null,
                },
                'locations' => $missionRule->getLocations(),
            ]),
            [$missionRule->getCreator(), $missionRule->getRequester()],
        );

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route('/{mission}/delete', name: 'mission_rules_delete', options: ["expose" => true], methods: "DELETE", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function delete(EntityManagerInterface $entityManager,
                           InventoryMissionRule   $mission): JsonResponse {
        if(!$mission->getCreatedMissions()->isEmpty()) {
            throw new FormException("Vous ne pouvez pas supprimer cette planification d'inventaire car des missions d'inventaires ont déjà été créées à partir de celle-ci");
        } else {
            $entityManager->remove($mission);
            $entityManager->flush();
            return $this->json([
                'success' => true,
                'msg' => "La planification de mission d'inventaire a été supprimée avec succès"
            ]);
        }
    }

    #[Route('/{mission}/cancel', name: 'mission_rules_cancel', options: ["expose" => true], methods: "DELETE", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES], mode: HasPermission::IN_JSON)]
    public function cancel(EntityManagerInterface $entityManager,
                           InventoryMissionRule   $mission): JsonResponse {
        $mission->setActive(false);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "La planification de mission d'inventaire a été annulée avec succès"
        ]);

    }
}
