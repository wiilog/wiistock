<?php

namespace App\Controller\Settings;

use App\Controller\AbstractController;
use App\Entity\DeliveryStationLine;
use App\Entity\Emplacement;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Service\DeliveryStationLineService;
use App\Service\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/parametrage")
 */
class DeliveryStationLineController extends AbstractController
{
    #[Route("/delivery-station-line-new", name: "delivery_station_line_new", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    public function new(Request $request, EntityManagerInterface $manager, DeliveryStationLineService $deliveryStationLineService): Response {

        $deliveryStationLine = $deliveryStationLineService->createOrUpdateDeliveryStationLine($manager, $request->request, null);
        $manager->persist($deliveryStationLine);
        $manager->flush();

        return $this->json([
            'success' => true,
            "msg" => "Lien créé avec succès.",
        ]);
    }

    #[Route("/delivery-station-line-api", name: "delivery_station_line_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function deliveryStationLineApi(Request $request, EntityManagerInterface $manager, DeliveryStationLineService $deliveryStationLineService): Response {
        if ($request->isXmlHttpRequest()) {
            $data = $deliveryStationLineService->getDeliveryStationLineForDatatable($manager);
            return $this->json($data);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/delivery-station-line-edit", name: "delivery_station_line_edit", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    public function edit(Request $request, EntityManagerInterface $manager, DeliveryStationLineService $deliveryStationLineService): Response {
        $deliveryStationLineRepository = $manager->getRepository(DeliveryStationLine::class);
        $deliveryStationLine = $deliveryStationLineRepository->find($request->request->get('deliveryStationLineId'));

        $deliveryStationLine = $deliveryStationLineService->createOrUpdateDeliveryStationLine($manager, $request->request, $deliveryStationLine);
        $manager->persist($deliveryStationLine);
        $manager->flush();

        return $this->json([
            'success' => true,
            "msg" => "Lien modifié avec succès.",
        ]);
    }

    #[Route('/edit_delivery_station_line', name: 'edit_delivery_station_line', options: ['expose' => true])]
    public function getModalQualityReserveContent(Request $request,
                                                  EntityManagerInterface $entityManager): JsonResponse {
        $deliveryStationLineRepository = $entityManager->getRepository(DeliveryStationLine::class);

        $deliveryStationLine = $deliveryStationLineRepository->find($request->query->get('deliveryStationLineId'));

        return $this->json([
            'success' => true,
            'content' => $this->renderView('settings/stock/borne_tactile/form.html.twig', [
                "deliveryStationLineId" => $deliveryStationLine->getId(),
                'deliveryType' => [
                    "label" => $deliveryStationLine->getDeliveryType()->getLabel(),
                    "value" => $deliveryStationLine->getDeliveryType()->getId(),
                    "selected" => true,
                ],
                'visibilityGroup' => [
                    "label" => $deliveryStationLine->getVisibilityGroup()->getLabel(),
                    "value" => $deliveryStationLine->getVisibilityGroup()->getId(),
                    "selected" => true,
                ],
                'destinationLocation' => [
                    "label" => $deliveryStationLine->getDestinationLocation()->getLabel(),
                    "value" => $deliveryStationLine->getDestinationLocation()->getId(),
                    "selected" => true,
                ],
                'deliveryStationLineReceiver' => [
                    "label" => $deliveryStationLine->getReceiver()?->getUsername(),
                    "value" => $deliveryStationLine->getReceiver()?->getId(),
                    "selected" => !!$deliveryStationLine->getReceiver(),
                ],
                'welcomeMessage' => $deliveryStationLine->getWelcomeMessage()
            ])
        ]);
    }

    #[Route('/delivery_station_line_delete', name: 'delivery_station_line_delete', options: ['expose' => true])]
    public function delete(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $deliveryStationLineRepository = $entityManager->getRepository(DeliveryStationLine::class);
        $deliveryStationLine = $deliveryStationLineRepository->find($request->request->getInt('id'));
        $entityManager->remove($deliveryStationLine);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            "msg" => "Lien supprimé avec succès.",
        ]);
    }
}
