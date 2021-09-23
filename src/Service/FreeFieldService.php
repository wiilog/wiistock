<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\FreeFieldEntity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class FreeFieldService {

    public function serializeValue(array $valeurChampLibre): ?string {
        if (in_array($valeurChampLibre['typage'], [FreeField::TYPE_DATE, FreeField::TYPE_DATETIME])
            && !empty($valeurChampLibre['valeur'])) {
            try {
                $valeurChampLibre['valeur'] = str_replace('T', ' ', $valeurChampLibre['valeur']);
                $champLibreDateTime = new DateTime($valeurChampLibre['valeur']);
                $hourFormat = ($valeurChampLibre['typage'] === FreeField::TYPE_DATETIME) ? ' H:i' : '';
                $formattedValue = $champLibreDateTime->format("d/m/Y$hourFormat");
            } catch(Throwable $ignored) {
                $formattedValue = $valeurChampLibre['valeur'];
            }
        } else if($valeurChampLibre['typage'] == FreeField::TYPE_BOOL) {
            $formattedValue = ($valeurChampLibre['valeur'] === null || $valeurChampLibre['valeur'] === '')
                ? ''
                : ($valeurChampLibre['valeur'] ? "Oui" : "Non");
        } else {
            $formattedValue = $valeurChampLibre['valeur'];
        }
        return $formattedValue;
    }

    public function createExportArrayConfig(EntityManagerInterface $entityManager,
                                            array $freeFieldCategoryLabels): array
    {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $freeFields = $freeFieldsRepository->findByFreeFieldCategoryLabels($freeFieldCategoryLabels);

        $config = [
            'freeFieldIds' => [],
            'freeFieldsHeader' => [],
            'freeFieldsIdToTyping' => []
        ];

        foreach ($freeFields as $freeField) {
            $config['freeFieldIds'][] = $freeField->getId();
            $config['freeFieldsHeader'][] = $freeField->getLabel();
            $config['freeFieldsIdToTyping'][$freeField->getId()] = $freeField->getTypage();
        }

        return $config;
    }


    public function manageFreeFields(FreeFieldEntity $entity,
                                     array $data,
                                     EntityManagerInterface $entityManager)
    {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $freeFields = [];
        $champsLibresKey = array_keys($data);
        foreach ($champsLibresKey as $field) {
            if (gettype($field) === 'integer') {
                $champLibre = $champLibreRepository->find($field);
                if ($champLibre) {
                    $freeFields[$champLibre->getId()] = $this->manageJSONFreeField($champLibre, $data[$field]);
                }

            }
        }

        $entity->setFreeFields($freeFields);
    }

    public function manageJSONFreeField(FreeField $champLibre, $value): string {
        switch ($champLibre->getTypage()) {
            case FreeField::TYPE_BOOL:
                $value = empty($value) || $value === "false" ? "0" : "1";
                break;

            case FreeField::TYPE_LIST_MULTIPLE:
                if (is_array($value)) {
                    $value = implode(';', $value);
                }
                else {
                    $decoded = json_decode($value, true);
                    $value = json_last_error() !== JSON_ERROR_NONE
                        ? $value
                        : implode(';', $decoded ?: []);
                }
                break;

            case FreeField::TYPE_DATETIME:
                //save the date in d/m/Y H:i
                if(preg_match("/(\d{2})\/(\d{2})\/(\d{4})T(\d{2}):(\d{2})/", $value)) {
                    $date = DateTime::createFromFormat("d/m/Y H:i", $value);
                    $value = $date->format("Y-m-dTH:i");
                }
                break;

            default:
                break;
        }

        return strval($value);
    }

    public function getFilledFreeFieldArray(EntityManagerInterface $entityManager,
                                            FreeFieldEntity $freeFieldEntity,
                                            ?string $categoryCLLabel,
                                            string $category) {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository =  $entityManager->getRepository(CategorieCL::class);

        if (!empty($categoryCLLabel)) {
            $categorieCL = $categorieCLRepository->findOneBy(['label' => $categoryCLLabel]);
            $freeFieldResult = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        }
        else {
            $freeFieldResult = $champLibreRepository->findByCategoryTypeLabels([$category]);
        }

        $freeFields = array_reduce($freeFieldResult, function(array $acc, $freeField) {
            $id = $freeField instanceof FreeField ? $freeField->getId() : $freeField['id'];
            $label = $freeField instanceof FreeField ? $freeField->getLabel() : $freeField['label'];
            $typage = $freeField instanceof FreeField ? $freeField->getTypage() : $freeField['typage'];

            $acc[$id] = [
                'label' => $label,
                'typage' => $typage
            ];

            return $acc;
        }, []);

        $detailsChampLibres = [];
        foreach ($freeFieldEntity->getFreeFields() as $freeFieldId => $freeFieldValue) {
            if ($freeFieldValue !== "" && $freeFieldValue !== null && isset($freeFields[$freeFieldId])) {
                $detailsChampLibres[] = [
                    'label' => $freeFields[$freeFieldId]['label'],
                    'value' => $this->serializeValue([
                        'valeur' => $freeFieldValue,
                        'typage' => $freeFields[$freeFieldId]['typage']
                    ])
                ];
            }
        }

        return $detailsChampLibres;
    }
}
