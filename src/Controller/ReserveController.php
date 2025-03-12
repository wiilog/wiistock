<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reserve;
use App\Entity\ReserveType;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\ReserveService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;

#[Route('/reserve', name: 'reserve_')]
class ReserveController extends AbstractController
{
    #[Route('/form', name: 'form_submit', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::EDIT_RESERVES])]
    public function index(Request                $request,
                          EntityManagerInterface $entityManager,
                          AttachmentService      $attachmentService,
                          ReserveService         $reserveService): Response
    {
        $reserveRepository = $entityManager->getRepository(Reserve::class);
        $reserveTypeRepository = $entityManager->getRepository(ReserveType::class);
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $data = $request->request->all();


        $reserve = ($data['reserveId'] ?? null) ? $reserveRepository->find($data['reserveId']) : new Reserve();
        if(isset($data['type']) && $data['type'] === Reserve::KIND_LINE){
            $truckArrivalLine = $truckArrivalLineRepository->find($data['truckArrivalLineNumber']);
            if (isset($data['reserveType'])) {
                $reserveTypeId = $data['reserveType'];
                $reserveType = $reserveTypeRepository->find($reserveTypeId);
                $reserve
                    ->setKind(Reserve::KIND_LINE)
                    ->setLine($truckArrivalLine)
                    ->setReserveType($reserveType)
                    ->setComment($data['comment'] ?? '');
            }
            else {
                throw new FormException('Le type de réserve est obligatoire');
            }

            $attachmentService->persistAttachments($entityManager, $request->files, ["attachmentContainer" => $reserve]);
        } else {
            if (!empty($data['hasGeneralReserve']) || !empty($data['hasQuantityReserve'])) {
                $type = $data['type'] ?? null;
                if (!in_array($type, Reserve::KINDS)) {
                    throw new FormException('Une erreur est survenue lors de la validation du formulaire');
                }
                $truckArrival = $truckArrivalRepository->find($data['truckArrivalId']);
                if (!$truckArrival || ($reserve->getTruckArrival() && ($reserve->getTruckArrival()  !== $truckArrival))) {
                    throw new FormException('Une erreur est survenue lors de la validation du formulaire');
                }
                $reserve
                    ->setKind($type)
                    ->setReserveType(null)
                    ->setComment($data['quantityReserveComment'] ?? $data['generalReserveComment'] ?? null )
                    ->setQuantity($data['reserveQuantity'] ?? null)
                    ->setQuantityType($data['reserveType'] ?? null)
                    ->setTruckArrival($truckArrival);
                $entityManager->persist($reserve);
            }
            else {
                $entityManager->remove($reserve);
            }
        }

        $entityManager->flush();

        if(isset($reserveType) && isset($truckArrivalLine)) {
            $truckArrival = $truckArrivalLine->getTruckArrival();
            $reserves = $reserveRepository->findReservesByLines($truckArrival->getTrackingLines());
            $attachments = Stream::from($reserves)
                ->flatMap(fn(Reserve $reserve) => $reserve->getAttachments())
                ->toArray();
            $reserveService->sendTruckArrivalMail($entityManager, $truckArrival, $reserveType, $reserves, $attachments);
        }

        return new JsonResponse([
            'success' => true,
            'msg' => 'La modification réserve a bien été enregistrée',
        ]);
    }

    #[Route('/modal-quality-content', name: 'modal_quality_content', options: ['expose' => true])]
    public function getModalQualityReserveContent(Request $request,
                                                  EntityManagerInterface $entityManager): JsonResponse {
        $reserveRepository = $entityManager->getRepository(Reserve::class);
        $reserveTypesRepository = $entityManager->getRepository(ReserveType::class);
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);

        $reserve = '';
        if($request->query->has('reserveId')){
            $reserve = $reserveRepository->find($request->query->get('reserveId'));
        }

        if($reserve instanceof Reserve){
            $availableTrackingNumber = [[
                "label" => $reserve->getLine()->getNumber(),
                "value" => $reserve->getLine()->getId(),
                "selected" => true
            ]];
        } else {
            $availableTrackingNumber = $truckArrivalLineRepository->getForReserve($request->query->getInt('truckArrival'));

            $availableTrackingNumber = Stream::from($availableTrackingNumber)
                ->map(function($line) use ($reserve) {
                    return [
                        "label" => $line['number'],
                        "value" => $line['id'],
                    ];
                })
                ->toArray();
        }
        $isNew = !($reserve instanceof Reserve);

        $reserveTypes = $reserveTypesRepository->getActiveReserveType();
        $reserveTypesLabels = Stream::from($reserveTypes)
            ->map(fn(array $reserveType) => [
                'label' => $reserveType['label'],
                'value' => $reserveType['id'],
                'selected' => ($isNew && $reserveType['defaultReserveType']) || (!$isNew && $reserve->getReserveType()->getId() === $reserveType['id']),
            ])
            ->toArray();

        $attachments = $reserve instanceof Reserve
            ? array_merge($reserve->getAttachments()->toArray(), $reserve->getLine()->getAttachments()->toArray())
            : [];

        $params = $reserve instanceof Reserve
            ? [
                'reserve' => $reserve,
                'attachments' => $attachments ?? [],
                'availableTrackingNumber' => $availableTrackingNumber,
                'reserveTypesLabels' => $reserveTypesLabels,
                'new' => $isNew,
            ]
            : [
                'availableTrackingNumber' => $availableTrackingNumber,
                'reserveTypesLabels' => $reserveTypesLabels,
                'new' => $isNew,
                ];

        return $this->json([
            'success' => true,
            'content' => $this->renderView('truck_arrival/reserve/qualityReserveForm.html.twig', $params)
        ]);
    }

}
