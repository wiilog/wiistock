<?php

namespace App\Service;

use App\Entity\ChampLibre;
use App\Entity\ValeurChampLibre;
use DateTime;
use DateTimeZone;

class ValeurChampLibreService {

    private $CSVExportService;

    public function __construct(CSVExportService $CSVExportService) {
        $this->CSVExportService = $CSVExportService;
    }


    public function formatValeurChampLibreForDatatable(array $valeurChampLibre): ?string {
        if (in_array($valeurChampLibre['typage'], [ChampLibre::TYPE_DATE, ChampLibre::TYPE_DATETIME])
            && !empty($valeurChampLibre['valeur'])) {
            $champLibreDateTime = new DateTime($valeurChampLibre['valeur'], new DateTimeZone('Europe/Paris'));
            $hourFormat = ($valeurChampLibre['typage'] === ChampLibre::TYPE_DATETIME) ? ' H:i' : '';
            $formattedValue = $champLibreDateTime->format("d/m/Y$hourFormat");
        }
        else {
            $formattedValue = $valeurChampLibre['valeur'];
        }
        return $formattedValue;
    }

    public function formatValeurChampLibreForExport(ValeurChampLibre $valeurChampLibre): ?string {
        $typage = $valeurChampLibre->getChampLibre()->getTypage();
        $value = $valeurChampLibre->getValeur();

        $formattedValue = $this->formatValeurChampLibreForDatatable([
            'valeur' => $value,
            'typage' => $typage
        ]);

        return !empty($formattedValue)
            ? $this->CSVExportService->escapeCSV($formattedValue)
            : '';
    }
}
