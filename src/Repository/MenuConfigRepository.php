<?php

namespace App\Repository;

use App\Entity\MenuConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method MenuConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method MenuConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method MenuConfig[]    findAll()
 * @method MenuConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuConfig::class);
    }

	/**
	 * @param string $menu
	 * @param string $submenu
	 * @return MenuConfig
	 * @throws NonUniqueResultException
	 */
	public function findOneByMenuAndSubmenu($menu, $submenu)
	{
		return $this->createQueryBuilder('mc')
			->andWhere('mc.menu = :menu')
			->setParameter('menu', $menu)
			->andWhere('mc.submenu = :submenu')
			->setParameter('submenu', $submenu)
			->getQuery()
			->getOneOrNullResult();
	}

	/**
	 * @param string $menu
	 * @param string $submenu
	 * @return mixed
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function getOneDisplayByMenuAndSubmenu($menu, $submenu)
	{
		return $this->createQueryBuilder('mc')
			->select('mc.display')
			->andWhere('mc.menu = :menu')
			->setParameter('menu', $menu)
			->andWhere('mc.submenu = :submenu')
			->setParameter('submenu', $submenu)
			->getQuery()
			->getSingleScalarResult();
	}

	/**
	 * @param string $menu
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function countDisplayedByMenu($menu)
	{
		return $this->createQueryBuilder('mc')
			->select('COUNT(mc.id)')
			->andWhere('mc.menu = :menu')
			->andWhere('mc.display = 1')
			->setParameter('menu', $menu)
			->getQuery()
			->getSingleScalarResult();
	}
}
