<?php

namespace App\Repository;

use App\Entity\ChampsLibre;
use App\Entity\Demande;
use App\Entity\Reception;
use App\Entity\ValeurChampsLibre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method ValeurChampsLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ValeurChampsLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ValeurChampsLibre[]    findAll()
 * @method ValeurChampsLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValeurChampsLibreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ValeurChampsLibre::class);
    }

    public function getByRefArticleAndType($idArticle, $idType)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label, c.id idCL
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.articleReference a
            JOIN v.champLibre c
            JOIN c.type t
            WHERE a.id = :idArticle AND t.id = :idType"
        );
        $query->setParameters([
            "idArticle" => $idArticle,
            "idType" => $idType
        ]);

        return $query->execute();
    }

    public function getValeurAdresse (){
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT DISTINCT vcl.valeur 
            FROM App\Entity\ValeurChampsLibre vcl
            JOIN vcl.champLibre cl
            WHERE cl.id IN (
                SELECT c.id
                FROM App\Entity\ChampsLibre c
                WHERE c.label LIKE 'adresse%'
            )"
        );
        return $query->execute();
    }
    public function getCLsAdresse()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT DISTINCT v.valeur
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.champLibre c
            WHERE c.label LIKE 'adresse%'
            "
        );
        return $query->execute();
    }

    public function findOneByRefArticleANDChampsLibre($refArticleId, $champLibre)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.articleReference a
            WHERE a.id = :refArticle AND v.champLibre = :champLibre"
        );
        $query->setParameters([
            "refArticle" => $refArticleId,
            "champLibre" => $champLibre
        ]);

        //        return $query->getOneOrNullResult();
        $result = $query->execute();
        return $result ? $result[0] : null;
    }

    public function getByRefArticle($idArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.articleReference a
            JOIN v.champLibre c
            WHERE a.id = :idArticle "
        );
        $query->setParameter("idArticle", $idArticle);

        return $query->execute();
    }

    public function getByArticle($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.article a
            JOIN v.champLibre c
            WHERE a.id = :id "
        );
        $query->setParameter("id", $id);

        return $query->execute();
    }

    public function getByArticleAndType($idArticle, $idType)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label, c.id idCL
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.article a
            JOIN v.champLibre c
            JOIN c.type t
            WHERE a.id = :idArticle AND t.id = :idType"
        );
        $query->setParameters([
            "idArticle" => $idArticle,
            "idType" => $idType
        ]);

        return $query->execute();
    }

    public function findOneByArticleANDChampsLibre($idArticle, $idChampLibre)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.article a
            JOIN v.champLibre c
            WHERE a.id = :idArticle AND c.id = :idChampLibre"
        );
        $query->setParameters([
            "idArticle" => $idArticle,
            "idChampLibre" => $idChampLibre
        ]);

//        return $query->getOneOrNullResult();
		$result = $query->execute();
		return $result ? $result[0] : null;
    }

    public function findOneByChampLibreAndArticle($champLibreId, $articleId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.article a
            JOIN v.champLibre c
            WHERE a.id = :articleId AND c.id = :champLibreId"
        );
        $query->setParameters(['champLibreId' => $champLibreId, 'articleId' => $articleId]);

        return $query->getOneOrNullResult();
    }

    public function getByReceptionAndType($reception, $type)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label, c.typage
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.champLibre c
            WHERE v.id IN (:receptions) AND c.type = :type"
        );
        $query->setParameters([
            "receptions" => $reception->getValeurChampsLibre(),
            "type" => $type
        ]);

        return $query->execute();
    }

	/**
	 * @param Reception $reception
	 * @param ChampsLibre $champLibre
	 * @return mixed
	 */
    public function findOneByReceptionAndChampLibre($reception, $champLibre)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampsLibre v
            WHERE v.champLibre = :champLibre AND v.id IN (:receptionvcl)"
        );
        $query->setParameters([
            'receptionvcl' => $reception->getValeurChampsLibre(),
            "champLibre" => $champLibre
        ]);
        return $query->execute();
    }

	/**
	 * @param Demande $demande
	 * @param ChampsLibre $champLibre
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
	public function findOneByDemandeLivraisonAndChampsLibre($demande, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.demandesLivraison d
            WHERE v.champLibre = :champLibre AND v.id IN (:demandeVCL)"
		);
		$query->setParameters([
			'champLibre' => $champLibre->getId(),
			'demandeVCL' => $demande->getValeurChampLibre()
		]);
		return $query->getOneOrNullResult();
	}


	/**
	 * @param Demande $demandeLivraison
	 * @param ChampsLibre $champLibre
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
	public function getValueByDemandeLivraisonAndChampLibre($demandeLivraison, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v.valeur
            FROM App\Entity\ValeurChampsLibre v
            WHERE v.champLibre = :champLibre AND v.id in (:demandeLivraisonVCL)"
		);
		$query->setParameters([
			'demandeLivraisonVCL' => $demandeLivraison->getValeurChampLibre(),
			"champLibre" => $champLibre
		]);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param Demande $demandeLivraison
	 * @return mixed
	 */
	public function getByDemandeLivraison($demandeLivraison)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v.id, v.valeur, c.label, c.typage
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.champLibre c
            WHERE v.id IN (:demandesLivraison) AND c.type = :type"
		);
		$query->setParameters([
			"demandesLivraison" => $demandeLivraison->getValeurChampLibre(),
			"type" => $demandeLivraison->getType()
		]);

		return $query->execute();
	}
}
