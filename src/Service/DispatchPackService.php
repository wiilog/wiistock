<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Nature;
use App\Entity\Printer;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use ZplGenerator\Client\SocketClient;

class DispatchPackService {

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public PackService $packService;

    #[Required]
    public PrinterService $printerService;

    #[Required]
    public FormatService $formatService;


    public function generatePacks(array                  $packs,
                                  Dispatch               $dispatch,
                                  ?Printer               $printer,
                                  Utilisateur            $currentUser,
                                  EntityManagerInterface $manager): array {
        if ($printer) {
            try {
                $client = SocketClient::create($printer->getAddress(), PrinterService::DEFAULT_PRINTER_PORT, PrinterService::DEFAULT_PRINTER_TIMEOUT);
                $zebraPrinter = $this->printerService->getPrinter($printer);
            }
            catch(Throwable) {
                return [
                    'success' => false,
                    'msg' => "Problème de communication avec l'imprimante, la génération des unités logistiques a échoué.",
                ];
            }
        }

        $now = new DateTime();
        $packsToPrint = [];
        $packsList = [];
        foreach ($packs as $nature => $number) {
            for ($i = 0; $i < $number; $i++) {
                $nature = $manager->find(Nature::class, $nature);
                $pack = $this->packService->createPack($manager, ['dispatch' => $dispatch, 'nature' => $nature]);

                $manager->persist($pack);

                $dispatchPack = (new DispatchPack())
                    ->setPack($pack)
                    ->setQuantity(1)
                    ->setDispatch($dispatch)
                    ->setFromGeneration(true)
                    ->setTreated(false);

                $packsToPrint[] = $dispatchPack;
                $manager->persist($dispatchPack);
                $dispatch->addDispatchPack($dispatchPack);

                $trackingMovement = $this->trackingMovementService->createTrackingMovement(
                    $pack,
                    $dispatch->getLocationFrom(),
                    $currentUser,
                    $now,
                    false,
                    false,
                    TrackingMovement::TYPE_DEPOSE
                );

                $trackingMovement->setDispatch($dispatch);

                $manager->persist($trackingMovement);
                $manager->flush();

                $packsList[] = $dispatchPack->serialize($this->formatService);
            }
        }

        if ($printer) {
            $this->printerService->printDispatchPacks($zebraPrinter, $client, $dispatch, $packsToPrint);
        }

        return [
            'success' => true,
            'msg' => 'Les unités logisitiques ont bien été générées.',
            'packs' => $packsList
        ];
    }
}
