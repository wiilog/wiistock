<?php


namespace App\Service;


use App\Entity\FieldsParam;
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

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
                || $this->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], 'displayedCreate')
                || $this->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], 'displayedEdit')
            );
        });
    }

    public function checkForErrors(EntityManagerInterface $entityManager,
                                   InputBag               $inputBag,
                                   string                 $entityCode,
                                   bool                   $isCreation): InputBag {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity($entityCode);

        $displayAction = $isCreation ? 'displayedCreate' : 'displayedEdit';
        $requiredAction = $isCreation ? 'requiredCreate' : 'requiredEdit';

        Stream::from($fieldsParam)
            ->each(function ($params, $fieldName) use ($inputBag, $displayAction, $requiredAction) {
                if (!$params[$displayAction]) {
                    $inputBag->remove($fieldName);
                } else {
                    if ($params[$requiredAction] && !$inputBag->has($fieldName)) {
                        throw new FormException("Une erreur est presente dans le formulaire");
                    } elseif (!$inputBag->has($fieldName)) {
                        $inputBag->set($fieldName, null);
                    }
                }
            });

        return $inputBag;
    }
}
