<?php

namespace App\Repository\Fields;

use App\Entity\Fields\FixedFieldByType;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<FixedFieldByType>
 *
 * @method FixedFieldByType|null find($id, $lockMode = null, $lockVersion = null)
 * @method FixedFieldByType|null findOneBy(array $criteria, array $orderBy = null)
 * @method FixedFieldByType[]    findAll()
 * @method FixedFieldByType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FixedFieldByTypeRepository extends EntityRepository {
}
