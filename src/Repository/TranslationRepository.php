<?php

namespace App\Repository;

use App\Entity\Translation;
use Doctrine\ORM\EntityRepository;

/**
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationRepository extends EntityRepository {
    public function findCategories() {
        return [];
    }

}
