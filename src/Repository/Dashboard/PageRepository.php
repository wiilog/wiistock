<?php

namespace App\Repository\Dashboard;

use App\Entity\Dashboard;
use Doctrine\ORM\EntityRepository;

/**
 * @method Dashboard\Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dashboard\Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dashboard\Page[]    findAll()
 * @method Dashboard\Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends EntityRepository
{
}
