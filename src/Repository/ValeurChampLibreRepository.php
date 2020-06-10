<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\ChampLibre;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method ValeurChampLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ValeurChampLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ValeurChampLibre[]    findAll()
 * @method ValeurChampLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValeurChampLibreRepository extends EntityRepository
{

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

    /**
     * @param int $refArticleId
     * @param ChampLibre $champLibre
     * @return ValeurChampLibre|null
     */
    public function findByDemandesAndChampLibres($demandes, $champLibres)
    {
        $em = $this->getEntityManager();
        $query = $em
            ->createQuery(
                "SELECT valeurCL, 
                        reference.id AS referenceId
                FROM App\Entity\ValeurChampLibre valeurCL
                JOIN valeurCL.champLibre champLibre
                JOIN valeurCL.articleReference reference
                JOIN reference.ligneArticles ligneArticle
                JOIN ligneArticle.demande demande
                WHERE demande.id IN (:demandesId) AND champLibre.id IN (:champLibresId)"
            )
            ->setParameter(
                'demandesId',
                array_map( function (Demande $demande) {
                    return $demande->getId();
                }, $demandes),
                Connection::PARAM_STR_ARRAY
            )
            ->setParameter(
                'champLibresId',
                array_map(function (ChampLibre $champLibre) {
                    return $champLibre->getId();
                }, $champLibres),
                Connection::PARAM_STR_ARRAY
            );
        $result = $query->execute();

        return array_reduce($result, function(array $carry, $current) {
            $valeurChampLibre =  $current[0];
            $referenceId = $current['referenceId'];

            if (!isset($carry[$referenceId])) {
                $carry[$referenceId] = [];
            }

            $carry[$referenceId][] = $valeurChampLibre;
            return $carry;
        }, []);
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
	 * @param Arrivage $arrivage
	 * @param ChampLibre $champLibre
	 * @return ValeurChampLibre|null
	 * @throws NonUniqueResultException
	 */
	public function findOneByArrivageAndChampLibre($arrivage, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT v
            FROM App\Entity\ValeurChampLibre v
            WHERE v.champLibre = :champLibre AND v.id IN (:arrivageVCL)"
		);
		$query->setParameters([
			'arrivageVCL' => $arrivage->getValeurChampLibre(),
			"champLibre" => $champLibre
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
	 * @param Arrivage $arrivage
	 * @param ChampLibre $champLibre
	 * @return string|null
	 * @throws NonUniqueResultException
	 */
	public function getValueByArrivageAndChampLibre($arrivage, $champLibre)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL  */
			"SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            WHERE v.champLibre = :champLibre AND v.id in (:arrivageVCL)"
		);
		$query->setParameters([
			'arrivageVCL' => $arrivage->getValeurChampLibre(),
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

	/**
	 * @param ReferenceArticle $ref
	 * @return array
	 * @throws DBALException
	 */
    public function getLabelCLAndValueByRefArticle(ReferenceArticle $ref)
	{
		$em = $this->getEntityManager()->getConnection();
		$sql =
		/** @lang SQL */
		"SELECT cl.label, temp.valeur, cl.typage
		FROM champ_libre cl
			LEFT JOIN (
				SELECT vcl.champ_libre_id clid ,vcl.valeur as valeur
				FROM valeur_champ_libre vcl
					INNER JOIN valeur_champ_libre_reference_article vclra ON vclra.valeur_champ_libre_id = vcl.id
					WHERE vclra.reference_article_id = :refId
			) temp ON temp.clid = cl.id
			INNER JOIN categorie_cl ccl ON ccl.id = cl.categorie_cl_id
			WHERE ccl.label = :categoryCL
		";
		$prepare = $em->prepare($sql);
		$prepare->bindValue('refId', $ref->getId());
		$prepare->bindValue('categoryCL', CategorieCL::REFERENCE_ARTICLE);
		$prepare->execute();

		return $prepare->fetchAll();
	}

	/**
	 * @param Article $article
	 * @return array
	 * @throws DBALException
	 */
    public function getLabelCLAndValueByArticle(Article $article)
    {
        $em = $this->getEntityManager()->getConnection();
        $sql =
            /** @lang SQL */
            "SELECT cl.label, temp.valeur
		FROM champ_libre cl
			LEFT JOIN (
				SELECT vcl.champ_libre_id clid ,vcl.valeur as valeur
				FROM valeur_champ_libre vcl
					INNER JOIN valeur_champ_libre_article vclra ON vclra.valeur_champ_libre_id = vcl.id
					WHERE vclra.article_id = :artId
			) temp ON temp.clid = cl.id
			INNER JOIN categorie_cl ccl ON ccl.id = cl.categorie_cl_id
			WHERE ccl.label = :categoryCL
		";
        $prepare = $em->prepare($sql);
        $prepare->bindValue('artId', $article->getId());
        $prepare->bindValue('categoryCL', CategorieCL::ARTICLE);
        $prepare->execute();

        return $prepare->fetchAll();
    }

    public function deleteIn(array $ids) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "DELETE
            FROM App\Entity\ValeurChampLibre v
            WHERE v.id IN (:ids)"
        );
        $query->setParameter('ids', $ids);
        return $query->execute();
    }
}
