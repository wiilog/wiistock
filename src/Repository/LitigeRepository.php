<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Litige|null find($id, $lockMode = null, $lockVersion = null)
 * @method Litige|null findOneBy(array $criteria, array $orderBy = null)
 * @method Litige[]    findAll()
 * @method Litige[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Litige::class);
    }

    public function findByStatutLabel($statutLabel)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT l
			FROM App\Entity\Litige l
			JOIN l.statut s
			WHERE s.nom = :statutLabel"
		)->setParameter('statutLabel', $statutLabel);

		return $query->execute();
	}

	public function getAllWithArrivageData()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT l.id, l.creationDate, l.updateDate,
			tr.label as carrier, f.nom as provider, a.numeroArrivage, t.label as type, a.id as arrivageId, s.nom status
			FROM App\Entity\Litige l
			LEFT JOIN l.colis c
			JOIN l.type t
			LEFT JOIN c.arrivage a
			LEFT JOIN a.fournisseur f
			LEFT JOIN a.chauffeur ch
			LEFT JOIN a.transporteur tr
			LEFT JOIN l.status s
			");

		return $query->execute();
	}

	/**
	 * @param int $litigeId
	 * @return LitigeHistoric
	 */
	public function getLastHistoricByLitigeId($litigeId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT lh.date, lh.comment
			FROM App\Entity\LitigeHistoric lh
			WHERE lh.litige = :litige
			ORDER BY lh.date DESC
			")
		->setParameter('litige', $litigeId);

		$result = $query->execute();

		return $result ? $result[0] : null;
	}

	/**
	 * @param string $dateMin
	 * @param string $dateMax
	 * @return Litige[]|null
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT l
            FROM App\Entity\Litige l
            WHERE l.creationDate BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}

    public function getByArrivage($arrivage)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT DISTINCT l
            FROM App\Entity\Litige l
            INNER JOIN l.colis c
            INNER JOIN c.arrivage a
            WHERE a.id = :arrivage'
        )->setParameter('arrivage', $arrivage);

        return $query->execute();
    }
}
