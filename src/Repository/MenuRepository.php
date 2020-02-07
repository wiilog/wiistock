<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Menu|null find($id, $lockMode = null, $lockVersion = null)
 * @method Menu|null findOneBy(array $criteria, array $orderBy = null)
 * @method Menu[]    findAll()
 * @method Menu[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

	/**
	 * @param string $menu
	 * @param string $submenu
	 * @return Menu
	 * @throws NonUniqueResultException
	 */
	public function findOneByMenuAndSubmenu($menu, $submenu)
	{
		return $this->createQueryBuilder('mc')
			->andWhere('m.menu = :menu')
			->setParameter('menu', $menu)
			->andWhere('m.submenu = :submenu')
			->setParameter('submenu', $submenu)
			->getQuery()
			->getOneOrNullResult();
	}

}
