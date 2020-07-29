<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\FreeFieldEntity;
use App\Entity\Reception;
use App\Entity\Type;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class ChampLibreService
{

    private $CSVExportService;
    private $entityManager;

    public function __construct(CSVExportService $CSVExportService,
                                EntityManagerInterface $entityManager)
    {
        $this->CSVExportService = $CSVExportService;
        $this->entityManager = $entityManager;
    }


    public function formatValeurChampLibreForDatatable(array $valeurChampLibre): ?string
    {
        if (in_array($valeurChampLibre['typage'], [ChampLibre::TYPE_DATE, ChampLibre::TYPE_DATETIME])
            && !empty($valeurChampLibre['valeur'])) {
            try {
                $valeurChampLibre['valeur'] = str_replace('T', ' ', $valeurChampLibre['valeur']);
                $champLibreDateTime = new DateTime($valeurChampLibre['valeur'], new DateTimeZone('Europe/Paris'));
                $hourFormat = ($valeurChampLibre['typage'] === ChampLibre::TYPE_DATETIME) ? ' H:i' : '';
                $formattedValue = $champLibreDateTime->format("d/m/Y$hourFormat");
            } catch (Throwable $ignored) {
                $formattedValue = $valeurChampLibre['valeur'];
            }
        } else {
            $formattedValue = $valeurChampLibre['valeur'];
        }
        return $formattedValue;
    }


    public function manageFreeFields(FreeFieldEntity $entity,
                                     array $data,
                                     EntityManagerInterface $entityManager)
    {
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $freeFields = [];
        $champsLibresKey = array_keys($data);
        foreach ($champsLibresKey as $champs) {
            if (gettype($champs) === 'integer') {
                $champLibre = $champLibreRepository->find($champs);
                if ($champLibre) {
                    $freeFields[$champLibre->getId()] = $this->manageJSONFreeField($champLibre, $data[$champs]);
                }
            }
        }
        $entity
            ->setFreeFields($freeFields);
    }

    public function manageJSONFreeField(ChampLibre $champLibre, $value): string
    {
        $value = $champLibre->getTypage() === ChampLibre::TYPE_BOOL
            ? empty($value)
                ? "0"
                : "1"
            : (is_array($value)
                ? implode(';', $value)
                : $value);

        return strval($value);
    }

}
