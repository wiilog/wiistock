<?php

namespace App\Controller;

use App\Entity\DeliveryRequest\Demande;
use App\Exceptions\FormException;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/visible-column", name: "visible_column_")]
class VisibleColumnController extends AbstractController {

    private const PAGES = [
        "reference",
        "arrival",
        "article",
        "deliveryRequest",
        "deliveryRequestShow",
        "dispatch",
        "dispute",
        "onGoing",
        "handling",
        "arrivalPack",
        "reception",
        "reference",
        "trackingMovement",
        "truckArrival",
        "productionRequest",
        "shippingRequest",
    ];

    #[Route("/{page}/save", name: "save", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    public function save(string                 $page,
                         Request                $request,
                         EntityManagerInterface $manager,
                         VisibleColumnService   $visibleColumnService): Response {
        if (!in_array($page, self::PAGES)) {
            throw new FormException("Unknown visible columns page.");
        }

        $displayedColumns = $request->request->keys();
        $visibleColumnService->setVisibleColumns($page, $displayedColumns, $this->getUser());

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Vos préférences de colonnes à afficher ont bien été sauvegardées.",
        ]);
    }
}
