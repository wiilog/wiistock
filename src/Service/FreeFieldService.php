<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\FreeFieldEntity;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class FreeFieldService
{
    public function serializeValue(array $valeurChampLibre): ?string
    {
        if (in_array($valeurChampLibre['typage'], [FreeField::TYPE_DATE, FreeField::TYPE_DATETIME])
            && !empty($valeurChampLibre['valeur'])) {
            try {
                $valeurChampLibre['valeur'] = str_replace('T', ' ', $valeurChampLibre['valeur']);
                $champLibreDateTime = new DateTime($valeurChampLibre['valeur'], new DateTimeZone('Europe/Paris'));
                $hourFormat = ($valeurChampLibre['typage'] === FreeField::TYPE_DATETIME) ? ' H:i' : '';
                $formattedValue = $champLibreDateTime->format("d/m/Y$hourFormat");
            } catch (Throwable $ignored) {
                $formattedValue = $valeurChampLibre['valeur'];
            }
        } else {
            $formattedValue = $valeurChampLibre['valeur'];
        }
        return $formattedValue;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param array $freeFieldCategoryLabels
     * @return array[
     *     'freeFieldIds' => int[],
     *     'freeFieldsHeader' => string[],
     *     'freeFieldsIdToTyping' => array[int][string]
     * ]
     */
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

    public function manageJSONFreeField(FreeField $champLibre, $value): string {
        if($champLibre->getTypage() === FreeField::TYPE_BOOL) {
            $value = empty($value) || $value === "false" ? "0" : "1";
        } else if($champLibre->getTypage() === FreeField::TYPE_LIST_MULTIPLE) {
            $value = is_array($value)
                ? implode(';', $value)
                : implode(';', json_decode($value) ?: []);
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
            $categorieCL = $categorieCLRepository->findOneByLabel($categoryCLLabel);
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
            if ($freeFieldValue && isset($freeFields[$freeFieldId])) {
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

    public function getFreeFieldLabelToId(EntityManagerInterface $entityManager,
                                          string $categoryCLLabel,
                                          string $category) {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
        $categorieCL = $categorieCLRepository->findOneByLabel($categoryCLLabel);

        $champs = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        return array_reduce($champs, function (array $accumulator, array $freeField) {
            $accumulator[trim(mb_strtolower($freeField['label']))] = $freeField['id'];
            return $accumulator;
        }, []);
    }

}
