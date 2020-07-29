<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\FreeFieldEntity;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class FreeFieldService
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

    public function getFilledFreeFieldArray(EntityManagerInterface $entityManager,
                                            FreeFieldEntity $freeFieldEntity,
                                            ?string $categoryCLLabel,
                                            string $category) {
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $categorieCLRepository =  $entityManager->getRepository(CategorieCL::class);

        if (!empty($categoryCLLabel)) {
            $categorieCL = $categorieCLRepository->findOneByLabel($categoryCLLabel);
            $freeFieldResult = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        }
        else {
            $freeFieldResult = $champLibreRepository->findByCategoryTypeLabels([$category]);
        }

        $freeFields = array_reduce($freeFieldResult, function(array $acc, $freeField) {
            $id = $freeField instanceof ChampLibre ? $freeField->getId() : $freeField['id'];
            $label = $freeField instanceof ChampLibre ? $freeField->getLabel() : $freeField['label'];
            $typage = $freeField instanceof ChampLibre ? $freeField->getTypage() : $freeField['typage'];

            $acc[$id] = [
                'label' => $label,
                'typage' => $typage
            ];

            return $acc;
        }, []);

        $detailsChampLibres = [];
        foreach ($freeFieldEntity->getFreeFields() as $freeFieldId => $freeFieldValue) {
            if ($freeFieldValue) {
                $detailsChampLibres[] = [
                    'label' => $freeFields[$freeFieldId]['label'],
                    'value' => $this->formatValeurChampLibreForDatatable([
                        'valeur' => $freeFieldValue,
                        'typage' => $freeFields[$freeFieldId]['typage']
                    ])
                ];
            }
        }

        return $detailsChampLibres;
    }

}
