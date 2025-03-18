<?php

namespace App\Repository\RequestTemplate;

use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;

/**
 * @method DeliveryRequestTemplateSleepingStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryRequestTemplateSleepingStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method array<int, DeliveryRequestTemplateSleepingStock> findAll()
 * @method array<int, DeliveryRequestTemplateSleepingStock> findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRequestTemplateSleepingStockRepository extends RequestTemplateRepository {
    public function getForSelect(?string $term = null): array {
        $queryBuilder = $this->createQueryBuilder('delivery_request_template_repository_trigger_action')
            ->select("delivery_request_template_repository_trigger_action.id AS id")
            ->addSelect("delivery_request_template_repository_trigger_action.name AS text");

        if ($term) {
            $queryBuilder->andWhere('role.label LIKE :term')
                ->setParameter("term", "%$term%");
        }

        return $queryBuilder->getQuery()
            ->getResult();
    }
}
