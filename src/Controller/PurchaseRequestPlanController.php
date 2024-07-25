<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\FormException;
use App\Service\ScheduledTaskService;
use App\Service\ScheduleRuleService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route('achat/planification')]
class PurchaseRequestPlanController extends AbstractController
{
    #[Route('/api', name: 'purchase_request_plan_api', options: ['expose' => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function purchaseRequestPlanApi(EntityManagerInterface $entityManager): JsonResponse {
        $purchaseRequestPlanRepository = $entityManager->getRepository(PurchaseRequestPlan::class);
        $data = Stream::from($purchaseRequestPlanRepository->findAll())
            ->map(function (PurchaseRequestPlan $purchaseRequestPlan) {
                $scheduleRule = $purchaseRequestPlan->getScheduleRule();
                return [
                    "actions" => $this->renderView('settings/stock/demandes/purchase_request_plan_table_row.html.twig', [
                        "purchaseRequestPlan" => $purchaseRequestPlan
                    ]),
                    "zone" => $this->getFormatter()->zones($purchaseRequestPlan->getZones()->toArray()),
                    "supplier" => $this->getFormatter()->suppliers($purchaseRequestPlan->getSuppliers()->toArray()),
                    "requester" => $this->getFormatter()->user($purchaseRequestPlan->getRequester()),
                    "emailSubject" => $purchaseRequestPlan->getEmailSubject(),
                    "createdAt" => $this->getFormatter()->date($purchaseRequestPlan->getCreatedAt()),
                    "frequency" => match ($scheduleRule?->getFrequency()) {
                        ScheduleRule::ONCE => "Une fois",
                        ScheduleRule::HOURLY => "Chaque heure",
                        ScheduleRule::DAILY => "Chaque jour",
                        ScheduleRule::WEEKLY => "Chaque semaine",
                        ScheduleRule::MONTHLY => "Chaque mois",
                        default => null,
                    },
                    "lastExecution" => $this->getFormatter()->datetime($purchaseRequestPlan->getlastRun()),
                ];
            })
            ->toArray();

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    #[Route('/formulaire', name: 'purchase_request_schedule_form', options: ['expose' => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function getForm(EntityManagerInterface $entityManager,
                            Request                $request): JsonResponse {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestPlanRepository = $entityManager->getRepository(PurchaseRequestPlan::class);

        $ruleId = $request->query->getInt('id');
        $plan = ($ruleId ? $purchaseRequestPlanRepository->find($ruleId) : null) ?? new PurchaseRequestPlan();

        $statuses = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST))
            ->map(function (Statut $status) use ($plan) {
                return [
                    "value" => $status->getId(),
                    "label" => $status->getNom(),
                    "selected" => $status->getId() === $plan->getStatus()?->getId(),
                ];
            })
            ->toArray();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('settings/stock/demandes/purchase-request-planner/form.html.twig', [
                "purchaseRequestPlan" => $plan,
                'statuses' => $statuses,
            ]),
        ]);
    }

    #[Route('/creer-modifier', name: 'purchase_request_schedule_form_submit', options: ['expose' => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function formSubmit(EntityManagerInterface $entityManager,
                               Request                $request,
                               ScheduledTaskService   $scheduledTaskService,
                               ScheduleRuleService    $scheduleRuleService): JsonResponse {
        $data = $request->request->all();

        $purchaseRequestPlanRepository = $entityManager->getRepository(PurchaseRequestPlan::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        if (isset($data['id'])) {
            $purchaseRequestPlan = $purchaseRequestPlanRepository->find($data['id']);
            if (!$purchaseRequestPlan) {
                throw new FormException("Une erreur est survenue lors du traitement de votre demande.");
            }
        } else {
            if(!$scheduledTaskService->canSchedule($entityManager, PurchaseRequestPlan::class)){
                throw new FormException("Vous avez déjà planifié " . ScheduledTaskService::MAX_ONGOING_SCHEDULED_TASKS . " génération de demande d'achat");
            }

            $purchaseRequestPlan = new PurchaseRequestPlan();
        }

        if (isset($data['zones'])) {
            $zones = Stream::from(explode(',', $data['zones']))
                ->map(fn($id) => $zoneRepository->find($id))
                ->toArray();
            // convert to collection
            $purchaseRequestPlan->setZones(new ArrayCollection($zones));
        } else {
            throw new FormException("Veuillez sélectionner au moins une zone.");
        }

        if (isset($data['suppliers'])) {
            $suppliers = Stream::from(explode(',', $data['suppliers']))
                ->map(fn($id) => $supplierRepository->find($id))
                ->toArray();
            $purchaseRequestPlan->setSuppliers(new ArrayCollection($suppliers));
        } else {
            throw new FormException("Veuillez sélectionner au moins un fournisseur.");
        }

        $purchaseRequestPlan
            ->setRequester($userRepository->find($data['requester']))
            ->setStatus($statusRepository->find($data['status']))
            ->setEmailSubject($data['mailSubject'])
            ->setCreatedAt(new DateTime('now'));

        $scheduleRule = $scheduleRuleService->updateRule($purchaseRequestPlan->getScheduleRule(), new ParameterBag([
            "startDate" => $data["startDate"] ?? null,
            "frequency" => $data["frequency"] ?? null,
            "repeatPeriod" => $data["repeatPeriod"] ?? null,
            "intervalTime" => $data["intervalTime"] ?? null,
            "intervalPeriod" => $data["intervalPeriod"] ?? null,
            "months" => $data["months"] ?? null,
            "weekDays" => $data["weekDays"] ?? null,
            "monthDays" => $data["monthDays"] ?? null,
        ]));
        $purchaseRequestPlan->setScheduleRule($scheduleRule);

        $entityManager->persist($purchaseRequestPlan);
        $entityManager->flush();

        $scheduledTaskService->deleteCache(PurchaseRequestPlan::class);

        return $this->json([
            'success' => true,
            'msg' => 'Planification créée avec succès'
        ]);
    }

    #[Route('/{purchaseRequestPlan}/delete', name: 'purchase_request_plan_delete', options: ['expose' => true], methods: [self::DELETE], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function delete(EntityManagerInterface $entityManager,
                           ScheduledTaskService   $scheduledTaskService,
                           PurchaseRequestPlan    $purchaseRequestPlan): Response {
        $entityManager->remove($purchaseRequestPlan);
        $entityManager->flush();

        $scheduledTaskService->deleteCache(PurchaseRequestPlan::class);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Planification créée avec succès'
        ]);
    }
}
