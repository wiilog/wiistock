<?php

namespace App\Repository;

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

    public function findByFrenchTranslation(TranslationCategory $category, string $translation) {
        return $this->createQueryBuilder("source")
            ->join("source.translations", "translation")
            ->leftJoin("translation.language", "language")
            ->where("source.category = :category")
            ->andWhere("language.slug = 'french'")
            ->andWhere("translation.translation LIKE :translation")
            ->setParameter("category", $category)
            ->setParameter("translation", $translation)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
