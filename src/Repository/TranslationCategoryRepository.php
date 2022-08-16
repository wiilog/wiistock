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

}
