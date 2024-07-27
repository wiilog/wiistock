<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Exceptions\FormException;
use App\Service\ReceiptAssociationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Annotation as Wii;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile")]
class ReceiptAssociationController extends AbstractController {

    #[Route("/receipt-association", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postReceiptAssociation(Request                   $request,
                                           EntityManagerInterface    $entityManager,
                                           ReceiptAssociationService $receiptAssociationService): Response {
        $receptionNumber = $request->request->get('receptionNumber');
        $logisticUnitCodes = json_decode($request->request->get("logisticUnits", "[]"), true) ?: [];
        $user = $this->getUser();

        if (empty($receptionNumber) || empty($logisticUnitCodes)) {
            throw new FormException("La réception et les unités logistiques doivent être renseignées pour effectuer l'association BR.");
        }

        $receiptAssociationService->persistReceiptAssociation($entityManager, [$receptionNumber], $logisticUnitCodes, $user);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "L'association BR a bien été effectuée.",
        ]);
    }

}
