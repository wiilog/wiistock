<?php

namespace App\Repository;

use App\Entity\Language;
use App\Entity\TranslationCategory;
use App\Entity\TranslationSource;
use Doctrine\ORM\EntityRepository;

/**
 * @method TranslationSource|null find($id, $lockMode = null, $lockVersion = null)
 * @method TranslationSource|null findOneBy(array $criteria, array $orderBy = null)
 * @method TranslationSource[]    findAll()
 * @method TranslationSource[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationSourceRepository extends EntityRepository {

    public function findByDefaultFrenchTranslation(TranslationCategory $category, string $translation) {
        return $this->createQueryBuilder("source")
            ->join("source.translations", "translation")
            ->leftJoin("translation.language", "language")
            ->andWhere("language.slug = :slug")
            ->andWhere("source.category = :category")
            ->andWhere("translation.translation LIKE :translation")
            ->setParameter("slug", Language::FRENCH_DEFAULT_SLUG)
            ->setParameter("category", $category)
            ->setParameter("translation", $translation)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUnusedTranslations(TranslationCategory $category, array $activeTranslations) {
        return $this->createQueryBuilder("source")
            ->join("source.translations", "translation")
            ->leftJoin("source.type", "joinType")
            ->leftJoin("source.status", "joinStatus")
            ->leftJoin("source.nature", "joinNature")
            ->leftJoin("source.freeField", "joinFreeField")
            ->leftJoin("source.elementOfFreeField", "joinElementOfFreeField")
            ->leftJoin("translation.language", "language")
            ->andWhere("language.slug = :slug")
            ->andWhere("source.category = :category")
            ->andWhere("joinType IS NULL")
            ->andWhere("joinStatus IS NULL")
            ->andWhere("joinNature IS NULL")
            ->andWhere("joinFreeField IS NULL")
            ->andWhere("joinElementOfFreeField IS NULL")
            ->andWhere("translation.translation NOT IN (:translations)")
            ->setParameter("slug", Language::FRENCH_DEFAULT_SLUG)
            ->setParameter("category", $category)
            ->setParameter("translations", $activeTranslations)
            ->getQuery()
            ->getResult();
    }

}
