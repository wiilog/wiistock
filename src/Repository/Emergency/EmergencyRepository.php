<?php

namespace App\Repository\Emergency;


use App\Entity\Arrivage;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Reception;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use phpDocumentor\Reflection\Types\Static_;
use Symfony\Component\HttpFoundation\ParameterBag;
use WiiCommon\Helper\Stream;

/**
 * @extends EntityRepository<EmergencyRepository>
 */
class EmergencyRepository extends EntityRepository {
    public function findByParamsAndFilters(ParameterBag $params, array $filters, array $visibleColumnsConfig): array {
        $entityManager = $this->getEntityManager();
        $lastArrivalNumberSubquery = $entityManager->createQueryBuilder()
            ->select('arrival.numeroArrivage')
            ->from(Arrivage::class, 'arrival')
            ->where('emergency_arrival = emergency.id')
            ->innerJoin(TrackingEmergency::class, 'emergency_arrival')
            ->orderBy('arrival.date', 'DESC')
            ->getDQL();

        // TODO WIIS-12641 à voir si faut refaire avec changement d'entité ?
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

        $order = $params->all('order')[0]['dir'] ?? null;
        $columnToOrder = $params->all('columns')[$params->all('order')[0]['column']]['data'] ?? null;
        $searchParams = $params->all('search');
        $search = $searchParams['value'] ?? null;
        $presentSearchableColumns = Stream::from($visibleColumnsConfig)
            ->filter(static fn(array $config): bool => ($config['searchable'] ?? false) && ($config['fieldVisible'] ?? false))
            ->map(static fn(array $config): string => $config['data'])
            ->toArray();
        $searches = [];

        $queryBuilder = $this->createQueryBuilder("emergency")
            ->select("emergency.id AS id")
            ->distinct();
        $exprBuilder = $queryBuilder->expr();

        foreach ($visibleColumnsConfig as $config) {
            $field = $config['data'] ?? null;
            $column = $columns[$field] ?? null;

            if ($order && $columnToOrder === $field) {
                $queryBuilder
                    ->orderBy($column, $order)
                    ->addSelect("$column AS order_$field");
            }

            if (!$field || !$column || !($config['fieldVisible'] ?? false)) {
                $queryBuilder->addSelect("'' AS $field");
            } else {
                $queryBuilder->addSelect("$column AS $field");

                if (!empty($search) && in_array($field, $presentSearchableColumns)) {
                    $searches[] = $exprBuilder->like("$column", ":value");
                }
            }
        }

        if (!empty($search)) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(...$searches))
                ->setParameter('value', "%$search%");
        }

        $queryBuilder->leftJoin(TrackingEmergency::class, "tracking_emergency", "WITH", "tracking_emergency.id = emergency.id")
            ->leftJoin("emergency.buyer", "emergency_buyer")
            ->leftJoin("emergency.supplier", "emergency_supplier")
            ->leftJoin("emergency.carrier", "emergency_carrier")
            ->leftJoin("emergency.type", "emergency_type");

        $total = QueryBuilderHelper::count($queryBuilder, 'emergency');
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
