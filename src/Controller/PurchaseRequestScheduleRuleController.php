<?php

namespace App\Controller;

use App\Entity\PurchaseRequestScheduleRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('achat/planification')]
class PurchaseRequestScheduleRuleController extends AbstractController
{
    #[Route('/api', name: 'purchase_request_schedule_rule_api', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function purchaseRequestScheduleRuleapi(EntityManagerInterface $entityManager): Response {
        $purchaseRequestScheduleRuleRepository = $entityManager->getRepository(PurchaseRequestScheduleRule::class);
        $data = Stream::from($purchaseRequestScheduleRuleRepository->findAll())
            ->map(fn(PurchaseRequestScheduleRule $purchaseRequestScheduleRule) => [
                "action" => $this->render('settings/stock/demandes/planification_achats.html.twig', [ "purchaseRequestScheduleRule" => $purchaseRequestScheduleRule ]),
                "zone" => $purchaseRequestScheduleRule->getZone(),
                "supplier" => $purchaseRequestScheduleRule->getSupplier(),
                "requester" => $purchaseRequestScheduleRule->getRequester(),
                "emailSubject" => $purchaseRequestScheduleRule->getEmailSubject(),
                "createdAt" => $purchaseRequestScheduleRule->getCreatedAt(),
                "frequency" => $purchaseRequestScheduleRule->getFrequency(),
            ])
            ->toArray();

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }
}
