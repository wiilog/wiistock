<?php

namespace App\Repository\Emergency;


use App\Controller\FieldModesController;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @extends EntityRepository<EmergencyRepository>
 */
class EmergencyRepository extends EntityRepository {
    public function findByParamsAndFilters(ParameterBag $params, array $filters): array {
        $queryBuilder = $this->createQueryBuilder("emergency")
            ->groupBy('emergency.id');

        $total = QueryBuilderHelper::count($queryBuilder, 'emergency');

        $searchParams = $params->all('search');
        if (!empty($searchParams)) {
            $search = $searchParams['value'];
            if (!empty($search)) {
                $exprBuilder = $queryBuilder->expr();
                $queryBuilder
                    ->leftJoin('emergency.arrivals', 'arrivals_search')

                    ;
                    //->setParameter('value', '%' . $search . '%');
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
