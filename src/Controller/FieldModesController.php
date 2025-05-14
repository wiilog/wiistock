<?php

namespace App\Controller;

use App\Entity\DeliveryRequest\Demande;
use App\Exceptions\FormException;
use App\Service\FieldModesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/field-modes", name: "field_modes_")]
class FieldModesController extends AbstractController {

    public const DELIVERY_REQUEST_SHOW_VISIBLE_COLUMNS = "deliveryRequestShow";
    public const PAGE_PRODUCTION_REQUEST_LIST = "productionRequest";
    public const PAGE_PRODUCTION_REQUEST_PLANNING = "productionRequestPlanning";
    public const PAGE_PACK_LIST = "packList";
    public const PAGE_EMPLACEMENT = "emplacement";
    public const PAGE_EMERGENCY_LIST = "emergency";

    private const PAGES = [
        "reference",
        "arrival",
        "article",
        "deliveryRequest",
        "stockMovement",
        self::DELIVERY_REQUEST_SHOW_VISIBLE_COLUMNS,
        "dispatch",
        "dispute",
        "onGoing",
        "handling",
        "arrivalPack",
        "reception",
        "reference",
        "trackingMovement",
        "truckArrival",
        self::PAGE_PRODUCTION_REQUEST_LIST,
        self::PAGE_PRODUCTION_REQUEST_PLANNING,
        "shippingRequest",
        self::PAGE_PACK_LIST,
        self::PAGE_EMERGENCY_LIST,
        self::PAGE_EMPLACEMENT,
    ];

    #[Route("/{page}/save", name: "save", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    public function save(string                 $page,
                         Request                $request,
                         EntityManagerInterface $manager,
                         FieldModesService      $fieldModesService): Response {
        if (!in_array($page, self::PAGES)) {
            throw new FormException("Unknown visible columns page.");
        }

        $columnsModes = Stream::from($request->request->all())
            ->filterMap(static fn(?string $value) => $value ? explode(",", $value): null)
            ->toArray();

        $id = $request->request->getInt("id");
        if ($page === self::DELIVERY_REQUEST_SHOW_VISIBLE_COLUMNS && $id) {
            $deliveryRequest = $manager->find(Demande::class, $id);
            $deliveryRequest->setVisibleColumns($columnsModes);
        }

        $fieldModesService->setFieldModesByPage($page, $columnsModes, $this->getUser());

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Vos préférences de colonnes à afficher ont bien été sauvegardées.",
        ]);
    }
}
