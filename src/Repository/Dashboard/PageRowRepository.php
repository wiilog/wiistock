<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityRepository;

/**
 * @method Dashboard\PageRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dashboard\PageRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dashboard\PageRow[]    findAll()
 * @method Dashboard\PageRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRowRepository extends EntityRepository
{
}
