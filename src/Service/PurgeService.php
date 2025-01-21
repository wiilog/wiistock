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
        private PackService $packService,
        private SerializerInterface       $serializer,
        private ReceiptAssociationService $receiptAssociationService,
        private FormatService             $formatService,
        private DisputeService            $disputeService,
        private CSVExportService          $csvExportService,
    ) {}

    public function generateDataPurgeFileName(string $entityToArchive, DateTime $dateToArchive): string {
        // file name = ARC  + entityToArchive + today's date + _ + $dateToArchive + .csv
        $now = (new DateTime())->format(self::FILE_NAME_DATE_FORMAT);
        $date = $dateToArchive->format(self::FILE_NAME_DATE_FORMAT);

        return "ARC_{$entityToArchive}_{$now}_{$date}.csv";
    }

    public function getEntityName(string $entity): string {
        return match ($entity) {
            Arrivage::class => "Arrivage",
            TrackingMovement::class => "MovementDeTracabilité",
            Pack::class => "UL",
            ReceiptAssociation::class => "AssociationBR",
            Dispute::class => "Litige",
            default => Stream::explode("\\", $entity)->last(),
        };
    }

    public function archivePack(EntityManagerInterface $entityManager, Pack $pack, array $files): void {
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

    public function createAndOpenPurgeFiles(array $fileNames, FileSystem $filesystem, string $absoluteCachePath, array $columnsSorted = []): array {
        $files = [];
        foreach ($fileNames as $entityToArchive => $fileName) {
            // if directory self::TEMPORARY_FOLDER does not exist, create it
            if (!$filesystem->isDir()) {
                $filesystem->mkdir();
            }

            $fileExists = $filesystem->exists($fileName);
            $file = fopen($absoluteCachePath . $fileName, 'a');

            if (!$fileExists) {
                //generate the header for the file based on the entity
                $fileHeader = match ($entityToArchive) {
                    TrackingMovement::class, Arrivage::class => $columnsSorted["labels"],
                    Pack::class => $this->packService->getCsvHeader(),
                    ReceiptAssociation::class => $this->receiptAssociationService->getCsvHeader(),
                    Dispute::class => $this->disputeService->getCsvHeader(),
                };

                $this->csvExportService->putLine($file, $fileHeader);
            }
            $files[$entityToArchive] = $file;
        }

        return $files;
    }
}
