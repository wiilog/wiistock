<?php


namespace App\Service;


class FieldsParamService
{
    public function isFieldRequired(array $config, string $fieldName, string $action): bool {
        return isset($config[$fieldName]) && isset($config[$fieldName][$action]) && $config[$fieldName][$action];
    }
}
