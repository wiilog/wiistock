<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\PurchaseRequestScheduleRule;
use App\Entity\ScheduleRule;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\FormException;
use App\Service\FormatService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('achat/planification')]
class PurchaseRequestScheduleRuleController extends AbstractController
{
    #[Route('/api', name: 'purchase_request_schedule_rule_api', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function purchaseRequestScheduleRuleapi(EntityManagerInterface $entityManager,
                                                   FormatService          $formatService): Response {
        $purchaseRequestScheduleRuleRepository = $entityManager->getRepository(PurchaseRequestScheduleRule::class);
        $data = Stream::from($purchaseRequestScheduleRuleRepository->findAll())
            ->map(fn(PurchaseRequestScheduleRule $purchaseRequestScheduleRule) => [
                "actions" => $this->renderView('settings/stock/demandes/purchase_request_schedule_rule_table_row.html.twig', ["purchaseRequestScheduleRule" => $purchaseRequestScheduleRule]),
                "zone" => $formatService->zones($purchaseRequestScheduleRule->getZones()->toArray()),
                "supplier" => $formatService->suppliers($purchaseRequestScheduleRule->getSuppliers()->toArray()),
                "requester" => $formatService->user($purchaseRequestScheduleRule->getRequester()),
                "emailSubject" => $purchaseRequestScheduleRule->getEmailSubject(),
                "createdAt" => $purchaseRequestScheduleRule->getCreatedAt()->format("d/m/Y"),
                "frequency" => match($purchaseRequestScheduleRule->getFrequency()) {
                    ScheduleRule::ONCE => "Une fois",
                    ScheduleRule::HOURLY => "Chaque heure",
                    ScheduleRule::DAILY => "Chaque jour",
                    ScheduleRule::WEEKLY => "Chaque semaine",
                    ScheduleRule::MONTHLY => "Chaque mois",
                    default => null,
                },
                "lastExecution" => $purchaseRequestScheduleRule->getlastRun()?->format("d/m/Y"),
            ])
            ->toArray();

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    #[Route('/formulaire', name: 'purchase_request_schedule_form', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function getForm(EntityManagerInterface $entityManager,
                            Request                $request,
                            FormatService          $formatService): Response {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $purchaseRequestScheduleRuleRepository = $entityManager->getRepository(PurchaseRequestScheduleRule::class);

        $ruleId = $request->query->get('id');
        $rule = ($ruleId ? $purchaseRequestScheduleRuleRepository->find($ruleId) : null) ?? new PurchaseRequestScheduleRule();

        $status = Stream::from($statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST))
            ->map(function (Statut $status) use ($rule) {
                return [
                    "value" => $status->getId(),
                    "label" => $status->getNom(),
                    "selected" => $status->getId() === $rule->getStatus()?->getId(),
                ];
            })
            ->toArray();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('settings/stock/demandes/purchase-request-planner/form.html.twig', [
                "purchaseRequestScheduleRule" => $rule,
                'status' => $status,
            ]),
        ]);
    }

    #[Route('/creer-modifier', name: 'purchase_request_schedule_form_submit', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function FormSubmit(EntityManagerInterface $entityManager,
                               Request                $request,
                               FormatService          $formatService): Response {
        $data = $request->request->all();

        $purchaseRequestScheduleRuleRepository = $entityManager->getRepository(PurchaseRequestScheduleRule::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $statusRepository = $entityManager->getRepository(Statut::class);


        if (isset($data['id'])) {
            $purchaseRequestScheduleRule = $purchaseRequestScheduleRuleRepository->find($data['id']);
            if (!$purchaseRequestScheduleRule) {
                throw new FormException("Une erreur est survenue lors du traitement de votre demande.");
            }
        } else {
            $purchaseRequestScheduleRule = new PurchaseRequestScheduleRule();
        }

        if (isset($data['zones'])) {
            $zones = Stream::from(explode(',', $data['zones']))
                ->map(fn($id) => $zoneRepository->find($id))
                ->toArray();
            // convert to collection
            $purchaseRequestScheduleRule->setZones(new ArrayCollection($zones));
        } else {
            throw new FormException("Veuillez sélectionner au moins une zone.");
        }

        if (isset($data['suppliers'])) {
            $suppliers = Stream::from(explode(',', $data['suppliers']))
                ->map(fn($id) => $supplierRepository->find($id))
                ->toArray();
            $purchaseRequestScheduleRule->setSuppliers(new ArrayCollection($suppliers));
        } else {
            throw new FormException("Veuillez sélectionner au moins un fournisseur.");
        }

        $purchaseRequestScheduleRule
            ->setRequester($userRepository->find($data['requester']))
            ->setStatus($statusRepository->find($data['status']))
            ->setEmailSubject($data['mailSubject'])
            ->setCreatedAt(new \DateTime('now'))
            ->setBegin($formatService->parseDatetime($data['startDate']))
            ->setFrequency($data["frequency"] ?? null)
            ->setPeriod($data["repeatPeriod"] ?? null)
            ->setIntervalTime($data["intervalTime"] ?? null)
            ->setIntervalPeriod($data["intervalPeriod"] ?? null)
            ->setMonths(isset($data["months"]) ? explode(",", $data["months"]) : null)
            ->setWeekDays(isset($data["weekDays"]) ? explode(",", $data["weekDays"]) : null)
            ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null);

        $entityManager->persist($purchaseRequestScheduleRule);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Planification créée avec succès'
        ]);
    }

    #[Route('/delete', name: 'purchase_request_schedule_rule_delete', options: ['expose' => true], methods: ['DELETE'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE], mode: HasPermission::IN_JSON)]
    public function delete(EntityManagerInterface $entityManager,
                               Request                $request,): Response {
        $purchaseRequestScheduleRuleRepository = $entityManager->getRepository(PurchaseRequestScheduleRule::class);
        $ruleId = $request->query->get('id') ?? null;
        $rule = $purchaseRequestScheduleRuleRepository?->find($ruleId);
        if (!$rule) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Une erreur est survenue lors du traitement de votre demande.'
            ]);
        }
        $entityManager->remove($rule);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'Planification créée avec succès'
        ]);
    }
}
