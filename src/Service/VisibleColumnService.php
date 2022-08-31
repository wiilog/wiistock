<?php


namespace App\Service;


use App\Entity\FreeField;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use App\Service\TranslationService;

class VisibleColumnService {
    public const FREE_FIELD_NAME_PREFIX = 'free_field';

    /** @Required */
    public TranslationService $translation;

    /** @Required  */
    public EntityManagerInterface $entityManager;

    public function getArrayConfig(array $fields,
                                   array $freeFields = [],
                                   array $columnsVisible = []): array
    {
        return array_merge(
            array_map(
                function (array $column) use ($columnsVisible) {
                    $alwaysVisible = $column['alwaysVisible'] ?? false;
                    $visible = $column['visible'] ?? ($alwaysVisible || in_array($column['name'], $columnsVisible));
                    $translated = $column['translated'] ?? false;
                    $title = $column['title'] ?? '';
                    return [
                        'title' => $title,
                        'hiddenTitle' => $column['hiddenTitle'] ?? '',
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

    public function setVisibleColumns(string $entity, array $fields, Utilisateur $user): void {
        $visibleColumns = $user->getVisibleColumns();
        $visibleColumns[$entity] = $fields;

        $user->setVisibleColumns($visibleColumns);
    }

    public function getSearchableColumns(array $conditions, string $entity, QueryBuilder $qb, Utilisateur $user, ?string $search): Orx
    {
        $condition = $qb->expr()->orX();
        $queryBuilderAlias = $qb->getRootAliases()[0];
        $freeFieldRepository = $this->entityManager->getRepository(FreeField::class);

        foreach($user->getVisibleColumns()[$entity] as $column) {
            if(str_starts_with($column, "free_field_")) {
                $id = str_replace("free_field_", "", $column);
                $freeField = $freeFieldRepository->find($id);
                if ($freeField?->getTypage() === FreeField::TYPE_BOOL) {
                    $lowerSearchValue = strtolower($search);
                    if (($lowerSearchValue === "oui") || ($lowerSearchValue === "non")) {
                        $booleanValue = $lowerSearchValue === "oui" ? '1' : '0';
                        $condition->add("JSON_SEARCH(${queryBuilderAlias}.freeFields, 'one', :boolean_value, NULL, '$.\"${id}\"') IS NOT NULL");
                        $qb->setParameter("boolean_value", $booleanValue);
                    }
                }
                $condition->add("JSON_EXTRACT(${queryBuilderAlias}.freeFields, '$.\"$id\"') LIKE :search_value");
                $condition->add("DATE_FORMAT(STR_TO_DATE(TRIM('\"' FROM JSON_EXTRACT(${queryBuilderAlias}.freeFields, '$.\"$id\"')), '%Y-%m-%dT%H:%i'), '%d/%m/%Y %H:%i') LIKE :search_value");
            } else if(isset($conditions[$column])) {
                $condition->add($conditions[$column]);
            }
        }

        return $condition;
    }
}
