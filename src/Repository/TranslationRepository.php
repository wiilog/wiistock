<?php

namespace App\Repository;

use App\Entity\Translation;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationRepository extends EntityRepository
{

    public function countUpdatedRows(): int {
        return $this->createQueryBuilder("t")
            ->select("COUNT(t)")
            ->where("t.updated = 1")
            ->getQuery()
            ->getSingleScalarResult();
	}

	public function findAllObjects() {
        $queryBuilder = $this->createQueryBuilder('translation');
        return $queryBuilder
            ->select('translation.menu')
            ->addSelect('translation.label')
            ->addSelect('translation.translation')
            ->getQuery()
            ->execute();
    }

	public function clearUpdate() {
		return $this->getEntityManager()
            ->createQuery("UPDATE App\Entity\Translation t SET t.updated = 0")
            ->execute();
	}

	/**
	 * @return string[]
	 */
	public function getMenus()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT DISTINCT (t.menu)
		FROM App\Entity\Translation t");

		return $query->getScalarResult();
	}

	/**
	 * @param string $menu
	 * @param string $label
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
	public function getTranslationByMenuAndLabel($menu, $label)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT t.translation
			FROM App\Entity\Translation t
			WHERE t.menu = :menu AND t.label = :label"
		)->setParameters([
			'menu' => $menu,
			'label' => $label
		]);

		$result = $query->getOneOrNullResult();
		return $result ? $result['translation'] : null;
	}

}
