<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use App\Helper\QueryCounter;
use WiiCommon\Helper\Stream;
use Doctrine\ORM\EntityRepository;

/**
 * @method Fournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fournisseur[]    findAll()
 * @method Fournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FournisseurRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'Nom' => 'nom',
        'Code de référence' => 'codeReference',
    ];

    public function findOneByCodeReference($code)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier')
            ->where('supplier.codeReference = :code')
            ->setParameter('code', $code);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countByCode($code)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('COUNT(supplier)')
            ->where('supplier.codeReference = :code')
            ->setParameter('code', $code);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getCodesAndLabelsGroupedByReference(): array {
        $queryBuilder = $this->createQueryBuilder('supplier');

        return Stream::from($queryBuilder
                ->distinct()
                ->select('supplier.nom as supplierLabel')
                ->addSelect('supplier.codeReference as supplierCode')
                ->addSelect('referenceArticle.id')
                ->innerJoin('supplier.articlesFournisseur', 'supplierArticles')
                ->innerJoin('supplierArticles.referenceArticle', 'referenceArticle')
                ->getQuery()
                ->getResult())
            ->reduce(function(array $carry, array $supplierWithRefId) {
                $refId = $supplierWithRefId['id'];
                $supplierCode = $supplierWithRefId['supplierCode'];
                $supplierLabel = $supplierWithRefId['supplierLabel'];

                if(!isset($carry[$refId])) {
                    $carry[$refId] = [
                        "supplierCodes" => $supplierCode,
                        "supplierLabels" => $supplierLabel,
                    ];
                } else {
                    $carry[$refId]['supplierCodes'] .= ', ' . $supplierCode;
                    $carry[$refId]['supplierLabels'] .= ', ' . $supplierLabel;
                }

                return $carry;
            }, []);
    }

    public function getIdAndCodeBySearch($search)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier.id AS id')
            ->addSelect('supplier.nom AS text')
            ->addSelect('supplier.codeReference AS code')
            ->where('supplier.nom LIKE :search')
            ->orWhere('supplier.codeReference LIKE :search')
            ->setParameter('search', '%' . $search . '%');

        return $qb->getQuery()->getResult();
    }

    public function getIdAndLabelseBySearch($search)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier.id AS id')
            ->addSelect('supplier.codeReference AS code')
            ->addSelect('supplier.nom AS text')
            ->where('supplier.nom LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('search', '%' . $search . '%');

        return $qb->getQuery()->getResult();
    }

    public function getByParams($params = null)
    {
        $qb = $this->createQueryBuilder('supplier');

        $countTotal = QueryCounter::count($qb, 'supplier');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $qb
                        ->orderBy('supplier.' . self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']], $order);
                }
            }
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('supplier.nom LIKE :value OR supplier.codeReference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'supplier');

        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb
            ->select('supplier')
            ->getQuery();

        return [
            'recordsTotal' => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data' => $query->getResult()
        ];
    }

    public function findOneByColis($pack)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier')
            ->join('supplier.arrivages', 'arrivals')
            ->join('arrivals.packs', 'packs')
            ->where('packs = :pack')
            ->setParameter('pack', $pack);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
