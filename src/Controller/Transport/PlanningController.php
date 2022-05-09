<?php

namespace App\Controller\Transport;

use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


#[Route("transport/planning")]
class PlanningController extends AbstractController
{

    /**
     * Called in /index.html.twig
     */
    #[Route("/liste", name: "transport_planning_index", methods: "GET")]
    public function index(): Response
    {
        return $this->render('transport/planning/index.html.twig');
    }

    #[Route("/api", name: "transport_planning_api", options: ['expose' => true], methods: "GET")]
    public function api(Request $request, EntityManagerInterface $manager)
    {
        $data = $request->query;
        $transportOrderRepository = $manager->getRepository(TransportOrder::class);

        $currentDate = DateTime::createFromFormat('Y-m-d',$data?->get('currentDate'));

        if (!$currentDate) {
            throw new RuntimeException('Invalid date');
        }

        $dateForContainer = clone $currentDate;
        $transportOrders = $transportOrderRepository->findOrdersForPlanning($currentDate, $dateForContainer->modify("+1 day"), explode(" ", $data?->get('statusForFilter'))[0]);
        $orderedTransportOrders = [];
        $typesCount = [];

        $dateForContainer = $dateForContainer->format('Y-m-d');
        $currentDate = $currentDate->format('Y-m-d');

        $orderedTransportOrders[$currentDate] = [];
        $orderedTransportOrders[$dateForContainer] = [];
        $typeIdToFullPath = [];
       /** @var TransportOrder $transportOrder */
        foreach ($transportOrders as $transportOrder) {
            $requestExpectedAt = $transportOrder->getRequest()->getExpectedAt()->format('Y-m-d');
            $requestType = $transportOrder->getRequest() instanceof TransportDeliveryRequest ? 'delivery' : 'collect';
            $typeId = $transportOrder->getRequest()->getType()->getId();
            $typeIdToFullPath[$typeId] = $transportOrder->getRequest()->getType()->getLogo()?->getFullPath();
            if(!isset($typesCount[$requestExpectedAt][$requestType][$typeId])){
                $typesCount[$requestExpectedAt][$requestType][$typeId] = 0;
            }
            $typesCount[$requestExpectedAt][$requestType][$typeId] ++;

            $orderedTransportOrders[$requestExpectedAt][] = $transportOrder;
       }

        $deliveryAndCollectCount = [
            $currentDate => [
                'delivery' => Stream::from($transportOrders)
                    ->filter(fn(TransportOrder $order) => $order->getRequest() instanceof TransportDeliveryRequest
                        && $order->getRequest()->getExpectedAt()->format('Y-m-d') === $currentDate)
                    ->count(),
                'collect' => Stream::from($transportOrders)
                    ->filter(fn(TransportOrder $order) => $order->getRequest() instanceof TransportCollectRequest
                        && $order->getRequest()->getExpectedAt()->format('Y-m-d') === $currentDate)
                    ->count(),
            ],
            $dateForContainer => [
                'delivery' => Stream::from($transportOrders)
                    ->filter(fn(TransportOrder $order) => $order->getRequest() instanceof TransportDeliveryRequest
                        && $order->getRequest()->getExpectedAt()->format('Y-m-d') === $dateForContainer)
                    ->count(),
                'collect' => Stream::from($transportOrders)
                    ->filter(fn(TransportOrder $order) => $order->getRequest() instanceof TransportCollectRequest
                        && $order->getRequest()->getExpectedAt()->format('Y-m-d') === $dateForContainer)
                    ->count(),
            ]
        ];

        return $this->json([
            'success' => true,
            'template' => $this->renderView("transport/planning/planning_by_date_container.html.twig", [
                'transportOrders' => $orderedTransportOrders,
                'dateForContainer' => $currentDate,
                'deliveryAndCollectCount' => $deliveryAndCollectCount,
                'typesCount' => $typesCount,
                'typeIdToFullPath' => $typeIdToFullPath
            ]),
        ]);
    }
}
