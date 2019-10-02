<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ChampLibre;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Reception;
use App\Entity\ValeurChampLibre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method ValeurChampLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ValeurChampLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ValeurChampLibre[]    findAll()
 * @method ValeurChampLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValeurChampLibreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ValeurChampLibre::class);
    }

    public function getByRefArticleAndType($idArticle, $idType)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label, c.id idCL
            FROM App\Entity\ValeurChampLibre v
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
            FROM App\Entity\ValeurChampLibre vcl
            JOIN vcl.champLibre cl
            WHERE cl.id IN (
                SELECT c.id
                FROM App\Entity\ChampLibre c
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
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            WHERE c.label LIKE 'adresse%'
            "
        );
        return $query->execute();
    }

    /**
     * @param int $refArticleId
     * @param ChampLibre $champLibre
     * @return ValeurChampLibre|null
     */
    public function findOneByRefArticleAndChampLibre($refArticleId, $champLibre)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampLibre v
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
            FROM App\Entity\ValeurChampLibre v
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
            FROM App\Entity\ValeurChampLibre v
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
            FROM App\Entity\ValeurChampLibre v
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

	/**
	 * @param int|ChampLibre $champLibre
	 * @param int|Article $article
	 * @return ValeurChampLibre|null
	 */
	public function findOneByArticleAndChampLibre($article, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v
            FROM App\Entity\ValeurChampLibre v
            JOIN v.article a
            JOIN v.champLibre c
            WHERE a.id = :article AND c.id = :champLibre"
		);
		$query->setParameters([
			'champLibre' => $champLibre,
			'article' => $article
		]);

//		return $query->getOneOrNullResult();
		$result = $query->execute();
		return $result ? $result[0] : null;
	}
	/**
	 * @param Reception $reception
	 * @param $type
	 * @return mixed
	 */
    public function getByReceptionAndType($reception, $type)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label, c.typage
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            WHERE v.id IN (:receptions) AND c.type = :type"
        );
        $query->setParameters([
            "receptions" => $reception->getValeurChampLibre(),
            "type" => $type
        ]);

        return $query->execute();
    }

	/**
	 * @param Reception $reception
	 * @param ChampLibre $champLibre
	 * @return ValeurChampLibre|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByReceptionAndChampLibre($reception, $champLibre)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampLibre v
            WHERE v.champLibre = :champLibre AND v.id IN (:receptionvcl)"
        );
        $query->setParameters([
            'receptionvcl' => $reception->getValeurChampLibre(),
            "champLibre" => $champLibre
        ]);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param Demande $demande
	 * @param int|ChampLibre $champLibre
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
	public function findOneByDemandeLivraisonAndChampLibre($demande, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v
            FROM App\Entity\ValeurChampLibre v
            JOIN v.demandesLivraison d
            WHERE v.champLibre = :champLibre AND v.id IN (:demandeVCL)"
		);
		$query->setParameters([
			'champLibre' => $champLibre,
			'demandeVCL' => $demande->getValeurChampLibre()
		]);
		return $query->getOneOrNullResult();
	}

	/**
	 * @param Collecte $demandeCollecte
	 * @param int|ChampLibre $champLibre
	 * @return ValeurChampLibre|null
	 * @throws NonUniqueResultException
	 */
	public function findOneByDemandeCollecteAndChampLibre($demandeCollecte, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT v
            FROM App\Entity\ValeurChampLibre v
            JOIN v.demandesCollecte c
            WHERE v.champLibre = :champLibre AND v.id IN (:collecteVCL)"
		);
		$query->setParameters([
			'champLibre' => $champLibre,
			'collecteVCL' => $demandeCollecte->getValeurChampLibre()
		]);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param Demande $demandeLivraison
	 * @param ChampLibre $champLibre
	 * @return mixed
	 * @throws NonUniqueResultException
	 */
	public function getValueByDemandeLivraisonAndChampLibre($demandeLivraison, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            WHERE v.champLibre = :champLibre AND v.id in (:demandeLivraisonVCL)"
		);
		$query->setParameters([
			'demandeLivraisonVCL' => $demandeLivraison->getValeurChampLibre(),
			"champLibre" => $champLibre
		]);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param Collecte $demandeCollecte
	 * @param ChampLibre $champLibre
	 * @return string|null
	 * @throws NonUniqueResultException
	 */
	public function getValueByDemandeCollecteAndChampLibre($demandeCollecte, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL  */
			"SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            WHERE v.champLibre = :champLibre AND v.id in (:demandeCollecteVCL)"
		);
		$query->setParameters([
			'demandeCollecteVCL' => $demandeCollecte->getValeurChampLibre(),
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
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            WHERE v.id IN (:demandesLivraison) AND c.type = :type"
		);
		$query->setParameters([
			"demandesLivraison" => $demandeLivraison->getValeurChampLibre(),
			"type" => $demandeLivraison->getType()
		]);

		return $query->execute();
	}

	/**
	 * @param Collecte $demandeCollecte
	 * @return mixed
	 */
	public function getByDemandeCollecte($demandeCollecte)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT v.id, v.valeur, c.label, c.typage
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            WHERE v.id IN (:demandesCollecte) AND c.type = :type"
		);
		$query->setParameters([
			"demandesCollecte" => $demandeCollecte->getValeurChampLibre(),
			"type" => $demandeCollecte->getType()
		]);

		return $query->execute();
	}

	public function getValeurByCL($champLibre){
	    $em = $this->getEntityManager();
	    $query = $em->createQuery(
	        "SELECT v.valeur
	        FROM App\Entity\ValeurChampLibre v
	        JOIN v.champLibre c
	        WHERE c.id =:champLibre"
        )->setParameter('champLibre', $champLibre);
	    return $query->execute();
    }

	/**
	 * @param int $champLibreId
	 * @return ValeurChampLibre[]
	 */
    public function findByCL($champLibreId){
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
	        FROM App\Entity\ValeurChampLibre v
	        JOIN v.champLibre c
	        WHERE c.id =:champLibre"
        )->setParameter('champLibre', $champLibreId);
        return $query->execute();
    }
}
