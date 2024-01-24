<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

    public function getListFreeFieldConfig(EntityManagerInterface $entityManager, string $freeFieldCategoryLabel, string $typeCategoryLabel): array {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);

        return Stream::from($freeFieldsRepository->findByCategoryTypeAndCategoryCL($typeCategoryLabel, $freeFieldCategoryLabel))
            ->keymap(fn(FreeField $freeField) => [$freeField->getId(), $freeField])
            ->toArray();
    }


    public function manageFreeFields(mixed                  $entity,
                                     array                  $data,
                                     EntityManagerInterface $entityManager,
                                     Utilisateur            $user = null): void
    {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $userLanguage = $user?->getLanguage() ?: $this->languageService->getDefaultLanguage();
        $defaultLanguage = $this->languageService->getDefaultLanguage();

        $freeFields = [];
        foreach (array_keys($data) as $freeFieldId) {
            if (is_int($freeFieldId)) {
                $freeField = $freeFieldRepository->find($freeFieldId);

                if ($freeField) {
                    self::processErrors($freeField, $data, $userLanguage, $defaultLanguage);
                    $freeFields[$freeField->getId()] = $this->manageJSONFreeField($freeField, $data[$freeFieldId], $user);
                }
            }
        }

        $entity->setFreeFields($freeFields);
    }

    public static function processErrors(FreeField $freeField,
                                         array     $data,
                                         Language  $userLanguage,
                                         Language  $defaultLanguage): void {
        if($freeField->getTypage() === FreeField::TYPE_TEXT) {
            $value = trim($data[$freeField->getId()]);
            $name = $freeField->getLabelIn($userLanguage, $defaultLanguage);
            $message = "Le nombre de caractères du champ libre <strong>$name</strong> ne peut être {type} à {length}.";

            $minCharactersLength = $freeField->getMinCharactersLength() ?? null;
            if ($minCharactersLength && $value && (strlen($value) < $minCharactersLength)) {
                throw new FormException(str_replace(["{type}", "{length}"], ["inférieur", $minCharactersLength], $message));
            }

            $maxCharactersLength = $freeField->getMaxCharactersLength() ?? null;
            if ($maxCharactersLength && $value && (strlen($value) > $maxCharactersLength)) {
                throw new FormException(str_replace(["{type}", "{length}"], ["supérieur", $maxCharactersLength], $message));
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
