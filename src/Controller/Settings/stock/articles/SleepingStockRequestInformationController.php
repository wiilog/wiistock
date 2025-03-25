<?php

namespace App\Controller\Settings\stock\articles;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Entity\SleepingStockRequestInformation;
use App\Service\FormService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route('/parametrage/demande-stocks-dormants', name: "settings_sleeping_stock_request_information" )]
class SleepingStockRequestInformationController extends AbstractController {

    #[Route('/api', name: '_api', options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::DISPLAY_ARTI], mode: HasPermission::IN_JSON)]
    public function sleepingStockRequestInformationApi(EntityManagerInterface $manager,
                                                       FormService            $formService): JsonResponse {
        $sleepingStockRequestInformationRepository = $manager->getRepository(SleepingStockRequestInformation::class);
        $deliveryRequestTemplateSleepingStockRepository = $manager->getRepository(DeliveryRequestTemplateSleepingStock::class);

        $deliveryRequestTemplate = Stream::from($deliveryRequestTemplateSleepingStockRepository->getForSelect())
            ->keymap(fn(array $deleveryRequestTemplat) => [
                $deleveryRequestTemplat["id"],
                [
                    "value" => $deleveryRequestTemplat["id"],
                    "label" => $deleveryRequestTemplat["text"],
                ]
            ])
            ->toArray();

        $data = Stream::from($sleepingStockRequestInformationRepository->findAll())
            ->map(function(SleepingStockRequestInformation $sleepingStockRequestInformation) use ($deliveryRequestTemplate, $formService) {
                $items = $deliveryRequestTemplate;
                $deliveryRequestTemplate = $sleepingStockRequestInformation->getDeliveryRequestTemplate();
                if ($deliveryRequestTemplate) {
                    $items[$deliveryRequestTemplate->getId()]["selected"] = true;
                }
                return [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$sleepingStockRequestInformation->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>".
                        $formService->macro("hidden", "id", $sleepingStockRequestInformation->getId()),
                    "deliveryRequestTemplate" =>$formService->macro("select", "deliveryRequestTemplate", null, false, [
                        "items" => $items,
                        "emptyOption" => [
                            "label" => "Aucune demande de livraison",
                        ],
                        "attributes" => [
                            "data-parent" => "body"
                        ],
                    ]),
                    "buttonLabel" => $formService->macro("input", "buttonLabel", null, true, $sleepingStockRequestInformation->getButtonActionLabel()),
                ];
            })
            ->toArray();

        return $this->json([
            "data" => [
                ...$data,
                [
                    "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
                    "deliveryRequestTemplate" => "",
                    "buttonLabel" => "",
                ]
            ]
        ]);
    }

    #[Route('/supprimer/{entity}', name: '_delete', options: ['expose' => true], methods: [self::POST])]
    #[HasPermission([Menu::PARAM, Action::DISPLAY_ARTI], mode: HasPermission::IN_JSON)]
    public function deleteSleepingStockRequestInformation(EntityManagerInterface          $entityManager,
                                                          SleepingStockRequestInformation $entity): Response {
        $entityManager->remove($entity);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La ligne a bien été supprimée",
        ]);
    }
}
