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

        $queryBuilder = $this->createQueryBuilder("emergency")
            ->select("emergency.id AS id")
            ->distinct()
            ->addSelect("emergency.dateStart AS ". FixedFieldEnum::dateStart->name)
            ->addSelect("emergency.dateEnd AS ". FixedFieldEnum::dateEnd->name)
            ->addSelect("emergency.closedAt AS closedAt")
            ->addSelect("GREATEST(FIRST($lastArrivalNumberSubquery), FIRST($lastReceptionNumberSubquery)) AS lastEntityNumber")
            ->addSelect("emergency.createdAt AS ". FixedFieldEnum::createdAt->name)
            ->addSelect("emergency.command AS ". FixedFieldEnum::orderNumber->name)
            ->addSelect("tracking_emergency.postNumber AS ". FixedFieldEnum::postNumber->name)
            ->addSelect("emergency_buyer.username AS ". FixedFieldEnum::buyer->name)
            ->addSelect("emergency_supplier.nom AS ". FixedFieldEnum::supplier->name)
            ->addSelect("emergency_carrier.label AS ". FixedFieldEnum::carrier->name)
            ->addSelect("emergency.carrierTrackingNumber AS ". FixedFieldEnum::carrierTrackingNumber->name)
            ->addSelect("emergency_type.label AS ". FixedFieldEnum::type->name)
            ->addSelect("tracking_emergency.internalArticleCode AS " . FixedFieldEnum::internalArticleCode->name)
            ->addSelect("tracking_emergency.supplierArticleCode AS " . FixedFieldEnum::supplierArticleCode->name)
            ->leftJoin(TrackingEmergency::class, "tracking_emergency", "WITH", "tracking_emergency.id = emergency.id")
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
