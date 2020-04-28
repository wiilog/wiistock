<?php

namespace App\Service;

use App\Entity\ParametrageGlobal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CSVExportService {

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    /**
     * TODO delete
     * @deprecated à supprimer quand il n'y aura plu d'export en JS
     * @param $cell
     * @return string
     */
    public function escapeCSV($cell) {
        return !empty($cell)
            ? ('"' . str_replace('"', '""', $cell) . '"')
            : '';
    }

    public function createCsvResponse(string $fileName, array $data, callable $mapper = null): Response {
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $wantsUFT8 = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true;

        $tmpCsvFileName = tempnam('', 'export_csv_');
        $tmpCsvFile = fopen($tmpCsvFileName, 'w');

        $index = 0;

        foreach ($data as $row) {
            if (isset($mapper)) {
                $row = $mapper($row, $index === 0);
            }

            $encodedRow = !$wantsUFT8
                ? array_map('utf8_decode', $row)
                : $row;

            fputcsv($tmpCsvFile, $encodedRow, ';');

            if ($index === 0) {
                $index++;
            }
        }
        fclose($tmpCsvFile);

        $response = new BinaryFileResponse($tmpCsvFileName);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
