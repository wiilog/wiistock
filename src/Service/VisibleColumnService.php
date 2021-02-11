<?php


namespace App\Service;


use Symfony\Contracts\Translation\TranslatorInterface;

class VisibleColumnService {
    public const FREE_FIELD_NAME_PREFIX = 'free_field';

    /** @Required */
    public TranslatorInterface $translator;

    public function getArrayConfig(array $fields,
                                   array $freeFields = [],
                                   array $columnsVisible = []): array
    {
        return array_merge(
            array_map(
                function (array $column) use ($columnsVisible) {
                    $alwaysVisible = $column['alwaysVisible'] ?? false;
                    $visible = $alwaysVisible || in_array($column['name'], $columnsVisible);
                    $translated = $column['translated'] ?? false;
                    $title = $column['title'] ?? '';
                    return [
                        'title' => $title,
                        'hiddenTitle' => $column['hiddenTitle'] ?? '',
                        'displayedTitle' => $translated ? $this->translator->trans($title) : $title,
                        'alwaysVisible' => $column['alwaysVisible'] ?? null,
                        'hiddenColumn' => $column['hiddenColumn'] ?? false,
                        'orderable' => $column['orderable'] ?? true,
                        'data' => $column['name'],
                        'name' => $column['name'],
                        'translated' => $translated,
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
                    $title = ucfirst(mb_strtolower($freeField['label']));
                    return [
                        "title" => $title,
                        'displayedTitle' => $title,
                        "data" => $freeFieldName,
                        "name" => $freeFieldName,
                        "isColumnVisible" => $visible,
                        "searchable" => true,
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
