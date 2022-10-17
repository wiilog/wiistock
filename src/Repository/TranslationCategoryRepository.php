<?php

namespace App\Repository;

use App\Entity\TranslationCategory;
use Doctrine\ORM\EntityRepository;

/**
 * @method TranslationCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method TranslationCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method TranslationCategory[]    findAll()
 * @method TranslationCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationCategoryRepository extends EntityRepository {

    public function findUnusedCategories(?TranslationCategory $parent, array $activeCategories) {
        $query = $this->createQueryBuilder("category");
            //->leftJoin("category.parent", "parentCategory");

        if($parent === null) {
            $query->andWhere("category.parent IS NULL");
        } else {
            $query->andWhere("category.parent = :parent")
                ->setParameter("parent", $parent);
        }

        return $query->andWhere("category.label NOT IN (:categories)")
            ->setParameter("categories", $activeCategories)
            ->getQuery()
            ->getResult();
    }

}
