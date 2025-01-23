<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use App\Serializer\SerializerUsageEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use WiiCommon\Helper\Stream;

class PurgeService {

    const FILE_NAME_DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private PackService               $packService,
        private SerializerInterface       $serializer,
        private ReceiptAssociationService $receiptAssociationService,
        private FormatService             $formatService,
        private DisputeService            $disputeService,
        private CSVExportService          $csvExportService,
    ) {}

    public function generateDataPurgeFileName(string $entityToArchive, DateTime $dateToArchive): string {

        $entityStrInFilename = $this->getEntityName($entityToArchive);

        // file name = ARC  + entityToArchive + today's date + _ + $dateToArchive + .csv
        $now = (new DateTime())->format(self::FILE_NAME_DATE_FORMAT);
        $date = $dateToArchive->format(self::FILE_NAME_DATE_FORMAT);

        return "ARC_{$entityStrInFilename}_{$now}_{$date}.csv";
    }

    private function getEntityName(string $entity): string {
        return match ($entity) {
            Arrivage::class => "Arrivage",
            TrackingMovement::class => "MouvementDeTraca",
            Pack::class => "UL",
            ReceiptAssociation::class => "AssociationBR",
            Dispute::class => "Litige",
            default => Stream::explode("\\", $entity)->last(),
        };
    }

    public function archivePack(EntityManagerInterface $entityManager,
                                Pack                   $pack,
                                array                  $files): void {

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        if ($pack->isBasicUnit()
            || !$pack->getArrivage()
            || !$pack->getDispatchPacks()->isEmpty()
            || !$pack->getChildArticles()->isEmpty()
            || !$pack->getTrackingMovements()->isEmpty()
            || !$pack->getContent()->isEmpty()
            || $trackingMovementRepository->findOneBy(["packGroup" => $pack])) {
            return;
        }

        foreach ($pack->getReceiptAssociations() as $receiptAssociation) {
            $this->archiveReceiptAssociation($entityManager, $receiptAssociation, $pack, $files);
        }

        foreach ($pack->getProjectHistoryRecords() as $projectHistoryRecord) {
            $entityManager->remove($projectHistoryRecord);
        }

        foreach ($pack->getTransportHistories() as $transportHistory) {
            $entityManager->remove($transportHistory);
        }

        foreach ($pack->getPairings() as $pairing) {
            $entityManager->remove($pairing);
        }

        foreach ($pack->getDisputes() as $dispute) {
            $this->archiveDispute($entityManager, $dispute, $pack, $files);
        }

        $this->packService->putPackLine(
            $files[Pack::class],
            $this->serializer->normalize($pack, null, ["usage" => SerializerUsageEnum::CSV_EXPORT])
        );

        $pack->setGroup(null);
        // prevent Detached entity XXX cannot be removed
        if($entityManager->contains($pack)) {
            $entityManager->remove($pack);
        }
    }

    private function archiveReceiptAssociation(EntityManagerInterface $entityManager, ReceiptAssociation $receiptAssociation, Pack $pack, array $files): void {
        $this->receiptAssociationService->putReceiptAssociationLine(
            $files[ReceiptAssociation::class],
            [
                ...$this->serializer->normalize($receiptAssociation, null, ["usage" => SerializerUsageEnum::CSV_EXPORT]),
                "lastActionDate" => $this->formatService->datetime($pack->getLastAction()?->getDatetime()),
                "lastActionLocation" => $this->formatService->location($pack->getLastAction()?->getEmplacement()),
                "logisticUnit" => $this->formatService->pack($pack),
            ]
        );


        $receiptAssociation->removePack($pack);

        if($receiptAssociation->getLogisticUnits()->isEmpty()) {
            $receiptAssociation->setUser(null);
            $entityManager->remove($receiptAssociation);
        }
    }

    private function archiveDispute(EntityManagerInterface $entityManager, Dispute $dispute, Pack $pack, array $files): void {
        $this->disputeService->putDisputeLine(
            $files[Dispute::class],
            $dispute,
            [
                "packs" => [$pack],
            ]
        );

        $dispute->removePack($pack);

        if($dispute->getPacks()->isEmpty()) {
            $entityManager->remove($dispute);
        }
    }

    /**
     * @return resource[] Associated array className => fopen result
     */
    public function createAndOpenPurgeFiles(DateTime               $dateToArchive,
                                            array                  $entitiesToArchive,
                                            FileSystem             $filesystem,
                                            array                  $sortedColumns): array {
        $files = [];

        $fileNames = Stream::from($entitiesToArchive)
            ->keyMap(fn($entityToArchive) => [
                $entityToArchive,
                $this->generateDataPurgeFileName($entityToArchive, $dateToArchive),
            ])
            ->toArray();

        foreach ($fileNames as $entityToArchive => $fileName) {
            // if directory self::TEMPORARY_FOLDER does not exist, create it
            if (!$filesystem->isDir()) {
                $filesystem->mkdir();
            }

            $fileExists = $filesystem->exists($fileName);
            $absoluteCachePath = $filesystem->getRoot();
            $file = fopen($absoluteCachePath . $fileName, 'a');

            if (!$fileExists) {
                $this->csvExportService->putLine($file, $this->getFileHeader($entityToArchive, $sortedColumns));
            }
            $files[$entityToArchive] = $file;
        }

        return $files;
    }

    public function getFileHeader(string $className,
                                  array  $sortedColumns): array {
        return match ($className) {
            Arrivage::class => $sortedColumns[Arrivage::class]["labels"] ?? [],
            TrackingMovement::class => $sortedColumns[TrackingMovement::class]["labels"] ?? [],
            Pack::class => $this->packService->getCsvHeader(),
            ReceiptAssociation::class => $this->receiptAssociationService->getCsvHeader(),
            Dispute::class => $this->disputeService->getCsvHeader(),
        };
    }
}
