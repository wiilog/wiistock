<?php


namespace App\Service;


use App\Entity\FieldsParam;
use Doctrine\ORM\EntityManagerInterface;

class FieldsParamService
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function isFieldRequired(array $config, string $fieldName, string $action): bool {
        return isset($config[$fieldName]) && isset($config[$fieldName][$action]) && $config[$fieldName][$action];
    }

    public function filterHeaderConfig(array $config, string $entityCode) {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);

        $fieldsParam = $fieldsParamRepository->getByEntity($entityCode);

        return array_filter($config, function ($fieldConfig) use ($fieldsParam) {
            return (
                !isset($fieldConfig['show'])
                || $this->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], 'displayedFormsCreate')
                || $this->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], 'displayedFormsEdit')
            );
        });
    }
}
