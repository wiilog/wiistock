<?php


namespace App\Service;


use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Type\Type;
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use WiiCommon\Helper\Stream;

class FixedFieldService {

    public function isFieldRequired(array $config, string $fieldName, string $action): bool {
        return isset($config[$fieldName]) && isset($config[$fieldName][$action]) && $config[$fieldName][$action];
    }

    public function filterHeaderConfig(EntityManagerInterface $entityManager, array $config, string $entityCode, ?Type $type = null): array {
        if ($type) {
            $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
            $fieldsParam = Stream::from($fixedFieldByTypeRepository->findBy(["entityCode" => FixedFieldStandard::ENTITY_CODE_DISPATCH]))
                ->keymap(static fn(FixedFieldByType $field) => [$field->getFieldCode(), [
                    FixedFieldByType::ATTRIBUTE_DISPLAYED_EDIT => $field->isDisplayedEdit($type),
                    FixedFieldByType::ATTRIBUTE_DISPLAYED_CREATE => $field->isDisplayedCreate($type),
                ]])
                ->toArray();
        } else {
            $fixedFieldStandardRepository = $entityManager->getRepository(FixedFieldStandard::class);
            $fieldsParam = $fixedFieldStandardRepository->getByEntity($entityCode);
        }

        return array_filter($config, function ($fieldConfig) use ($fieldsParam) {
            return (
                !isset($fieldConfig['show'])
                || $this->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], 'displayedCreate')
                || $this->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], 'displayedEdit')
            );
        });
    }

    public function checkForErrors(EntityManagerInterface $entityManager,
                                   InputBag               $inputBag,
                                   string                 $entityCode,
                                   bool                   $isCreation,
                                   ?ParameterBag          $ignoredFields = null,
                                   ?Type                  $type = null): InputBag {
        $displayAction = $isCreation ? 'displayedCreate' : 'displayedEdit';
        $requiredAction = $isCreation ? 'requiredCreate' : 'requiredEdit';
        $actions = [$displayAction, $requiredAction];

        $ignoredFields = $ignoredFields ?? new ParameterBag();

        $fixedFieldRepository = $entityManager->getRepository($type ? FixedFieldByType::class : FixedFieldStandard::class);
        $accessorParams = $type ? [$type] : [];
        $fieldsParam = Stream::from($fixedFieldRepository->findBy(["entityCode" => $entityCode]))
            ->keymap(static fn(FixedField $field) => [
                $field->getFieldCode(),
                Stream::from($actions)
                    ->keymap(static function (string $action) use ($field, $accessorParams): array {
                        $method = "is" . ucfirst($action);
                        return[
                            $action,
                            $field->$method(...$accessorParams),
                        ];
                    })
                    ->toArray()
            ])
            ->toArray();

        Stream::from($fieldsParam)
            ->each(function ($params, $fieldName) use ($ignoredFields, $inputBag, $displayAction, $requiredAction) {
                if (!$params[$displayAction]) {
                    $inputBag->remove($fieldName);
                } else {
                    if ($params[$requiredAction] && ($inputBag->get($fieldName) === '' || $inputBag->get($fieldName) === null)) {
                        if (!$ignoredFields->getBoolean($fieldName)) {
                            throw new FormException("Une erreur est presente dans le formulaire ");
                        }
                    } elseif (!$inputBag->has($fieldName)) {
                        $inputBag->set($fieldName, null);
                    }
                }
            });

        return $inputBag;
    }
}
