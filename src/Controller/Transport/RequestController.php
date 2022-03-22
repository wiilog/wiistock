<?php

namespace App\Controller\Transport;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Statut;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


#[Route("transport/demande")]
class RequestController extends AbstractController {

    /**
     * Called in /index.html.twig
     */
    #[Route("/liste", name: "transport_request_index", methods: "GET")]
    public function index(EntityManagerInterface $manager): Response {
        $categoryTypeRepository = $manager->getRepository(CategoryType::class);
        $typesRepository = $manager->getRepository(Type::class);

        return $this->render('transport/request/index.html.twig', [
            'categories' => [
                [
                    "category" => CategoryType::DELIVERY_TRANSPORT_REQUEST,
                    "icon" => "cart-delivery",
                    "label" => "Livraison",
                ], [
                    "category" => CategoryType::COLLECT_TRANSPORT_REQUEST,
                    "icon" => "cart-collect",
                    "label" => "Collecte" ,
                ],
            ],
            'types' => $typesRepository->findByCategoryLabels([
                CategoryType::DELIVERY_TRANSPORT_REQUEST, CategoryType::COLLECT_TRANSPORT_REQUEST,
            ]),
            'statuts' => [
                TransportRequest::STATUS_AWAITING_VALIDATION,
                TransportRequest::STATUS_TO_PREPARE,
                TransportRequest::STATUS_TO_DELIVER,
                TransportRequest::STATUS_AWAITING_PLANNING,
                TransportRequest::STATUS_TO_COLLECT,
                TransportRequest::STATUS_ONGOING,
                TransportRequest::STATUS_FINISHED,
                TransportRequest::STATUS_DEPOSITED,
                TransportRequest::STATUS_CANCELLED,
                TransportRequest::STATUS_NOT_DELIVERED,
                TransportRequest::STATUS_NOT_COLLECTED,
            ],
        ]);
    }

}
