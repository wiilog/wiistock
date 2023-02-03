<?php

namespace App\Repository;

use App\Entity\PurchaseRequest;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method PurchaseRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseRequest[]    findAll()
 * @method PurchaseRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRequestRepository extends EntityRepository
{
    public function findByStateAndRequester(int $state, Utilisateur $requester) {
        return $this->createQueryBuilder('purchase_request')
            ->join('purchase_request.status', 'status')
            ->where('status.state = :state')
            ->andWhere('purchase_request.requester = :requester')
            ->setParameter('state', $state)
            ->setParameter('requester', $requester)
            ->getQuery()
            ->execute();
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('purchase_request')
            ->select('purchase_request.number')
            ->where('purchase_request.number LIKE :value')
            ->orderBy('purchase_request.creationDate', 'DESC')
            ->addOrderBy('purchase_request.number', 'DESC')
            ->setParameter('value', PurchaseRequest::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function findByParamsAndFilters(InputBag $params, $filters) {

        $qb = $this->createQueryBuilder("purchase_request");
        $total = QueryBuilderHelper::count($qb, "purchase_request");

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('purchase_request.status', 'filter_status')
                        ->andWhere('filter_status.id in (:status)')
                        ->setParameter('status', $value);
                    break;
                case 'requesters':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('purchase_request.requester', 'filter_requester')
                        ->andWhere("filter_requester.id in (:filter_value_requester)")
                        ->setParameter('filter_value_requester', $value);
                    break;
                case 'buyers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('purchase_request.buyer', 'filter_buyer')
                        ->andWhere("filter_buyer.id in (:filter_value_buyer)")
                        ->setParameter('filter_value_buyer', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('purchase_request.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('purchase_request.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'purchase_request.number LIKE :value',
                                'search_requester.username LIKE :value',
                                'search_status.nom LIKE :value',
                                'search_buyer.username LIKE :value',
                                'search_supplier.nom LIKE :value',
                            )
                            . ')')
                        ->leftJoin('purchase_request.requester', 'search_requester')
                        ->leftJoin('purchase_request.status', 'search_status')
                        ->leftJoin('purchase_request.buyer', 'search_buyer')
                        ->leftjoin('purchase_request.supplier', 'search_supplier')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'number':
                            $qb
                                ->orderBy("purchase_request.number", $order);
                            break;
                        case 'requester':
                            $qb
                                ->leftJoin('purchase_request.requester', 'order_requester')
                                ->orderBy('order_requester.username', $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('purchase_request.status', 'order_status')
                                ->orderBy('order_status.nom', $order);
                            break;
                        case 'buyer':
                            $qb
                                ->leftJoin('purchase_request.buyer', 'order_buyer')
                                ->orderBy('order_buyer.username', $order);
                            break;
                        default:
                            if (property_exists(PurchaseRequest::class, $column)) {
                                $qb
                                    ->orderBy('purchase_request.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'purchase_request');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function iterateByDates(DateTime $dateMin,
                                   DateTime $dateMax): iterable {
        $qb = $this->createQueryBuilder("request");

        return $qb
            ->select('request.id AS id')
            ->addSelect('request.number AS number')
            ->addSelect('join_status.nom AS statusName')
            ->addSelect('join_requester.username AS requester')
            ->addSelect('join_buyer.username AS buyer')
            ->addSelect('request.creationDate AS creationDate')
            ->addSelect('request.validationDate AS validationDate')
            ->addSelect('request.considerationDate AS considerationDate')
            ->addSelect('request.processingDate AS processingDate')
            ->addSelect('request.comment AS comment')
            ->leftJoin('request.status', 'join_status')
            ->leftJoin('request.requester', 'join_requester')
            ->leftJoin('request.buyer', 'join_buyer')
            ->where("request.creationDate BETWEEN :dateMin AND :dateMax")
            ->orderBy('request.creationDate', 'DESC')
            ->addOrderBy('request.id', 'DESC')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->toIterable();
    }

    public function getPurchaseRequestForSelect(Utilisateur $user) {
        return $this->createQueryBuilder("purchase_request")
            ->leftJoin("purchase_request.status", "purchase_request_status")
            ->andWhere("purchase_request.requester = :user")
            ->andWhere("purchase_request_status.state = :draft")
            ->setParameter("user", $user)
            ->setParameter("draft", STATUT::DRAFT)
            ->getQuery()
            ->getResult();
    }
}
