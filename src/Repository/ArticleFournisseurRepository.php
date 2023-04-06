<?php

namespace App\Repository;

use App\Entity\ArticleFournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @method ArticleFournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleFournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleFournisseur[]    findAll()
 * @method ArticleFournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleFournisseurRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Code Fournisseur' => 'fournisseur',
        'Référence' => 'reference',
        'Article de référence' => 'art_ref',
        'label' => 'label',
    ];

    public function findBySearch($value)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.id, a.label as text
          FROM App\Entity\ArticleFournisseur a
          WHERE a.label LIKE :search"
        )->setParameter('search', '%' . $value . '%');

        return $query->execute();
    }

    public function getGroupedByRefArticle() {

        $queryBuilder = $this->createQueryBuilder('article_fournisseur');

        $articlesFournisseurs = $queryBuilder
            ->addSelect('article_fournisseur.label as providerArticleLabel')
            ->addSelect('reference_article.id as idRef')
            ->addSelect('fournisseur.nom as providerName')
            ->join('article_fournisseur.referenceArticle', 'reference_article')
            ->join('article_fournisseur.fournisseur', 'fournisseur')
            ->getQuery()
            ->execute();

        return array_reduce($articlesFournisseurs, function(array $accumulator, array $articleFournisseur) {
            $idRef = $articleFournisseur['idRef'];
            $providerArticleLabel = $articleFournisseur['providerArticleLabel'];
            $providerName = $articleFournisseur['providerName'];
            if (!isset($accumulator[$idRef])) {
                $accumulator[$idRef] = [
                    'providerNames' => '',
                    'providerArticlesLabels' => ''
                ];
            }
            $accumulator[$idRef]['providerNames'] .= ((!empty($accumulator[$idRef]['providerNames']) ? ', ' : '') . $providerName);
            $accumulator[$idRef]['providerArticlesLabels'] .= ((!empty($accumulator[$idRef]['providerArticlesLabels']) ? ', ' : '') . $providerArticleLabel);

            return $accumulator;
        }, []);
    }

    public function getByFournisseur($fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.fournisseur = :fournisseurId"
        )->setParameters(['fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }


    public function findByRefArticle($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT rf
            FROM App\Entity\ArticleFournisseur rf
            WHERE rf.referenceArticle = :id"
        )->setParameter('id', $id);

        return $query->getResult();
    }

    public function countByRefArticle($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(rf)
            FROM App\Entity\ArticleFournisseur rf
            WHERE rf.referenceArticle = :id"
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param int $refArticleId
	 * @param int $fournisseurId
	 * @return ArticleFournisseur[]|null
	 */
    public function findByRefArticleAndFournisseur($refArticleId, $fournisseurId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.referenceArticle = :refArticleId
              AND af.fournisseur = :fournisseurId"
        )->setParameters(['refArticleId' => $refArticleId, 'fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }

    public function findByParams(InputBag $params, Utilisateur $user)
    {
        $queryBuilder = $this->createQueryBuilder('supplier_article');
        $visibilityGroup = $user->getVisibilityGroups();
        if (!$visibilityGroup->isEmpty()) {
            $queryBuilder
                ->join('supplier_article.referenceArticle', 'join_referenceArticle')
                ->join('join_referenceArticle.visibilityGroup', 'visibility_group')
                ->andWhere('visibility_group.id IN (:userVisibilityGroups)')
                ->setParameter('userVisibilityGroups', Stream::from(
                    $visibilityGroup->toArray()
                )->map(fn(VisibilityGroup $visibilityGroup) => $visibilityGroup->getId())->toArray());
        }
        $countTotal = $this->countAll();

        $queryBuilder
            ->select('supplier_article');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->all('order')))
            {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']];
                    if ($column === 'fournisseur') {
                        $queryBuilder
                            ->leftJoin('supplier_article.fournisseur', 'f')
                            ->orderBy('f.nom', $order);
                    } else if ($column === 'art_ref') {
                        $queryBuilder
                            ->leftJoin('supplier_article.referenceArticle', 'ra')
                            ->orderBy('ra.libelle', $order);
                    } else if ($column === 'label') {
                        $queryBuilder
                            ->orderBy('supplier_article.label', $order);
                    } else {
                        $queryBuilder
                            ->orderBy('supplier_article.' . $column, $order);
                    }
                }
            }
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->leftJoin('supplier_article.fournisseur', 'f2')
                        ->leftJoin('supplier_article.referenceArticle', 'ra2')
                        ->andWhere('f2.nom LIKE :value OR supplier_article.reference LIKE :value OR ra2.libelle LIKE :value OR supplier_article.label LIKE :value OR ra2.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            $queryBuilder->select('count(supplier_article)');
            $countQuery = (int) $queryBuilder->getQuery()->getSingleScalarResult();
        } else {
            $countQuery = $countTotal;
        }
        $queryBuilder
            ->select('supplier_article')
            ->andWhere('supplier_article.visible = 1');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(af)
            FROM App\Entity\ArticleFournisseur af
           "
        );

        return $query->getSingleScalarResult();
    }

    public function getByRefArticleAndFournisseur($refArticleId, $fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.referenceArticle = :refArticleId AND af.fournisseur = :fournisseurId"
        )->setParameters(['refArticleId' => $refArticleId, 'fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }

    public function getIdAndLibelleBySearch($search, $refArticle = null)
    {
        $queryBuilder = $this->createQueryBuilder('articleFournisseur')
            ->addSelect('articleFournisseur.id AS id')
            ->addSelect('articleFournisseur.reference AS text')
            ->andWhere('articleFournisseur.reference LIKE :search')
            ->setParameter('search', '%' . $search . '%');

        if (!empty($refArticle)) {
            $queryBuilder
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->andWhere('referenceArticle.reference = :referenceArticleReference')
                ->setParameter('referenceArticleReference', $refArticle);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getIdAndLibelleBySearchAndRef($search, $ref)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT articleFournisseur.id,
                         articleFournisseur.reference as text
          FROM App\Entity\ArticleFournisseur articleFournisseur
          WHERE articleFournisseur.reference LIKE :search AND articleFournisseur.referenceArticle = :ref"
        )->setParameters([
            'search' => '%' . $search . '%',
            'ref' => $ref,
        ]);

        return $query->execute();
    }

	/**
	 * @param ReferenceArticle $ref
	 * @return array
	 */
    public function getIdAndLibelleByRef($ref)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT articleFournisseur.id,
                         articleFournisseur.reference as reference
          FROM App\Entity\ArticleFournisseur articleFournisseur
          WHERE articleFournisseur.referenceArticle = :ref"
        )->setParameter('ref', $ref);

        return $query->execute();
    }

    /**
     * @param string $reference
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByReference($reference): int
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(articleFournisseur)
           FROM App\Entity\ArticleFournisseur articleFournisseur
           WHERE articleFournisseur.reference = :reference"
        )->setParameter('reference', $reference);

        return (int) $query->getSingleScalarResult();
    }

    /**
     * @param string $label
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByLabel($label): int
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(articleFournisseur)
           FROM App\Entity\ArticleFournisseur articleFournisseur
           WHERE articleFournisseur.label = :label"
        )
            ->setParameter('label', $label);

        return (int) $query->getSingleScalarResult();
    }

    public function getForSelect(?string $search, ?string $supplier = null): array {
        $qb = $this->createQueryBuilder("supplier_article")
            ->select("supplier_article.id AS id")
            ->addSelect("supplier_article.reference AS text")
            ->andWhere("supplier_article.reference LIKE :search")
            ->setParameter("search", "%$search%");

        if($supplier) {
            $qb->leftJoin("supplier_article.fournisseur", "join_supplier")
                ->andWhere("join_supplier.id = :supplier")
                ->setParameter("supplier", $supplier);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function deleteSupplierArticles(array $ignoredSupplierArticles, array $linkedReferenceArticles): void {
        $supplierArticleIdToDelete = $this->createQueryBuilder('supplierArticle')
            ->select('supplierArticle.id')
            ->join("supplierArticle.referenceArticle", "join_referenceArticle")
            ->leftJoin("supplierArticle.articles", "join_linkedArticle")
            ->leftJoin("supplierArticle.receptionReferenceArticles", "join_linkedReceptionReferenceArticles")
            ->andWhere("supplierArticle.reference NOT IN (:ignoredSupplierArticles)")
            ->andWhere("join_referenceArticle.id IN (:linkedReferenceArticles)")
            ->andWhere("join_linkedArticle.id IS NULL")
            ->andWhere("join_linkedReceptionReferenceArticles.id IS NULL")
            ->setParameter("ignoredSupplierArticles", $ignoredSupplierArticles)
            ->setParameter("linkedReferenceArticles", $linkedReferenceArticles)
            ->getQuery()
            ->getSingleColumnResult();

        if (!empty($supplierArticleIdToDelete)) {
            $this->createQueryBuilder('supplierArticle2')
                ->delete()
                ->andWhere("supplierArticle2.id IN (:supplierArticleIdToDelete)")
                ->setParameter("supplierArticleIdToDelete", $supplierArticleIdToDelete)
                ->getQuery()
                ->execute();
        }
    }
}
