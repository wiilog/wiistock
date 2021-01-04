<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityRepository;

/**
 * @method Dashboard\Component|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dashboard\Component|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dashboard\Component[]    findAll()
 * @method Dashboard\Component[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComponentRepository extends EntityRepository
{
}
