<?php

namespace App\Repository\Emergency;


use App\Entity\Arrivage;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Reception;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @extends EntityRepository<EmergencyRepository>
 */
class EmergencyRepository extends EntityRepository {
    public function findByParamsAndFilters(ParameterBag $params, array $filters): array {
        $entityManager = $this->getEntityManager();
        $lastArrivalNumberSubquery = $entityManager->createQueryBuilder()
            ->select('arrival.numeroArrivage')
            ->from(Arrivage::class, 'arrival')
            ->where('emergency_arrival = emergency.id')
            ->innerJoin(TrackingEmergency::class, 'emergency_arrival')
            ->orderBy('arrival.date', 'DESC')
            ->getDQL();

        $lastReceptionNumberSubquery = $entityManager->createQueryBuilder()
            ->select('reception.number')
            ->from(Reception::class, 'reception')
            ->where('emergency_reception = emergency.id')
            ->innerJoin("reception.lines", "reception_line")
            ->innerJoin("reception_line.receptionReferenceArticles", "reception_reference_article")
            ->innerJoin("reception_reference_article.stockEmergencies", "emergency_reception")
            ->orderBy('reception.date', 'DESC')
            ->getDQL();

        $columns = [
            FixedFieldEnum::dateStart->name => "emergency.dateStart",
            FixedFieldEnum::dateEnd->name => "emergency.dateEnd",
            "lastEntityNumber"=> "GREATEST(FIRST($lastArrivalNumberSubquery), FIRST($lastReceptionNumberSubquery))",
            FixedFieldEnum::createdAt->name => "emergency.createdAt",
            "lastTriggeredAt" => "emergency.createdAt",
            "closedAt"=> "emergency.closedAt",
            FixedFieldEnum::orderNumber->name => "emergency.orderNumber",
            FixedFieldEnum::postNumber->name => "tracking_emergency.postNumber",
            FixedFieldEnum::buyer->name => "emergency_buyer.username",
            FixedFieldEnum::supplier->name => "emergency_supplier.nom",
            FixedFieldEnum::carrier->name => "emergency_carrier.label",
            FixedFieldEnum::carrierTrackingNumber->name => "emergency.carrierTrackingNumber",
            FixedFieldEnum::type->name => "emergency_type.label",
            FixedFieldEnum::internalArticleCode->name=> "tracking_emergency.internalArticleCode",
            FixedFieldEnum::supplierArticleCode->name => "tracking_emergency.supplierArticleCode",
        ];

        $queryBuilder = $this->createQueryBuilder("emergency")
            ->select("emergency.id AS id")
            ->distinct();

        foreach ($columns as $field => $column) {
            $queryBuilder->addSelect("$column AS $field");
        }

        $queryBuilder->leftJoin(TrackingEmergency::class, "tracking_emergency", "WITH", "tracking_emergency.id = emergency.id")
            ->leftJoin("emergency.buyer", "emergency_buyer")
            ->leftJoin("emergency.supplier", "emergency_supplier")
            ->leftJoin("emergency.carrier", "emergency_carrier")
            ->leftJoin("emergency.type", "emergency_type");

        $total = QueryBuilderHelper::count($queryBuilder, 'emergency');

        $searchParams = $params->all('search');
        if (!empty($searchParams)) {
            $search = $searchParams['value'];
            if (!empty($search)) {
                $exprBuilder = $queryBuilder->expr();
                $queryBuilder
                    ->andWhere($exprBuilder->orX(
                        "emergency.command LIKE :value",
                        "tracking_emergency.postNumber LIKE :value",
                        "emergency_buyer.username LIKE :value",
                        "emergency_supplier.nom LIKE :value",
                        "emergency_carrier.label LIKE :value",
                        "emergency.carrierTrackingNumber LIKE :value",
                        "emergency_type.label LIKE :value",
                        "tracking_emergency.internalArticleCode LIKE :value",
                        "tracking_emergency.supplierArticleCode LIKE :value"
                    ))
                    ->setParameter('value', '%' . $search . '%');
            }
        }

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];
            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                $columnToOrder = $columns[$column] ?? null;

                if ($columnToOrder) {
                    $queryBuilder->orderBy($columnToOrder, $order);
                }
            }
        }

        $filtered = QueryBuilderHelper::count($queryBuilder, 'emergency');

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
        if ($pageLength) {
            $queryBuilder->setMaxResults($pageLength);
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }
}
