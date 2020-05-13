<?php

namespace App\Service;

use App\Entity\ChampLibre;
use App\Entity\ValeurChampLibre;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class ValeurChampLibreService {

    private $CSVExportService;
    private $entityManager;

    public function __construct(CSVExportService $CSVExportService,
                                EntityManagerInterface $entityManager) {
        $this->CSVExportService = $CSVExportService;
        $this->entityManager = $entityManager;
    }


    public function formatValeurChampLibreForDatatable(array $valeurChampLibre): ?string {
        if (in_array($valeurChampLibre['typage'], [ChampLibre::TYPE_DATE, ChampLibre::TYPE_DATETIME])
            && !empty($valeurChampLibre['valeur'])) {
            try {
                $valeurChampLibre['valeur'] = str_replace('T', ' ', $valeurChampLibre['valeur']);
                $champLibreDateTime = new DateTime($valeurChampLibre['valeur'], new DateTimeZone('Europe/Paris'));
                $hourFormat = ($valeurChampLibre['typage'] === ChampLibre::TYPE_DATETIME) ? ' H:i' : '';
                $formattedValue = $champLibreDateTime->format("d/m/Y$hourFormat");
            }
            catch(Throwable $ignored) {
                $formattedValue = $valeurChampLibre['valeur'];
            }
        }
        else {
            $formattedValue = $valeurChampLibre['valeur'];
        }
        return $formattedValue;
    }

    public function formatValeurChampLibreForShow(ValeurChampLibre $valeurChampLibre): ?string {
        $typage = $valeurChampLibre->getChampLibre()->getTypage();
        $value = $valeurChampLibre->getValeur();

        if ($typage === ChampLibre::TYPE_BOOL) {
            $formattedValue = (($value == 1) ? 'oui' : 'non');
        }
        else if ($typage === ChampLibre::TYPE_LIST_MULTIPLE
            && !empty($value)) {
            $formattedValue = str_replace(';', ',', $value);
        }
        else {
            $formattedValue = $this->formatValeurChampLibreForDatatable([
                'valeur' => $value,
                'typage' => $typage
            ]);
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

    /**
     * @param ChampLibre|int $champ
     * @param $value
     * @return ValeurChampLibre
     */
    public function createValeurChampLibre($champ, $value): ValeurChampLibre {
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
        /** @var ChampLibre $champLibre */
        $champLibre = ($champ instanceof ChampLibre)
            ? $champ
            : $champLibreRepository->find($champ);
        $valeurChampLibre = new ValeurChampLibre();

        $valeurChampLibre->setChampLibre($champLibre);

        $this->updateValue($valeurChampLibre, $value);

        return $valeurChampLibre;
    }

    public function updateValue(ValeurChampLibre $valeurChampLibre, $value): void {
        $converterValue = [
            'true' => 1,
            'false' => 0
        ];

        $formattedValue = is_array($value)
            ? implode(";", $value)
            : ($converterValue[$value] ?? $value);

        $valeurChampLibre->setValeur($formattedValue);
    }
}
