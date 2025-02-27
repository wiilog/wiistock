<?php

namespace App\Repository\RequestTemplate;

use App\Entity\RequestTemplate\CollectRequestTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateTriggerAction;
use App\Entity\RequestTemplate\DeliveryRequestTemplateUsageEnum;
use App\Entity\RequestTemplate\HandlingRequestTemplate;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method RequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequestTemplate[]    findAll()
 * @method RequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestTemplateRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params): array {
        $queryBuilder = $this->createQueryBuilder("request_template");

        $countTotal = QueryBuilderHelper::count($queryBuilder, "request_template");

        //Filter search
        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
            if (!empty($search)) {
                $queryBuilder
                    ->join("request_template.type", "search_type")
                    ->andWhere($queryBuilder->expr()->orX(
                        "request_template.name LIKE :value",
                        "search_type.label LIKE :value",
                    ))
                    ->setParameter('value', '%' . $search . '%');
            }
        }

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                $queryBuilder->orderBy("request_template.$column", $order);
            }
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, "request_template");

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query?->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getTemplateForSelect(EntityManagerInterface $entityManager) {
        $qb = $this->createQueryBuilder("request_template");

        $exprBuilder = $qb->expr();
        $qb->select("request_template.id AS id")
            ->addSelect("request_template.name AS text")
            ->leftJoin(DeliveryRequestTemplateTriggerAction::class, 'deliveryRequestTemplate', Join::WITH, 'request_template.id = deliveryRequestTemplate.id')
            ->leftJoin(CollectRequestTemplate::class, 'collectRequestTemplate', Join::WITH, 'request_template.id = collectRequestTemplate.id')
            ->leftJoin(HandlingRequestTemplate::class, 'handlingRequestTemplate', Join::WITH, 'request_template.id = handlingRequestTemplate.id')
            ->andWhere($exprBuilder->orX(
                "request_template INSTANCE OF :deliveryRequestTemplateClass",
                "request_template INSTANCE OF :collectRequestTemplateClass",
                "request_template INSTANCE OF :handlingRequestTemplateClass",
            ))
            ->setParameters([
                'deliveryRequestTemplateClass' => $entityManager->getClassMetadata(DeliveryRequestTemplateTriggerAction::class),
                'collectRequestTemplateClass' => $entityManager->getClassMetadata(CollectRequestTemplate::class),
                'handlingRequestTemplateClass' => $entityManager->getClassMetadata(HandlingRequestTemplate::class),
            ]);

        return $qb->getQuery()->getResult();
    }

}
