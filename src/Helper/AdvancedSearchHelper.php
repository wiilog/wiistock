<?php

namespace App\Helper;

use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use WiiCommon\Helper\Stream;

class AdvancedSearchHelper {

    public const ORDER_ACTION = "order";
    public const SEARCH_ACTION = "search";

    public const MIN_SEARCH_PART_LENGTH = 2;


    private const HIGHLIGHT_COLOR = "#FFFF00";
    private const SPAN_ELEMENT_REGEX = "/<span[^>]*>.*?<\/span>/";

    private const EXCLUDED_FIELDS = [
        "Actions",
        "actions",
        "image",
        "colorClass",
    ];

    public static function bindSearch(array $conditions, int $index, $searchPartsLength, bool $forSelect = false): Stream {
        return Stream::from($conditions)
            ->map(static function (string $condition) use ($forSelect, $index, $searchPartsLength): string {
                // add current index to :search_value parameter
                $boundCondition = match (true) {
                    str_contains($condition, ":search_value") => str_replace(":search_value", ":search_value_$index", $condition),
                    default => $condition,
                };

                $relevanceScore = $searchPartsLength - $index;
                if ($forSelect) {
                    return "IF($boundCondition, $relevanceScore, 0)";
                } else {
                    return $boundCondition;
                }
            });
    }

    public static function getRelevances(QueryBuilder $queryBuilder): Stream {
        /* Retourne tous les alias de select "search_relevance_***" de la requête pour procéder au orderBy et groupBy */
        return Stream::from($queryBuilder->getDQLParts()["select"])
            ->flatMap(static fn(Select $selectPart) => [$selectPart->getParts()[0]])
            ->map(static fn(string $selectString) => trim(explode("AS HIDDEN", $selectString)[1] ?? null))
            ->filter(static fn(?string $selectAlias) => $selectAlias && str_contains($selectAlias, "search_relevance"));
    }

    public static function highlight(array $row, array $searchParts, array $searchableFields = []): array {
        return Stream::from($row)
            ->keymap(static function (?string $value, string $field) use ($searchParts, $searchableFields): array {
                if (!in_array($field, self::EXCLUDED_FIELDS)
                    && (empty($searchableFields) || in_array($field, $searchableFields))) {
                    foreach ($searchParts as $part) {
                        $startPosition = strpos(strtolower($value), strtolower($part));
                        if ($startPosition !== false) {
                            preg_match_all(self::SPAN_ELEMENT_REGEX, $value, $matchedTags, PREG_OFFSET_CAPTURE);

                            $excludedParts = Stream::from($matchedTags[0])
                                ->map(static function($matchedTag) {
                                    $length = strlen($matchedTag[0]);
                                    $start = $matchedTag[1];
                                    $end = $start + $length;

                                    return [
                                        $start,
                                        $end,
                                    ];
                                });

                            $exclude = !!$excludedParts->find(static fn(array $positions) => $startPosition > $positions[0] && $startPosition < $positions[1]);
                            if ($exclude) {
                                continue;
                            }

                            $endPosition = $startPosition + strlen($part);
                            $highlightedPart = substr($value, $startPosition, strlen($part));

                            $value = substr_replace(
                                $value,
                                sprintf("<span style='background-color: %s'>$highlightedPart</span>", self::HIGHLIGHT_COLOR),
                                $startPosition,
                                $endPosition - $startPosition
                            );
                        }
                    }
                }

                return [
                    $field,
                    $value
                ];
            })
            ->toArray();
    }
}
