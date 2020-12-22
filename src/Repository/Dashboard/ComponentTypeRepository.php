<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityRepository;

/**
 * @method Dashboard\ComponentType|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dashboard\ComponentType|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dashboard\ComponentType[]    findAll()
 * @method Dashboard\ComponentType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComponentTypeRepository extends EntityRepository
{
}
