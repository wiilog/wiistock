<?php

namespace App\Helper;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

class QueryBuilderHelper
{

    public static function count(QueryBuilder $query, string $alias): int
    {
        $countQuery = clone $query;

        $result = $countQuery
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy')
            ->select("COUNT(DISTINCT $alias) AS __query_count")
            ->getQuery()
            ->getSingleResult();

        return $result["__query_count"] ?? 0;
    }

    public static function countByStatusesAndTypes(EntityManagerInterface $entityManager,
                                                   string $entity,
                                                   array $types = [],
                                                   array $statuses = [])
    {
        if (!empty($statuses) && !empty($types)) {
            $qb = $entityManager->createQueryBuilder();
            $statusProperty = property_exists($entity, 'status') ? 'status' : 'statut';

            $qb
                ->select("COUNT(entity)")
                ->from($entity, 'entity')
                ->innerJoin("entity.${statusProperty}", 'status')
                ->innerJoin("entity.type", 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->setParameter('types', $types)
                ->setParameter('statuses', $statuses);

            return $qb
                ->getQuery()
                ->getSingleScalarResult();
        }
        else {
            return [];
        }
    }

    public static function joinTranslations(QueryBuilder $qb, Language $language, Language $defaultLanguage, string $entity, string $order = null): QueryBuilder {
        $alias = $qb->getRootAliases()[0];
        $entityToString = $entity === 'statut' || $entity === 'status' ? 'nom' : 'label';
        $qb
            ->leftJoin("$alias.$entity", "join_$entity")
            ->leftJoin("join_$entity.labelTranslation", "join_labelTranslation")
            ->leftJoin("join_labelTranslation.translations", "join_translation", Join::WITH, "join_translation.language = :language")
            ->leftJoin("join_labelTranslation.translations", "join_translation_default", Join::WITH, "join_translation_default.language = :default");

        if($order){
            $qb
                ->orderBy("IFNULL(join_translation.translation, IFNULL(join_translation_default.translation, join_$entity.$entityToString))", $order)
                ->addGroupBy("join_translation.translation")
                ->addGroupBy("join_translation_default.translation")
                ->addGroupBy("join_$entity.$entityToString");
        } else {
            $qb->andWhere("IFNULL(join_translation.translation, IFNULL(join_translation_default.translation, join_$entity.$entityToString)) LIKE :term");
        }

        return $qb
            ->setParameter("language", $language)
            ->setParameter("default", $defaultLanguage);
    }
}
