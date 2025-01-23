<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Attachment;
use App\Entity\Chauffeur;
use App\Entity\Emplacement;
use App\Entity\Reserve;
use App\Entity\ReserveType;
use App\Entity\Setting;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Exceptions\FormException;
use App\Service\AttachmentService;
use App\Service\ReserveService;
use App\Service\SettingsService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class TruckArrivalController extends AbstractController {

    #[Route("/get-truck-arrival-default-unloading-location", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getTruckArrivalDefaultUnloadingLocation(EntityManagerInterface $manager, SettingsService $settingsService): JsonResponse
    {
        $truckArrivalDefaultUnloadingLocation = $settingsService->getValue($manager,Setting::TRUCK_ARRIVALS_DEFAULT_UNLOADING_LOCATION);

        return $this->json($truckArrivalDefaultUnloadingLocation);
    }

    #[Route("/get-truck-arrival-lines-number", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getTruckArrivalLinesNumber(EntityManagerInterface $manager): Response
    {
        $truckArrivalLineRepository = $manager->getRepository(TruckArrivalLine::class);
        $truckArrivalLinesNumber = $truckArrivalLineRepository->iterateAll();

        return $this->json($truckArrivalLinesNumber);
    }

    #[Route("/finish-truck-arrival", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishTruckArrival(Request                $request,
                                       EntityManagerInterface $entityManager,
                                       UniqueNumberService    $uniqueNumberService,
                                       ReserveService         $reserveService,
                                       AttachmentService      $attachmentService): Response
    {
        $data = $request->request;

        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $reserveTypeRepository = $entityManager->getRepository(ReserveType::class);

        $registrationNumber = $data->get('registrationNumber');
        $truckArrivalReserves = json_decode($data->get('truckArrivalReserves'), true);
        $truckArrivalLines = json_decode($data->get('truckArrivalLines'), true);
        $signatures = json_decode($data->get('signatures'), true) ?: [];


        $carrier = $carrierRepository->find($data->get('carrierId'));

        $driver = $data->get('driverId') ? $driverRepository->find($data->get('driverId')) : null;
        $unloadingLocation = $data->get('truckArrivalUnloadingLocationId') ? $locationRepository->find($data->get('truckArrivalUnloadingLocationId')) : null;

        try {
            $number = $uniqueNumberService->create($entityManager, null, TruckArrival::class, UniqueNumberService::DATE_COUNTER_FORMAT_TRUCK_ARRIVAL, new DateTime(), [$carrier->getCode()]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'msg' => $e->getMessage()
            ]);
        }

        $truckArrival = (new TruckArrival())
            ->setNumber($number)
            ->setCarrier($carrier)
            ->setOperator($this->getUser())
            ->setDriver($driver)
            ->setRegistrationNumber($registrationNumber)
            ->setUnloadingLocation($unloadingLocation)
            ->setCreationDate(new DateTime('now'));

        foreach ($truckArrivalReserves as $truckArrivalReserve) {
            $reserve = (new Reserve())
                ->setKind($truckArrivalReserve['kind'])
                ->setComment($truckArrivalReserve['comment'] ?? null)
                ->setQuantity($truckArrivalReserve['quantity'] ?? null)
                ->setQuantityType($truckArrivalReserve['quantityType'] ?? null);

            $truckArrival->addReserve($reserve);
            $entityManager->persist($reserve);
        }

        $reserves = [];
        foreach ($truckArrivalLines as $truckArrivalLine) {
            if(strlen($truckArrivalLine['number']) > 255) {
                throw new FormException('Le numéro de tracking transporteur ne doit pas dépasser 255 caractères');
            }

            $line = (new TruckArrivalLine())
                ->setNumber($truckArrivalLine['number']);

            if (isset($truckArrivalLine['reserve'])) {
                $lineReserve = (new Reserve())
                    ->setKind(Reserve::KIND_LINE)
                    ->setComment($truckArrivalLine['reserve']['comment'] ?? null);

                if ($truckArrivalLine['reserve']['reserveTypeId']) {
                    $reserveType = $reserveTypeRepository->find($truckArrivalLine['reserve']['reserveTypeId']);
                    $lineReserve->setReserveType($reserveType);
                }

                if ($truckArrivalLine['reserve']['photos']) {
                    foreach ($truckArrivalLine['reserve']['photos'] as $photo) {
                        $name = uniqid();
                        $attachmentService->createFile("$name.jpeg", file_get_contents($photo));

                        $attachment = new Attachment();
                        $attachment
                            ->setOriginalName("$name.jpeg")
                            ->setFileName("$name.jpeg")
                            ->setFullPath("/uploads/attachments/$name.jpeg");

                        $lineReserve->addAttachment($attachment);
                        $entityManager->persist($attachment);
                    }
                }

                $line->setReserve($lineReserve);
                $entityManager->persist($lineReserve);

                $reserves[] = $lineReserve;
            }


            $truckArrival->addTrackingLine($line);
            $entityManager->persist($line);
        }

        foreach ($signatures as $signature) {
            $name = uniqid();
            $attachmentService->createFile("$name.jpeg", file_get_contents($signature));

            $attachment = new Attachment();
            $attachment
                ->setOriginalName($truckArrival->getNumber() . "_signature_" . array_search($signature, $signatures) . ".jpeg")
                ->setFileName("$name.jpeg")
                ->setFullPath("/uploads/attachments/$name.jpeg");

            $truckArrival->addAttachment($attachment);
            $entityManager->persist($attachment);
        }

        $entityManager->persist($truckArrival);
        $entityManager->flush();

        $allAttachments = Stream::from($reserves)
            ->keymap(fn(Reserve $reserve) => [
                $reserve->getReserveType()->getId(),
                $reserve->getAttachments()->toArray()
            ], true)
            ->toArray();

        $reserveTypeIdToReserveTypeArray = Stream::from($reserves)
            ->keymap(fn(Reserve $reserve) => [$reserve->getReserveType()?->getId(), $reserve->getReserveType()])
            ->toArray();

        foreach ($allAttachments as $reserveTypeId => $attachments) {
            $attachments = Stream::from($attachments)->flatten()->toArray();
            $reserveType = $reserveTypeIdToReserveTypeArray[$reserveTypeId] ?? null;

            if ($reserveType) {
                $reserveService->sendTruckArrivalMail($truckArrival, $reserveType, $reserves, $attachments);
            }
        }

        return $this->json([
            'success' => true,
            'msg' => "Enregistrement"
        ]);
    }
}
