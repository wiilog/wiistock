<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\FreeField\FreeField;
use App\Entity\FreeField\FreeFieldManagementRule;
use App\Entity\Language;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\ImportException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class FreeFieldService {

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    public function createExportArrayConfig(EntityManagerInterface $entityManager,
                                            array $freeFieldCategoryLabels,
                                            ?array $typeCategories = []): array
    {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $freeFields = $freeFieldsRepository->findByFreeFieldCategoryLabels($freeFieldCategoryLabels, $typeCategories);

        $user = $this->userService->getUser();

        $defaultLanguage = $this->languageService->getDefaultSlug();
        $userLanguage = $user?->getLanguage() ?: $this->languageService->getDefaultSlug();

        $config = [
            'freeFields' => [],
            'freeFieldsHeader' => []
        ];

        foreach ($freeFields as $freeField) {
            $config['freeFieldsHeader'][] = $freeField->getLabelIn($userLanguage, $defaultLanguage)
                ?: $freeField->getLabel();
            $config['freeFields'][$freeField->getId()] = $freeField;
        }

        return $config;
    }

    public function getListFreeFieldConfig(EntityManagerInterface $entityManager, string|array $freeFieldCategoryLabel, string|array $typeCategoryLabel): array {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);
        $freeFieldCategoryLabel = is_array($freeFieldCategoryLabel) ? $freeFieldCategoryLabel : [$freeFieldCategoryLabel];
        $typeCategoryLabel = is_array($typeCategoryLabel) ? $typeCategoryLabel : [$typeCategoryLabel];

        return Stream::from($freeFieldsRepository->findByCategoriesTypeAndCategoriesCL($typeCategoryLabel, $freeFieldCategoryLabel))
            ->keymap(fn(FreeField $freeField) => [$freeField->getId(), $freeField])
            ->toArray();
    }


    public function manageFreeFields(mixed                  $entity,
                                     array                  $data,
                                     EntityManagerInterface $entityManager,
                                     Utilisateur            $user = null): void {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $userLanguage = $user?->getLanguage() ?: $this->languageService->getDefaultLanguage();
        $defaultLanguage = $this->languageService->getDefaultLanguage();

        $freeFields = [];
        foreach (array_keys($data) as $freeFieldId) {
            if (is_int($freeFieldId)) {
                $freeField = $freeFieldRepository->find($freeFieldId);

                if ($freeField) {
                    $this->processErrors($freeField, $data, $userLanguage, $defaultLanguage);
                    $freeFields[$freeField->getId()] = $this->manageJSONFreeField($freeField, $data[$freeFieldId], $user);
                }
            }
        }

        // concat new free fields with existing ones
        $freeFields = $freeFields + $entity->getFreeFields();
        $entity->setFreeFields($freeFields);
    }

    public function manageImportFreeFields(EntityManagerInterface $entityManager,
                                           array                  $freeFieldColumns,
                                           mixed                  $freeFieldEntity,
                                           bool                   $isNewEntity,
                                           array                  $row): void
    {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $missingFreeFields = [];

        $freeFieldCategory = match (true) {
            $freeFieldEntity instanceof ReferenceArticle => CategorieCL::REFERENCE_ARTICLE,
            $freeFieldEntity instanceof Article => CategorieCL::ARTICLE,
            $freeFieldEntity instanceof Demande => CategorieCL::DEMANDE_LIVRAISON,
            $freeFieldEntity instanceof Dispatch => CategorieCL::DEMANDE_DISPATCH,
            default => throw new Exception("Unhandled free field category")
        };

        if ($freeFieldEntity->getType()?->getId()) {
            $freeFieldManagementRules  = $freeFieldEntity->getType()?->getFreeFieldManagementRules();
        } else {
            $freeFieldManagementRules = [];
        }

        $freeFieldIds = array_keys($freeFieldColumns);
        $requiredGetter = $isNewEntity ? 'isRequiredCreate' : 'isRequiredEdit';
        foreach ($freeFieldManagementRules as $freeFieldManagementRule) {
            $freeFIeld = $freeFieldManagementRule->getFreeField();
            if (!in_array($freeFIeld->getId(), $freeFieldIds) && $freeFieldManagementRule->$requiredGetter()) {
                $missingFreeFields[] = $freeFIeld->getLabel();
            }
        }

        if (!empty($missingFreeFields)) {
            $message = count($missingFreeFields) > 1
                ? 'Les champs ' . join(', ', $missingFreeFields) . ' sont obligatoires'
                : 'Le champ ' . $missingFreeFields[0] . ' est obligatoire';
            $message .= ' à la ' . ($isNewEntity ? 'création.' : 'modification.');
            throw new ImportException($message);
        }

        $freeFieldsToInsert = $freeFieldEntity->getFreeFields();

        $ManagementRulesByFreeFieldId = Stream::from($freeFieldManagementRules)
            ->keymap(fn(FreeFieldManagementRule $rule) => [$rule->getFreeField()->getId(), $rule])
            ->toArray();

        foreach ($freeFieldColumns as $freeFieldId => $column) {
            $freeFieldManagementRule = $ManagementRulesByFreeFieldId[$freeFieldId] ?? null;
            if($freeFieldManagementRule) {

                $freeField = $freeFieldManagementRule->getFreeField();
                $value = match ($freeField->getTypage()) {
                    FreeField::TYPE_BOOL => in_array($row[$column], ['Oui', 'oui', 1, '1']),
                    FreeField::TYPE_DATE => $this->checkImportDate($row[$column], 'd/m/Y', 'Y-m-d', 'jj/mm/AAAA', $freeField),
                    FreeField::TYPE_DATETIME => $this->checkImportDate($row[$column], 'd/m/Y H:i', 'Y-m-d\TH:i', 'jj/mm/AAAA HH:MM', $freeField),
                    FreeField::TYPE_LIST => $this->checkImportList($row[$column], $freeField, false),
                    FreeField::TYPE_LIST_MULTIPLE => $this->checkImportList($row[$column], $freeField, true),
                    default => $row[$column],
                };

                $freeFieldsToInsert[$freeField->getId()] = strval(is_bool($value) ? intval($value) : $value);
            }
        }

        $freeFieldEntity->setFreeFields($freeFieldsToInsert);
    }

    private function checkImportDate(string $dateString, string $format, string $outputFormat, string $errorFormat, FreeField $champLibre): ?string
    {
        $response = null;
        if ($dateString !== "") {
            try {
                $date = DateTime::createFromFormat($format, $dateString);
                if (!$date) {
                    throw new Exception('Invalid format');
                }
                $response = $date->format($outputFormat);
            } catch (Exception $ignored) {
                $message = 'La date fournie pour le champ "' . $champLibre->getLabel() . '" doit être au format ' . $errorFormat . '.';
                throw new ImportException($message);
            }
        }
        return $response;
    }

    private function checkImportList(string $element, FreeField $champLibre, bool $isMultiple): ?string
    {
        $response = null;
        if ($element !== "") {
            $elements = $isMultiple ? explode(";", $element) : [$element];
            foreach ($elements as $listElement) {
                if (!in_array($listElement, $champLibre->getElements())) {
                    throw new ImportException('La ou les valeurs fournies pour le champ "' . $champLibre->getLabel() . '"'
                        . 'doivent faire partie des valeurs du champ libre ('
                        . implode(",", $champLibre->getElements()) . ').');
                }
            }
            $response = $element;
        }
        return $response;
    }


    public function processErrors(FreeField $freeField,
                                  array     $data,
                                  Language  $userLanguage,
                                  Language  $defaultLanguage): void {
        if($freeField->getTypage() === FreeField::TYPE_TEXT) {
            $value = trim($data[$freeField->getId()]);
            $name = $freeField->getLabelIn($userLanguage, $defaultLanguage);

            $minCharactersLength = $freeField->getMinCharactersLength() ?? null;
            if ($minCharactersLength && $value && (strlen($value) < $minCharactersLength)) {
                $errorMessage = $this->translationService->translate('Général', null, "Modale", "Le nombre de caractères du champ {1} ne peut être inférieur à {2}.", [
                    1 => "<strong>{$name}</strong>",
                    2 => $minCharactersLength,
                ]);
                throw new FormException($errorMessage);
            }

            $maxCharactersLength = $freeField->getMaxCharactersLength() ?? null;
            if ($maxCharactersLength && $value && (strlen($value) > $maxCharactersLength)) {
                $errorMessage = $this->translationService->translate('Général', null, "Modale", "Le nombre de caractères du champ {1} ne peut être supérieur à {2}.", [
                    1 => "<strong>{$name}</strong>",
                    2 => $maxCharactersLength,
                ]);
                throw new FormException($errorMessage);
            }
        }
    }

    public function manageJSONFreeField(FreeField   $freeField,
                                                    $value,
                                        Utilisateur $user = null): string {
        $userLanguage = $this->getUser($user)?->getLanguage()
            ?: $this->languageService->getDefaultSlug();

        switch ($freeField->getTypage()) {
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

                $values = Stream::explode(';', $value)
                    ->filter()
                    ->toArray();

                $translatedValues = $this->translationService->translateFreeFieldListValues(
                    [$userLanguage, $this->languageService->getDefaultSlug()],
                    Language::FRENCH_SLUG,
                    $freeField,
                    $values
                );

                $value = Stream::from($translatedValues ?: [])->join(';')
                    ?: null;
                break;

            case FreeField::TYPE_LIST:
                $value = $this->translationService->translateFreeFieldListValues(
                    [$userLanguage, $this->languageService->getDefaultSlug()],
                    Language::FRENCH_SLUG,
                    $freeField,
                    $value
                );

                break;

            case FreeField::TYPE_DATETIME:
                //save the date in d/m/Y H:i
                if(preg_match("/(\d{2})\/(\d{2})\/(\d{4})T(\d{2}):(\d{2})/", $value)) {
                    $date = DateTime::createFromFormat("d/m/Y H:i", $value);
                    $value = $value ? $date->format("Y-m-dTH:i") : null;
                } else if ($user && $user->getDateFormat()) {
                    $format = $user->getDateFormat() . ' H:i';
                    $date = DateTime::createFromFormat($format, $value);
                    $value = $value ? $date->format($format) : null;
                }
                break;

            default:
                break;
        }

        return strval($value);
    }

    public function getFilledFreeFieldArray(EntityManagerInterface $entityManager,
                                                                   $entity,
                                            array                  $displayedOptions,
                                            Utilisateur            $user = null) {
        $defaultLanguage = $this->languageService->getDefaultSlug();
        $userLanguage = $user?->getLanguage() ?: $this->languageService->getDefaultSlug();
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

        $freeFields = Stream::from($type->getFreeFieldManagementRules() ?: [])
            ->map(fn(FreeFieldManagementRule $rule) => $rule->getFreeField())
            ->toArray();

        $freeFieldValues = $entity->getFreeFields();
        return Stream::from($freeFields ?? [])
            ->map(fn (FreeField $freeField) => [
                'label' => $freeField->getLabelIn($userLanguage, $defaultLanguage)
                    ?: $freeField->getLabel(),
                'isRaw' => true,
                'value' => $this->formatService->freeField($freeFieldValues[$freeField->getId()] ?? null, $freeField, $user)
            ])
            ->toArray();
    }

    private function getUser(?Utilisateur $user = null): ?Utilisateur {
        return $user ?? $this->userService->getUser();
    }
}
