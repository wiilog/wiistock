<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Entity\Emplacement;
use App\Entity\TransferOrder;
use App\Service\TransferOrderService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Annotation as Wii;
use Symfony\Component\HttpFoundation\Response;
use WiiCommon\Helper\Stream;

#[Rest\Route("/api/mobile")]
class TransferOrderController extends AbstractController {

    #[Rest\Post("/transfer/finish", condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishTransfers(Request                $request,
                                    TransferOrderService   $transferOrderService,
                                    EntityManagerInterface $entityManager): Response
    {
        $nomadUser = $this->getUser();

        $dataResponse = [];
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);

        $httpCode = Response::HTTP_OK;
        $transfersToTreat = json_decode($request->request->get('transfers'), true) ?: [];
        Stream::from($transfersToTreat)
            ->each(function ($transfer) use ($locationRepository, $transferOrderRepository, $transferOrderService, $nomadUser, $entityManager) {
                $destination = $locationRepository->findOneBy(['label' => $transfer['destination']]);
                $transfer = $transferOrderRepository->find($transfer['id']);
                $transferOrderService->finish($transfer, $nomadUser, $entityManager, $destination);
            });

        $entityManager->flush();
        $dataResponse['success'] = $transfersToTreat;

        return new JsonResponse($dataResponse, $httpCode);
    }

}
