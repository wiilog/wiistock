<?php

namespace App\Controller\Settings;

use App\Controller\AbstractController;
use App\Entity\CategorieCL;
use App\Entity\DeliveryStationLine;
use App\Entity\FreeField;
use App\Entity\Utilisateur;
use App\Service\DeliveryStationLineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route('/parametrage')]
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
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);

        $deliveryStationLine = $deliveryStationLineRepository->find($request->query->get('deliveryStationLineId'));
        $filterFields = Stream::from($freeFieldsRepository->findByCategory(CategorieCL::REFERENCE_ARTICLE))
            ->concat(DeliveryStationLine::REFERENCE_FIXED_FIELDS)
            ->map(static fn(FreeField|array $filterField) => [
                'label' => $filterField instanceof FreeField ? $filterField->getLabel() : $filterField['label'],
                'value' => $filterField instanceof FreeField ? $filterField->getId() : $filterField['value'],
                'selected' => Stream::from($deliveryStationLine->getFilters())
                    ->some(static fn(string $filter) => $filter == ($filterField instanceof FreeField ? $filterField->getId() : $filterField['value']))
            ])
            ->toArray();

        return $this->json([
            'success' => true,
            'content' => $this->renderView('settings/stock/borne_tactile/form.html.twig', [
                "deliveryStationLine" => $deliveryStationLine,
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
                'deliveryStationLineReceivers' => Stream::from($deliveryStationLine->getReceivers())
                    ->map(fn(Utilisateur $receiver) => [
                        "label" => $receiver->getUsername(),
                        "value" => $receiver->getId(),
                        "selected" => !!$receiver,
                    ])->toArray(),
                'welcomeMessage' => $deliveryStationLine->getWelcomeMessage(),
                'filterFields' => $filterFields,
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
