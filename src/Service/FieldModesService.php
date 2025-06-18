<?php


namespace App\Service;


use App\Entity\FreeField\FreeField;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use WiiCommon\Helper\Stream;

class FieldModesService {

    public const FIELD_MODE_VISIBLE = 'fieldVisible';
    public const FIELD_MODE_VISIBLE_IN_DROPDOWN = 'fieldVisibleInDropdown';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserService            $userService,
        private LanguageService        $languageService,
        private FreeFieldService       $freeFieldService,
    ) {
    }

    public function getArrayConfig(array $fields,
                                   array $freeFields = [],
                                   array $fieldsModes = [],
                                   bool  $forExport = false): array {
        $user = $this->userService->getUser();
        $defaultLanguage = $this->languageService->getDefaultLanguage();
        $userLanguage = $user?->getLanguage() ?: $defaultLanguage;
        return Stream::from(
            Stream::from($fields)
                ->map(static function(array $column) use ($fieldsModes, $forExport) {
                    $alwaysVisible = $column['alwaysVisible'] ?? false;
                    $visible = isset($column['forceHidden'])
                        ? false
                        : ($column['visible'] ?? ($forExport || ($alwaysVisible || in_array(self::FIELD_MODE_VISIBLE, $fieldsModes[$column['name']] ?? []))));
                    $translated = $column['translated'] ?? false;
                    $title = $column['title'] ?? '';
                    return [
                        'title' => ucfirst($title),
                        'hiddenTitle' => $column['hiddenTitle'] ?? '',
                        'alwaysVisible' => $alwaysVisible,
                        'hiddenColumn' => $column['hiddenColumn'] ?? false,
                        'orderable' => $column['orderable'] ?? true,
                        'data' => $column['name'],
                        'name' => $column['name'],
                        'translated' => $translated,
                        'class' => $column['class'] ?? null,
                        self::FIELD_MODE_VISIBLE => $visible,
                        "type" => $column['type'] ?? null,
                        "searchable" => $column['searchable'] ?? null,
                        "required" => $column['required'] ?? false,
                        'info' => $column['info'] ?? null,
                        ...in_array(self::FIELD_MODE_VISIBLE_IN_DROPDOWN, $fieldsModes[$column['name']] ?? []) ? [self::FIELD_MODE_VISIBLE_IN_DROPDOWN => true] : [],
                    ];
                }),
            Stream::from($freeFields)
                ->map(function (FreeField $freeField) use ($fieldsModes, $userLanguage, $defaultLanguage, $forExport) {
                    $freeFieldName = $this->freeFieldService->getFreeFieldName($freeField->getId());
                    $alwaysVisible = $column['alwaysVisible'] ?? null;
                    $visible = $forExport || ($alwaysVisible || in_array(self::FIELD_MODE_VISIBLE, $fieldsModes[$freeFieldName] ?? []));
                    $dirtyLabel = $freeField->getLabelIn($userLanguage, $defaultLanguage) ?: $freeField->getLabel();
                    $title = ucfirst(mb_strtolower($dirtyLabel));
                    return [
                        "title" => $title,
                        'displayedTitle' => $title,
                        "data" => $freeFieldName,
                        "name" => $freeFieldName,
                        self::FIELD_MODE_VISIBLE => $visible,
                        "searchable" => true,
                        "type" => $freeField->getTypage(),
                        ...in_array(self::FIELD_MODE_VISIBLE_IN_DROPDOWN, $fieldsModes[$freeFieldName] ?? []) ? [self::FIELD_MODE_VISIBLE_IN_DROPDOWN => true] : [],
                    ];
                })
        )->toArray();
    }

    public static function extractFreeFieldId(?string $freeFieldName): ?int {
        if($freeFieldName === null) {
            return null;
        }

        preg_match(FreeFieldService::FREE_FIELD_NAME_REGEX, $freeFieldName, $matches);
        $freeFieldIdStr = $matches[1] ?? null;
        return is_numeric($freeFieldIdStr) ? intval($freeFieldIdStr) : null;
    }

    public function setFieldModesByPage(string $entity, array $fields, Utilisateur $user): void {
        $visibleColumns = $user->getFieldModesByPage();
        $visibleColumns[$entity] = $fields;

        $user->setFieldModesByPage($visibleColumns);
    }

    public function bindSearchableColumns(array $conditions, string $entity, QueryBuilder $qb, Utilisateur $user, ?string $search): Orx {
        $condition = $qb->expr()->orX();
        $queryBuilderAlias = $qb->getRootAliases()[0];
        $freeFieldRepository = $this->entityManager->getRepository(FreeField::class);

        foreach($user->getFieldModes($entity) as $column => $modes) {
            if(str_starts_with($column, FreeFieldService::FREE_FIELD_NAME_PREFIX) && in_array(self::FIELD_MODE_VISIBLE, $modes)) {
                $id = str_replace(FreeFieldService::FREE_FIELD_NAME_PREFIX, "", $column);
                $freeField = $freeFieldRepository->find($id);
                if ($freeField?->getTypage() === FreeField::TYPE_BOOL) {
                    $lowerSearchValue = strtolower($search);
                    if (($lowerSearchValue === "oui") || ($lowerSearchValue === "non")) {
                        $booleanValue = $lowerSearchValue === "oui" ? '1' : '0';
                        $condition->add("JSON_SEARCH({$queryBuilderAlias}.freeFields, 'one', :boolean_value, NULL, '$.\"{$id}\"') IS NOT NULL");
                        $qb->setParameter("boolean_value", $booleanValue);
                    }
                }
                $condition->add("JSON_EXTRACT({$queryBuilderAlias}.freeFields, '$.\"$id\"') LIKE :search_value");
                $condition->add("DATE_FORMAT(STR_TO_DATE(TRIM('\"' FROM JSON_EXTRACT({$queryBuilderAlias}.freeFields, '$.\"$id\"')), '%Y-%m-%dT%H:%i'), '%d/%m/%Y %H:%i') LIKE :search_value");
            } else if(isset($conditions[$column])) {
                $condition->add($conditions[$column]);
            }
        }

        if(empty($condition->getParts())) {
            $condition->add("0 != 0");
        } else {
            $qb->setParameter('search_value', "%$search%");
        }

        $qb
            ->andWhere($condition);
        return $condition;
    }
}
