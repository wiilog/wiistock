<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reserve;
use App\Entity\TruckArrival;
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/reserve', name: 'reserve_')]
class ReserveController extends AbstractController
{
    #[Route('/form', name: 'form_submit', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::EDIT_RESERVES])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reserveRepository = $entityManager->getRepository(Reserve::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $data = $request->request->all();

        $reserve = $data['reserveId'] ?? null ? $reserveRepository->find($data['reserveId']) : new Reserve();

        if (($data['hasGeneralReserve'] ?? false) || ($data['hasQuantityReserve'] ?? false )) {
            $type = $data['type'] ?? null;
            if (!in_array($type, Reserve::TYPES)) {
                throw new FormException('Une erreur est survenue lors de la validation du formulaire');
            }
            $truckArrival = $truckArrivalRepository->find($data['truckArrivalId']);
            if (!$truckArrival || ($reserve->getTruckArrival() && ($reserve->getTruckArrival()  !== $truckArrival))) {
                throw new FormException('Une erreur est survenue lors de la validation du formulaire');
            }
            $reserve
                ->setType($type)
                ->setComment($data['quantityReserveComment'] ?? $data['generalReserveComment'] ?? null )
                ->setQuantity($data['reserveQuantity'] ?? null)
                ->setQuantityType($data['reserveType'] ?? null)
                ->setTruckArrival($truckArrival);
            $entityManager->persist($reserve);
        } else {
            $entityManager->remove($reserve);
        }
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'La modification réserve a bien été enregistrée',
        ]);
    }
}
