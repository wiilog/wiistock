<?php

namespace App\Repository;

use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fournisseur;
use App\Helper\QueryBuilderHelper;
use Symfony\Component\HttpFoundation\InputBag;
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
        FixedFieldEnum::name->name => 'nom',
        FixedFieldEnum::code->name => 'codeReference'
    ];

    public function findOneByCodeReference($code)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier')
            ->where('supplier.codeReference = :code')
            ->setParameter('code', $code);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getIdAndCodeBySearch(?string $search, ?int $reference = null): array
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier.id AS id')
            ->addSelect('supplier.nom AS text')
            ->addSelect('supplier.codeReference AS code')
            ->where('supplier.nom LIKE :search')
            ->orWhere('supplier.codeReference LIKE :search')
            ->setParameter('search', '%' . $search . '%');

        if($reference !== null) {
            $qb
                ->leftJoin("supplier.articlesFournisseur", "join_supplierArticles")
                ->leftJoin("join_supplierArticles.referenceArticle", "join_referenceArticle")
                ->andWhere("join_referenceArticle.id = :reference")
                ->setParameter("reference", $reference);
        }

        return $qb->getQuery()->getResult();
    }

    public function getIdAndLabelseBySearch($search)
    {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier.id AS id')
            ->addSelect('supplier.codeReference AS code')
            ->addSelect('supplier.nom AS text')
            ->where('supplier.nom LIKE :search')
            ->setParameter('search', '%' . $search . '%');

        return $qb->getQuery()->getResult();
    }

    public function getByParams(InputBag $params = null): array {
        $qb = $this->createQueryBuilder('supplier');
        $expr = $qb->expr();

        $countTotal = QueryBuilderHelper::count($qb, 'supplier');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    $qb
                        ->orderBy('supplier.' . (self::DtToDbLabels[$column] ?? $column), $order);
                }
            }
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $searchOr = $expr->orX(
                        'supplier.nom LIKE :value',
                        'supplier.codeReference LIKE :value',
                        'supplier.email LIKE :value',
                        'supplier.phoneNumber LIKE :value',
                        'supplier.address LIKE :value',
                        'supplier.receiver LIKE :value',
                    );

                    $searchLowercase = strtolower($search);
                    if ($searchLowercase === 'oui') {
                        $searchOr
                            ->add('supplier.possibleCustoms = true')
                            ->add('supplier.urgent = true');
                    }
                    else if ($searchLowercase === 'non') {
                        $searchOr
                            ->add('supplier.possibleCustoms = false')
                            ->add('supplier.urgent = false');
                    }

                    $qb
                        ->andWhere($searchOr)
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, 'supplier');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        $query = $qb
            ->select('supplier')
            ->getQuery();

        return [
            'recordsTotal' => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data' => $query->getResult()
        ];
    }

    public function getForNomade() {
        $qb = $this->createQueryBuilder('supplier');

        $qb->select('supplier.id AS id')
            ->addSelect('supplier.codeReference AS code')
            ->addSelect('supplier.nom AS label');

        return $qb->getQuery()->getResult();
    }

    public function getForExport(): iterable {
        return $this->createQueryBuilder("supplier")
            ->getQuery()
            ->toIterable();
    }

    public function getForSelect(?string $term, array $options = []): array {
        return $this->createQueryBuilder("supplier")
            ->andWhere("supplier.nom LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }
}
