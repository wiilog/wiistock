<?php

namespace App\Controller\Transport;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Type;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $transportOrderRepository = $manager->getRepository(TransportOrder::class);

        $dateForContainer = (new \DateTime());
        $date = (new \DateTime());
        $transportOrders = $transportOrderRepository->findOrdersForPlanning($date, $dateForContainer->modify('+1 day'));

       $orderedTransportOrders = [];
       $deliveryAndCollectCount = [];
       $countDeliveryAndCollect = 1;
       $typesCount = [];
       $countTypes = 1;

       /** @var TransportOrder $transportOrder */
        foreach ($transportOrders as $transportOrder){
            $requestExpectedAt = $transportOrder->getRequest()->getExpectedAt()->format('Y-m-d');
            if ($requestExpectedAt === $date->format('Y-m-d')) {
                $orderedTransportOrders[$date->format('Y-m-d')][] = $transportOrder;
                $deliveryAndCollectCount[$date->format('Y-m-d')][$transportOrder->getRequest() instanceof TransportDeliveryRequest ? 'delivery' : 'collect'] = $countDeliveryAndCollect;
                $typesCount[$date->format('Y-m-d')][$transportOrder->getRequest() instanceof TransportDeliveryRequest ? 'delivery' : 'collect'][] =
                    [
                        ($transportOrder->getRequest()->getType()->getLogo()) ? $transportOrder->getRequest()->getType()->getLogo()->getFullPath() : null, $countTypes];

            } else if ($requestExpectedAt === $dateForContainer->format('Y-m-d')) {
                $orderedTransportOrders[$dateForContainer->format('Y-m-d')][] = $transportOrder;
                $deliveryAndCollectCount[$dateForContainer->format('Y-m-d')][$transportOrder->getRequest() instanceof TransportDeliveryRequest ? 'delivery' : 'collect'] = $countDeliveryAndCollect;
                $typesCount[$dateForContainer->format('Y-m-d')][$transportOrder->getRequest() instanceof TransportDeliveryRequest ? 'delivery' : 'collect'][] =
                    [($transportOrder->getRequest()->getType()->getLogo()) ? $transportOrder->getRequest()->getType()->getLogo()->getFullPath() : null, $countTypes];
            }
            $countDeliveryAndCollect++;
            $countTypes++;
       }

        dump($deliveryAndCollectCount);
        dump($typesCount);

        return $this->json([
            'success' => true,
            'template' => $this->renderView("transport/planning/planning_by_date_container.html.twig", [
                'transportOrders' => $orderedTransportOrders,
                'dateForContainer' => $date,
                'deliveryAndCollectCount' => $deliveryAndCollectCount,
                'typesCount' => $typesCount,
            ]),
        ]);
    }
}
