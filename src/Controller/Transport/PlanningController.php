<?php

namespace App\Controller\Transport;

use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route("transport/planning")]
class PlanningController extends AbstractController {

    /**
     * Called in /index.html.twig
     */
    #[Route("/liste", name: "transport_planning_index", methods: "GET")]
    public function index(EntityManagerInterface $manager,): Response {
        $transportOrderRepository = $manager->getRepository(TransportOrder::class);

        $transportOrders = $transportOrderRepository->findOrdersForPlanning();
        return $this->render('transport/planning/index.html.twig', [
            'transportOrders' => $transportOrders
        ]);
    }
}
