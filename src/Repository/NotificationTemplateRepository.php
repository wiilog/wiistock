<?php

namespace App\Repository;

use App\Entity\NotificationTemplate;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

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

    public function findByParams(InputBag $params) {
        $qb = $this->createQueryBuilder("notification_template");
        $total = QueryBuilderHelper::count($qb, "notification_template");

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb->andWhere($exprBuilder->orX(
                        'notification_template.type LIKE :value',
                    ))->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, "notification_template");

        $qb->setFirstResult(0);
        $qb->setMaxResults(10);

        return [
            "data" => $qb->getQuery()->getResult(),
            "count" => $countFiltered,
            "total" => $total
        ];
    }

}
