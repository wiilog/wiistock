<?php

namespace App\Repository;

use App\Entity\NotificationTemplate;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method NotificationTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationTemplate[]    findAll()
 * @method NotificationTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationTemplateRepository extends EntityRepository
{

    public function findByType(string $type): NotificationTemplate {
        return $this->createQueryBuilder("notification_template")
            ->where("notification_template.type = :type")
            ->setParameter("type", $type)
            ->getQuery()
            ->getSingleResult();
    }

    public function findByParams($params) {
        $qb = $this->createQueryBuilder("notification_template");
        $total = QueryCounter::count($qb, "notification_template");

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb->andWhere($exprBuilder->orX(
                        'notification_template.name LIKE :value',
                        'notification_template.type LIKE :value',
                    ))->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, "notification_template");

        if ($params) {
            if (!empty($params->get("start"))) $qb->setFirstResult($params->get("start"));
            if (!empty($params->get("length"))) $qb->setMaxResults($params->get("length"));
        }

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countFiltered,
            "total" => $total
        ];
    }

}
