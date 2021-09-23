<?php

namespace App\Service;

use App\Entity\Interfaces\Serializable;
use App\Entity\ParametrageGlobal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CSVExportService {

    public static $SERIALIZABLE;

    private $entityManager;
    private $wantsUTF8;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $this->wantsUTF8 = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true;
    }

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

    public function putLine($handle, array $row) {
        $encodedRow = !$this->wantsUTF8
            ? array_map('utf8_decode', $row)
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

}

CSVExportService::$SERIALIZABLE = function(Serializable $serializable) {
    return [$serializable->serialize()];
};
