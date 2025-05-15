<?php

namespace App\Helper;

use App\Entity\Language;
use App\Entity\Tracking\TrackingMovement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use WiiCommon\Helper\Stream;

class QueryBuilderHelper {

    public static function count(QueryBuilder $query,
                                 string       $alias,
                                 bool         $distinct = true): int {
        $countQuery = clone $query;

        $countQuery
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy');

        if($distinct) {
            $countQuery->select("COUNT(DISTINCT $alias) AS __query_count");
        } else {
            $countQuery->select("COUNT($alias) AS __query_count");
        }

        $result = $countQuery
            ->getQuery()
            ->getSingleResult();

        return $result["__query_count"] ?? 0;
    }

    public static function countByStatusesAndTypes(EntityManagerInterface $entityManager,
                                                   string $entity,
                                                   array $types = [],
                                                   array $statuses = []): int
    {
        if (!empty($statuses) && !empty($types)) {
            $qb = $entityManager->createQueryBuilder();
            $statusProperty = property_exists($entity, 'status') ? 'status' : 'statut';

            $qb
                ->select("COUNT(entity)")
                ->from($entity, 'entity')
                ->innerJoin("entity.{$statusProperty}", 'status')
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
            return 0;
        }
    }

    public static function countByStatuses(EntityManagerInterface $entityManager,
                                           string $entity,
                                           array $statuses = [])
    {
        if (!empty($statuses)) {
            $qb = $entityManager->createQueryBuilder();
            $statusProperty = property_exists($entity, 'status') ? 'status' : 'statut';

            $qb
                ->select("COUNT(entity)")
                ->from($entity, 'entity')
                ->innerJoin("entity.{$statusProperty}", 'status')
                ->andWhere('status IN (:statuses)')
                ->setParameter('statuses', $statuses);

            return $qb
                ->getQuery()
                ->getSingleScalarResult();
        }
        else {
            return [];
        }
    }

    public static function joinTranslations(QueryBuilder $qb, Language $language, Language $defaultLanguage, array $entities, array $options = []): QueryBuilder {
        $order = $options["order"] ?? null;
        $alias = $options["alias"] ?? null;
        $forSelect = $options["forSelect"] ?? false;

        $alias = $alias ?: $qb->getRootAliases()[0];
        foreach ($entities as $entity) {
            $entityToString = ($entity === 'statut' || $entity === 'status') ? 'nom' : 'label';
            $qb
                ->leftJoin("$alias.$entity", "join_$entity")
                ->leftJoin("join_$entity.labelTranslation", "join_labelTranslation_$entity")
                ->leftJoin("join_labelTranslation_$entity.translations", "join_translation_$entity", Join::WITH, "join_translation_$entity.language = :language")
                ->leftJoin("join_labelTranslation_$entity.translations", "join_translation_default_$entity", Join::WITH, "join_translation_default_$entity.language = :default");

            if ($order) {
                $qb
                    ->orderBy("IFNULL(join_translation_$entity.translation, IFNULL(join_translation_default_$entity.translation, join_$entity.$entityToString))", $order)
                    ->addGroupBy("join_translation_$entity.translation")
                    ->addGroupBy("join_translation_default_$entity.translation")
                    ->addGroupBy("join_$entity.$entityToString");
            } elseif ($forSelect) {
                $qb->andWhere("IFNULL(join_translation_$entity.translation, IFNULL(join_translation_default_$entity.translation, join_$entity.$entityToString)) LIKE :term");
            }
        }

        return $qb
            ->setParameter("language", $language)
            ->setParameter("default", $defaultLanguage);
    }

    public static function setGroupBy(QueryBuilder $queryBuilder, array $ignored = []): QueryBuilder {
        Stream::from($queryBuilder->getDQLParts()['select'])
            ->flatMap(static fn(Select $selectPart) => [$selectPart->getParts()[0]])
            ->map(static fn(string $selectString) => trim(explode('AS', $selectString)[1]))
            ->filter(static fn(string $selectAlias) => !in_array($selectAlias, $ignored))
            ->each(static fn(string $field) => $queryBuilder->addGroupBy($field));

        return $queryBuilder;
    }

    public static function addTrackingEntities(QueryBuilder $queryBuilder, string $aliasTrackingMovement): QueryBuilder
    {
        return $queryBuilder
            ->addSelect("IF(join_helper_reception.id IS NOT NULL, :helper_reception,
                                    IF(join_helper_dispatch.id IS NOT NULL, :helper_dispatch,
                                        IF(join_helper_arrival.id IS NOT NULL, :helper_arrival,
                                            IF(join_helper_transfer_order.id IS NOT NULL, :helper_transferOrder,
                                                IF(join_helper_delivery_request.id IS NOT NULL, :helper_deliveryRequest,
                                                    IF(join_helper_shipping_request.id IS NOT NULL, :helper_shippingRequest,
                                                        IF(join_helper_delivery_order.id IS NOT NULL, :helper_deliveryOrder,
                                                            IF(join_helper_preparation.id IS NOT NULL, :helper_preparation, NULL)
                                                        )
                                                    )
                                                )
                                            )
                                        )
                                    )
                                ) AS entity")
            ->addSelect("COALESCE(
                                    join_helper_reception.id,
                                    join_helper_dispatch.id,
                                    join_helper_arrival.id,
                                    join_helper_transfer_order.id,
                                    join_helper_delivery_request.id,
                                    join_helper_shipping_request.id,
                                    join_helper_delivery_order.id,
                                    join_helper_preparation.id
                                ) AS entityId")
            ->addSelect("COALESCE(
                                    join_helper_reception.number,
                                    join_helper_dispatch.number,
                                    join_helper_arrival.numeroArrivage,
                                    join_helper_transfer_order.number,
                                    join_helper_delivery_request.numero,
                                    join_helper_shipping_request.number,
                                    join_helper_delivery_order.numero,
                                    join_helper_preparation.numero
                                ) AS entityNumber")
            ->leftJoin("$aliasTrackingMovement.pack","join_helper_pack")
            ->leftJoin("join_helper_pack.arrivage","join_helper_arrival")
            ->leftJoin("$aliasTrackingMovement.reception","join_helper_reception")
            ->leftJoin("$aliasTrackingMovement.preparation","join_helper_preparation")
            ->leftJoin("$aliasTrackingMovement.delivery","join_helper_delivery_order")
            ->leftJoin("$aliasTrackingMovement.dispatch","join_helper_dispatch")
            ->leftJoin("$aliasTrackingMovement.mouvementStock", "join_helper_stock_movement")
            ->leftJoin("join_helper_stock_movement.transferOrder", "join_helper_transfer_order")
            ->leftJoin("$aliasTrackingMovement.deliveryRequest", "join_helper_delivery_request")
            ->leftJoin("$aliasTrackingMovement.shippingRequest", "join_helper_shipping_request")
            ->setParameter("helper_" . TrackingMovement::RECEPTION_ENTITY, TrackingMovement::RECEPTION_ENTITY)
            ->setParameter("helper_" . TrackingMovement::DISPATCH_ENTITY, TrackingMovement::DISPATCH_ENTITY)
            ->setParameter("helper_" . TrackingMovement::ARRIVAL_ENTITY, TrackingMovement::ARRIVAL_ENTITY)
            ->setParameter("helper_" . TrackingMovement::TRANSFER_ORDER_ENTITY, TrackingMovement::TRANSFER_ORDER_ENTITY)
            ->setParameter("helper_" . TrackingMovement::DELIVERY_REQUEST_ENTITY, TrackingMovement::DELIVERY_REQUEST_ENTITY)
            ->setParameter("helper_" . TrackingMovement::SHIPPING_REQUEST_ENTITY, TrackingMovement::SHIPPING_REQUEST_ENTITY)
            ->setParameter("helper_" . TrackingMovement::DELIVERY_ORDER_ENTITY, TrackingMovement::DELIVERY_ORDER_ENTITY)
            ->setParameter("helper_" . TrackingMovement::PREPARATION_ENTITY, TrackingMovement::PREPARATION_ENTITY);
    }
}
