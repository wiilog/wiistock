<?php


namespace App\Service;


class VisibleColumnService {
    public const FREE_FIELD_NAME_PREFIX = 'free_field';

    public function getArrayConfig(array $fields,
                                   array $freeFields = [],
                                   array $columnsVisible = []) {
        return array_merge(
            array_map(
                function (array $column) use ($columnsVisible) {
                    $alwaysVisible = $column['alwaysVisible'] ?? false;
                    $visible = $alwaysVisible || in_array($column['name'], $columnsVisible);
                    return [
                        'title' => $column['title'] ?? '',
                        'alwaysVisible' => $column['alwaysVisible'] ?? null,
                        'orderable' => $column['orderable'] ?? true,
                        'data' => $column['name'],
                        'name' => $column['name'],
                        'translated' => $column['translated'] ?? false,
                        'class' => $column['class'] ?? null,
                        'isColumnVisible' => $visible,
                        "type" => $column['type'] ?? null,
                        "searchable" => $column['searchable'] ?? null,
                    ];
                },
                $fields
            ),
            array_map(
                function (array $freeField) use ($columnsVisible) {
                    $freeFieldName = $this->getFreeFieldName($freeField['id']);
                    $alwaysVisible = $column['alwaysVisible'] ?? null;
                    $visible = $alwaysVisible || in_array($freeFieldName, $columnsVisible);
                    return [
                        "title" => ucfirst(mb_strtolower($freeField['label'])),
                        "data" => $freeFieldName,
                        "name" => $freeFieldName,
                        "isColumnVisible" => $visible,
                        "type" => $freeField['typage'],
                    ];
                },
                $freeFields
            )
        );
    }

    public function getFreeFieldName($id): string {
        return self::FREE_FIELD_NAME_PREFIX . '_' . $id;
    }

    public static function extractFreeFieldId(?string $freeFieldName): ?int {
        if($freeFieldName === null) {
            return null;
        }

        preg_match("/" . VisibleColumnService::FREE_FIELD_NAME_PREFIX . "_(\d+)/", $freeFieldName, $matches);
        $freeFieldIdStr = $matches[1] ?? null;
        return is_numeric($freeFieldIdStr) ? intval($freeFieldIdStr) : null;
    }
}
