<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\Setting;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WiiCommon\Helper\Stream;

class CSVExportService {

    private bool|null $wantsUTF8 = null;

    public function __construct(private EntityManagerInterface    $entityManager,
                                private SettingsService           $settingsService,
                                private PackService               $packService,
                                private ReceiptAssociationService $receiptAssociationService,
                                private DisputeService            $disputeService){}

    /**
     * @deprecated Use CSVExportService::stream instead
     * @param array $data
     * @param array|null $csvHeader
     * @param callable|null $flatMapper
     * @return string
     */
    public function createCsvFile(array $data, ?array $csvHeader = null, ?callable $flatMapper = null): string {
        $tmpCsvFileName = tempnam('', 'export_csv_');
        $tmpCsvFile = fopen($tmpCsvFileName, 'w');

        if (isset($csvHeader)) {
            $this->putLine($tmpCsvFile, $csvHeader);
        }

        foreach ($data as $row) {
            if (isset($flatMapper)) {
                $rows = $flatMapper($row);

                foreach ($rows as $subRows) {
                    $this->putLine($tmpCsvFile, $subRows);
                }
            }
            else {
                $this->putLine($tmpCsvFile, $row);
            }
        }

        fclose($tmpCsvFile);

        return $tmpCsvFileName;
    }

    /**
     * @deprecated Use CSVExportService::stream instead
     * @param string $fileName
     * @param array $data
     * @param array|null $csvHeader
     * @param callable|null $flatMapper
     * @return Response
     */
    public function createBinaryResponseFromData(string $fileName, array $data, array $csvHeader = null, callable $flatMapper = null): Response {
        $tempFileName = $this->createCsvFile($data, $csvHeader, $flatMapper);
        return $this->createBinaryResponseFromFile($tempFileName, $fileName);
    }

    /**
     * @deprecated Use CSVExportService::stream instead
     * @param string $tempFileName
     * @param string $fileName
     * @return Response
     */
    public function createBinaryResponseFromFile(string $tempFileName, string $fileName): Response {
        $response = new BinaryFileResponse($tempFileName);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    public function putLine($handle, array $row): void {
        $this->wantsUTF8 = $this->wantsUTF8 !== null ? $this->wantsUTF8 : ($this->settingsService->getValue($this->entityManager, Setting::USES_UTF8) ?? true);

        $encodedRow = !$this->wantsUTF8
            ? Stream::from($row)
                ->map(static fn(?string $value) => (
                    $value !== null && $value !== "" && mb_check_encoding($value, "UTF-8")
                        ? mb_convert_encoding($value, "ISO-8859-1", "UTF-8")
                        : $value
                ))
                ->toArray()
            : $row;

        fputcsv($handle, $encodedRow, ';');
    }

    /**
     * Streams exports to the user line by line
     *
     * @param callable $generator Function that generates a CSV line
     * @param string $name Name of the file
     * @param array|null $headers CSV headers
     * @return StreamedResponse
     */
    public function streamResponse(callable $generator, string $name, ?array $headers = null): StreamedResponse {
        $response = new StreamedResponse(function () use ($generator, $headers) {
            $output = fopen("php://output", "wb");
            if ($headers) {
                $firstCell = $headers[0] ?? null;
                if (is_array($firstCell)) {
                    foreach ($headers as $headerLine) {
                        $this->putLine($output, $headerLine);
                    }
                }
                else {
                    $this->putLine($output, $headers);
                }
            }

            $generator($output);
            fclose($output);
        });

        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name);

        $response->headers->set('Content-Type', "text/csv");
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    public function createAndOpenDataArchivingFiles(array $fileNames,FileSystem $filesystem, string $absoluteCachePath, array $columnsSorted = []): array {
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

                $this->putLine($file, $fileHeader);
            }
            $files[$entityToArchive] = $file;
        }

        return $files;
    }
}
