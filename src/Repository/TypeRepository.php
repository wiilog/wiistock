<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\FreeField;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\Dispute;
use App\Entity\OperationHistory\TransportHistoryRecord;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * @method Type|null find($id, $lockMode = null, $lockVersion = null)
 * @method Type|null findOneBy(array $criteria, array $orderBy = null)
 * @method Type[]    findAll()
 * @method Type[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TypeRepository extends EntityRepository {

    /**
     * @param array $categoryLabels
     * @param string|null $order ("asc" ou "desc")
     * @return Type[]
     */
    public function findByCategoryLabels(array $categoryLabels, $order = null): array {
        if (empty($categoryLabels)) {
            return [];
        }

        $queryBuilder = $this
            ->createQueryBuilder('type')
            ->join('type.category', 'category')
            ->where('category.label IN (:categoryLabels)')
            ->setParameter('categoryLabels', $categoryLabels);

        if ($order) {
            $queryBuilder->orderBy('type.label', $order);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findByCategoryLabelsAndLabels(array $categoryLabels, array $labels, $order = null): array {
        $queryBuilder = $this
            ->createQueryBuilder('type')
            ->join('type.category', 'category')
            ->andWhere('category.label IN (:categoryLabels)')
            ->andWhere('type.label IN (:labels)')
            ->setParameter('categoryLabels', $categoryLabels)
            ->setParameter('labels', $labels);

        if ($order) {
            $queryBuilder->orderBy('type.label', $order);
        }

        return !empty($categoryLabels)
            ? $queryBuilder
                ->getQuery()
                ->execute()
            : [];
    }

    private function createSelectBuilder(?string $category, array $options = []): QueryBuilder {
        $alreadyDefinedTypes = $options['alreadyDefinedTypes'] ?? [];
        $language = $options['language'] ?? null;
        $defaultLanguage = $options['defaultLanguage'] ?? null;


        $subQueryBuilder = $this->createQueryBuilder('sub_type')
            ->select("CONCAT_WS(':', join_status.id, (COALESCE(join_translation.translation, join_translation_default.translation, join_status.nom)))")
            ->leftJoin("sub_type.statuts", "join_status", Join::WITH, "join_status.defaultForCategory = 1")
            ->leftJoin("join_status.labelTranslation", "join_labelTranslation")
            ->leftJoin("join_labelTranslation.translations", "join_translation", Join::WITH, "join_translation.language = :language")
            ->leftJoin("join_labelTranslation.translations", "join_translation_default", Join::WITH, "join_translation_default.language = :default")
            ->andWhere("type.id = sub_type.id")
            ->getQuery()
            ->getDQL();


        $qb = $this->createQueryBuilder("type")
            ->addSelect("type.id AS id")
            ->addSelect("type.label AS text")
            ->addSelect("($subQueryBuilder) AS defaultStatus")
            ->join("type.category", "category")
            ->andWhere("category.label = '$category'")
            ->setParameter("language", $language)
            ->setParameter("default", $defaultLanguage);

        if (!empty($alreadyDefinedTypes)) {
            $qb->andWhere("type.id NOT IN (:alreadyDefinedTypes)")
                ->setParameter("alreadyDefinedTypes", $alreadyDefinedTypes);
        }

        return $qb;
    }

    public function getForSelect(?string $category, ?string $term, array $options = []): array {
        return $this->createSelectBuilder($category, $options)
            ->andWhere("type.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function countAvailableForSelect(?string $category, array $options = []): int {
        return $this->createSelectBuilder($category, $options)
            ->select("COUNT(type)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getIdAndLabelByCategoryLabel($category) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT t.id, t.label
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE c.label = :category"
        );
        $query->setParameter("category", $category);

        return $query->execute();
    }

    public function findOneByCategoryLabel(string $category, int $type = null): ?Type {
        $queryBuilder = $this->createQueryBuilder('type')
            ->join('type.category', 'category')
            ->andWhere('category.label = :category')
            ->setParameter("category", $category);

        if (isset($type)) {
            $queryBuilder
                ->andWhere('type.id = :type')
                ->setParameter("type", $type);
        }

        $result = $queryBuilder
            ->getQuery()
            ->execute();

        return !empty($result) ? $result[0] : null;
    }

    public function countByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(t)
            FROM App\Entity\Type t
            WHERE LOWER(t.label) = :label
           "
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    public function isTypeUsed($typeId): bool {
        $entityManager = $this->getEntityManager();

        $tableConfig = [
            ['class' => Article::class, 'where' => 'item.type = :id'],
            ['class' => FreeField::class, 'where' => 'item.type = :id'],
            ['class' => Collecte::class, 'where' => 'item.type = :id'],
            ['class' => Demande::class, 'where' => 'item.type = :id'],
            ['class' => Dispute::class, 'where' => 'item.type = :id'],
            ['class' => Reception::class, 'where' => 'item.type = :id'],
            ['class' => ReferenceArticle::class, 'where' => 'item.type = :id'],
            ['class' => Handling::class, 'where' => 'item.type = :id'],
            ['class' => Dispatch::class, 'where' => 'item.type = :id'],
            ['class' => TransportRequest::class, 'where' => 'item.type = :id'],
            ['class' => TransportHistoryRecord::class, 'where' => 'item.type = :id'],
        ];

        $resultsCount = array_map(function(array $table) use ($entityManager, $typeId) {
            $queryBuilder = $entityManager->createQueryBuilder()
                ->select('COUNT(item)')
                ->from($table['class'], 'item');

            return $queryBuilder
                ->where($table['where'])
                ->setParameter(':id', $typeId)
                ->getQuery()
                ->getSingleScalarResult();
        }, $tableConfig);

        return count(array_filter($resultsCount, function($count) {
                return ((int) $count) > 0;
            })) > 0;
    }

    public function findOneByLabel($label) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT t
            FROM App\Entity\Type t
            WHERE LOWER(t.label) = :label
           "
        )->setParameter('label', strtolower($label));

        return $query->getOneOrNullResult();
    }

    /**
     * @param string $categoryLabel
     * @param string $typeLabel
     * @return Type|null
     * @throws NonUniqueResultException
     */
    public function findOneByCategoryLabelAndLabel($categoryLabel, $typeLabel) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT t
            FROM App\Entity\Type t
            JOIN t.category c
            WHERE t.label = :typeLabel
            AND c.label = :categoryLabel
           "
        )->setParameters([
            'typeLabel' => $typeLabel,
            'categoryLabel' => $categoryLabel,
        ]);

        return $query->getOneOrNullResult();
    }

}
