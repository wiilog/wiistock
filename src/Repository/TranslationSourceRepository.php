<?php

namespace App\Repository;

use App\Entity\TranslationSource;
use Doctrine\ORM\EntityRepository;

/**
 * @method TranslationSource|null find($id, $lockMode = null, $lockVersion = null)
 * @method TranslationSource|null findOneBy(array $criteria, array $orderBy = null)
 * @method TranslationSource[]    findAll()
 * @method TranslationSource[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationSourceRepository extends EntityRepository {

}
