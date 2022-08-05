<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\Type;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Helper\FormatHelper;
use RuntimeException;
use WiiCommon\Helper\Stream;

class FreeFieldService {

    public function createExportArrayConfig(EntityManagerInterface $entityManager,
                                            array $freeFieldCategoryLabels,
                                            ?array $typeCategories = []): array
    {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $freeFields = $freeFieldsRepository->findByFreeFieldCategoryLabels($freeFieldCategoryLabels, $typeCategories);

        $config = [
            'freeFields' => [],
            'freeFieldsHeader' => []
        ];

        foreach ($freeFields as $freeField) {
            $config['freeFieldsHeader'][] = $freeField->getLabel();
            $config['freeFields'][$freeField->getId()] = $freeField;
        }

        return $config;
    }

    public function getListFreeFieldConfig(EntityManagerInterface $entityManager, string $freeFieldCategoryLabel, string $typeCategoryLabel): array {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $freeFieldCategoryRepository = $entityManager->getRepository(CategorieCL::class);

        $freeFieldCategory = $freeFieldCategoryRepository->findOneBy(['label' => $freeFieldCategoryLabel]);
        return Stream::from($freeFieldsRepository->findByCategoryTypeAndCategoryCL($typeCategoryLabel, $freeFieldCategory))
            ->keymap(fn(FreeField $freeField) => [$freeField->getId(), $freeField])
            ->toArray();
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
                        : (is_array($decoded)
                            ? implode(';', $decoded)
                            : $decoded);
                }
                break;

            case FreeField::TYPE_DATETIME:
                //save the date in d/m/Y H:i
                if(preg_match("/(\d{2})\/(\d{2})\/(\d{4})T(\d{2}):(\d{2})/", $value)) {
                    $date = DateTime::createFromFormat("d/m/Y H:i", $value);
                    $value = $date->format("Y-m-dTH:i");
                }
                break;

            case FreeField::TYPE_LIST:
                $value = $value === "null" ? "" : $value;
                break;

            default:
                break;
        }

        return strval($value);
    }

    public function getFilledFreeFieldArray(EntityManagerInterface $entityManager,
                                                                   $entity,
                                            array                  $displayedOptions) {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $freeFieldCategoryRepository = $entityManager->getRepository(CategorieCL::class);


        /** @var Type $type */
        $type = $displayedOptions['type'] ?? null;

        // for article & reference articles
        $freeFieldCategoryLabel = $displayedOptions['freeFieldCategoryLabel'] ?? null;
        $freeFieldCategory = $freeFieldCategoryLabel
            ? $freeFieldCategoryRepository->findOneBy(['label' => $freeFieldCategoryLabel])
            : null;

        if (!isset($type) && !isset($freeFieldCategory)) {
            throw new RuntimeException('Invalid options');
        }

        $freeFieldCriteria = [];
        if (isset($type)) {
            $freeFieldCriteria['type'] = $type;
        }
        if (isset($freeFieldCategory)) {
            $freeFieldCriteria['categorieCL'] = $freeFieldCategory;
        }

        $freeFields = $freeFieldsRepository->findBy($freeFieldCriteria);

        $freeFieldValues = $entity->getFreeFields();
        return Stream::from($freeFields ?? [])
            ->map(fn (FreeField $freeField) => [
                'label' => $freeField->getLabel(),
                'value' => FormatHelper::freeField($freeFieldValues[$freeField->getId()] ?? null, $freeField)
            ])
            ->toArray();
    }
}
