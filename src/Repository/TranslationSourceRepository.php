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

    public function findOneByDefaultFrenchTranslation(TranslationCategory $category, string $translation) {
        $res = $this->createQueryBuilder("source")
            ->join("source.translations", "translation")
            ->leftJoin("translation.language", "language")
            ->andWhere("language.slug = :slug")
            ->andWhere("source.category = :category")
            ->andWhere("translation.translation = :translation")
            ->setParameter("slug", Language::FRENCH_DEFAULT_SLUG)
            ->setParameter("category", $category)
            ->setParameter("translation", $translation)
            ->getQuery()
            ->getResult();
        $resCount = count($res);
        if ($resCount === 0 || $resCount === 1) {
            return $res[0] ?? null;
        }
        else { // for case-sensitive comparison
            /** @var TranslationSource $source */
            foreach ($res as $source) {
                $t = $source->getTranslationIn(Language::FRENCH_DEFAULT_SLUG)
                    ?->getTranslation();
                if ($translation === $t) {
                    return $source;
                }
            }
            return $res[0] ?? null;
        }
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

    public function getTranslationsByLanguage(Language $language): array {
        return $this->createQueryBuilder("translation_source")
            ->select("translation.translation AS translation")
            ->addSelect("category.label AS menu")
            ->leftJoin("translation_source.translation", "translation")
            ->leftJoin("translation_source.category", "category")
            ->where("translation.languade = :language")
            ->setParameter("language", $language)
            ->getQuery()
            ->getResult();
    }

}
