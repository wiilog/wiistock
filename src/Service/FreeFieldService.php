<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\FreeField;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Helper\FormatHelper;
use Throwable;

class FreeFieldService {

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


    public function manageFreeFields($entity,
                                     array $data,
                                     EntityManagerInterface $entityManager) {
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
                                                                   $freeFieldEntity,
                                            ?string                $categoryCLLabel,
                                            string                 $category) {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        if (!empty($categoryCLLabel)) {
            $categorieCL = $categorieCLRepository->findOneBy(['label' => $categoryCLLabel]);
            $freeFieldResult = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        }
        else {
            $freeFieldResult = $freeFieldsRepository->findByCategoryTypeLabels([$category]);
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
                $freeFieldEntity = $freeFieldsRepository->find($freeFieldId);
                $detailsChampLibres[] = [
                    'label' => $freeFields[$freeFieldId]['label'],
                    'value' => FormatHelper::freeField($freeFieldValue ?? '', $freeFieldEntity)
                ];
            }
        }

        return $detailsChampLibres;
    }
}
