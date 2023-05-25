<?php

namespace App\Repository;

use App\Entity\CategorieStatut;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends EntityRepository {

    public function getForSelect(?string $term, ?string $type = null) {
        $query = $this->createQueryBuilder("status");

        if($type) {
            $query->andWhere("status.type = :type")
                ->setParameter("type", $type);
        }

        return $query->select("status.id AS id, status.nom AS text")
            ->andWhere("status.nom LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }


    public function findByCategorieName($categorieName,
                                        $orderByField = false) {
        $statutEntity = $this->getEntityManager()->getClassMetadata(Statut::class);

        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom = :categorieName');

        if ($orderByField && $statutEntity->hasField($orderByField)) {
            $queryBuilder->orderBy('status.' . $orderByField, 'ASC');
        }

        $queryBuilder
            ->setParameter("categorieName", $categorieName);
        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function getIdDefaultsByCategoryName(string $categoryName): array {
        $queryBuilder = $this->createQueryBuilder('status')
            ->addSelect('type.id AS typeId')
            ->join('status.categorie', 'categorie')
            ->leftJoin('status.type', 'type')
            ->andWhere('categorie.nom = :categoryName')
            ->andWhere('status.defaultForCategory = 1')
            ->setParameter("categoryName", $categoryName);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return array_reduce($res, function (array $carry, $status) {
            $typeId = $status['typeId'] ?: 0;
            $carry[$typeId] = $status[0]->getId();
            return $carry;
        }, []);
    }

    public function findByCategorieNames(?array $categorieNames, $ordered = false, ?array $states = []) {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom IN (:categorieNames)')
            ->setParameter("categorieNames", $categorieNames, Connection::PARAM_STR_ARRAY);

        if ($ordered) {
            $queryBuilder->orderBy('status.displayOrder', 'ASC');
        }

        if (!empty($states)) {
            $queryBuilder
                ->andWhere('status.state IN (:states) OR status.state IS NULL')
                ->setParameter('states', $states);
        }

        return $queryBuilder->getQuery()->execute();
    }

    public function findByCategorieNamesAndStatusCodes(?array $categorieNames, ?array $statusCodes) {
        $queryBuilder = $this->createQueryBuilder('status')
            ->join('status.categorie', 'categorie')
            ->andWhere('categorie.nom IN (:categorieNames)')
            ->andWhere('status.code IN (:statusCodes)')
            ->setParameter("categorieNames", $categorieNames, Connection::PARAM_STR_ARRAY)
            ->setParameter("statusCodes", $statusCodes, Connection::PARAM_STR_ARRAY);

        return $queryBuilder->getQuery()->execute();
    }

    public function findByCategoryNameAndStatusCodes($categoryName, $statusCodes) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT s
            FROM App\Entity\Statut s
            JOIN s.categorie c
            WHERE c.nom = :categoryName
            AND s.nom IN (:statusCodes)"
        );
        $query->setParameter("categoryName", $categoryName);
        $query->setParameter("statusCodes", $statusCodes, Connection::PARAM_STR_ARRAY);

        return $query->execute();
    }

    public function findOneByCategorieNameAndStatutCode($categorieName, $statutCode): ?Statut {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->join('s.categorie', 'c')
            ->where('c.nom = :categorieName AND s.code = :statutCode')
            ->setParameters([
                'categorieName' => $categorieName,
                'statutCode' => $statutCode
            ]);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCategorieNameAndStatutState($categorieName, $state) {
        $queryBuilder = $this->createQueryBuilder('s');
        $queryBuilder
            ->join('s.categorie', 'c')
            ->where('c.nom = :categorieName AND s.state = :state')
            ->setParameters([
                'categorieName' => $categorieName,
                'state' => $state
            ]);

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCategoryAndStates(string $categoryName, array $states): array {
        $queryBuilder = $this->createQueryBuilder('status');
        $queryBuilder
            ->join('status.categorie', 'category')
            ->where('category.nom = :categoryName AND status.state IN (:states)')
            ->setParameter('categoryName', $categoryName)
            ->setParameter('states', $states);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countSimilarLabels($category, $label, $type, $current = null): int {
        $qb = $this->createQueryBuilder("s")
            ->select("COUNT(s)")
            ->where("s.nom LIKE :label")
            ->andWhere("s.categorie = :category")
            ->setParameter("category", $category)
            ->setParameter("label", $label);

        if ($type) {
            $qb
                ->andWhere("s.type = :type")
                ->setParameter("type", $type);
        }
        else {
            $qb->andWhere("s.type IS NULL");
        }

        if ($current) {
            $qb->andWhere("s.id != :current")
                ->setParameter("current", $current);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Statut[]
     */
    public function findStatusByType(string $categoryLabel,
                                     Type $type = null,
                                     array $stateFilters = []): array {
        $qb = $this->createQueryBuilder('status')
            ->join('status.categorie', 'category')
            ->andWhere('category.nom = :categoryLabel')
            ->addOrderBy('status.displayOrder', 'ASC')
            ->setParameter('categoryLabel', $categoryLabel);

        if (!empty($stateFilters)) {
            $qb
                ->andWhere('status.state IN (:stateIds)')
                ->setParameter(':stateIds', $stateFilters);
        }

        if ($type) {
            $qb
                ->andWhere("status.type = :type OR status.type IS NULL")
                ->setParameter("type", $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function getMobileStatus(bool $dispatchStatus, bool $handlingStatus): array {
        if ($dispatchStatus || $handlingStatus) {
            $queryBuilder = $this->createQueryBuilder('status')
                ->select('status.id AS id')
                ->addSelect('status.nom AS label')
                ->addSelect('status_category.nom AS category')
                ->addSelect('status.commentNeeded AS commentNeeded')
                ->addSelect('status.groupedSignatureType AS groupedSignatureType')
                ->addSelect('type.id AS typeId')
                ->addSelect("(
                    CASE
                        WHEN status.state = :treatedState THEN 'treated'
                        WHEN status.state = :partialState THEN 'partial'
                        WHEN status.state = :notTreatedState THEN 'notTreated'
                        WHEN status.state = :inProgressState THEN 'inProgress'
                        ELSE ''
                    END
                ) AS state")
                ->addSelect('status.displayOrder AS displayOrder')
                ->join('status.categorie', 'status_category')
                ->leftJoin('status.type', 'type')
                ->orderBy('status.displayOrder', 'ASC')
                ->setParameter('treatedState', Statut::TREATED)
                ->setParameter('partialState', Statut::PARTIAL)
                ->setParameter('inProgressState', Statut::IN_PROGRESS)
                ->setParameter('notTreatedState', Statut::NOT_TREATED);

            if ($dispatchStatus) {
                $queryBuilder
                    ->where('status_category.nom = :dispatchCategoryLabel')
                    ->setParameter('dispatchCategoryLabel', CategorieStatut::DISPATCH);
            }

            if ($handlingStatus) {
                $queryBuilder
                    ->orWhere('status_category.nom = :handlingCategoryLabel')
                    ->setParameter('handlingCategoryLabel', CategorieStatut::HANDLING);
            }

            return $queryBuilder
                ->getQuery()
                ->getResult();
        } else {
            return [];
        }
    }

    public function findAvailableStatuesForDeliveryImport($id) {
        return $this->createQueryBuilder("statut")
            ->select("statut.nom")

            ->where("statut.categorie = :id")
            ->andWhere("statut.state IN (:allowed_statuses)")

            ->setParameter("id", $id)
            ->setParameter("allowed_statuses", [Statut::DRAFT, Statut::NOT_TREATED])

            ->getQuery()
            ->getResult();
    }

    public function findDuplicates(string $nom, string $language, array $except = []) {
        return $this->createQueryBuilder("status")
            ->join("status.labelTranslation", "join_source")
            ->leftJoin("join_source.translations", "join_translations")
            ->leftJoin("join_translations.language", "join_language")
            ->andWhere("status NOT IN (:except)")
            ->andWhere("join_language.slug = :language")
            ->andWhere("join_translations.translation = :nom")
            ->setParameter("except", $except)
            ->setParameter("language", $language)
            ->setParameter("nom", $nom)
            ->getQuery()
            ->getResult();
    }
}
