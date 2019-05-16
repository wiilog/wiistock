<?php

namespace App\Repository;

use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferenceArticle[]    findAll()
 * @method ReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferenceArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReferenceArticle::class);
    }

    public function getIdAndLibelle()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.libelle 
            FROM App\Entity\ReferenceArticle r
            "
        );

        return $query->execute();
    }

    public function getChampFixeById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.libelle, r.reference, r.commentaire, r.quantite_stock, r.type_quantite, r.statut, r.type
            FROM App\Entity\ReferenceArticle r
            WHERE r.id = :id 
            "
        )->setParameter('id', $id);

        return $query->execute();
    }

    public function getByReference($reference)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r
            FROM App\Entity\ReferenceArticle r
            WHERE r.reference = :reference
            "
        )->setParameter('reference', $reference);

        return $query->getOneOrNullResult();
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT r.id, r.reference as text
          FROM App\Entity\ReferenceArticle r
          WHERE r.reference LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function getQuantiteStockById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r. quantiteStock
            FROM App\Entity\ReferenceArticle r
            WHERE r.id = $id
           "
        );
        return $query->getSingleScalarResult();
    }

    public function findByFiltersAndParams($filters, $params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $index = 0;

        $subQueries = [];

        // fait le lien entre intitulé champs dans datatable/filtres côté front
        // et le nom des attributs de l'entité ReferenceArticle (+ typage)
        $linkChampLibreLabelToField = [
            'Libellé' => ['field' => 'libelle', 'typage' => 'text'],
            'Référence' => ['field' => 'reference', 'typage' => 'text'],
            'Type' => ['field' => 'type_id', 'typage' => 'list'],
            'Quantité' => ['field' => 'quantiteStock', 'typage' => 'number'],
        ];
        //TODO trouver + dynamique

        $qb
            ->select('ra')
            ->distinct()
            ->from('App\Entity\ReferenceArticle', 'ra')
            ->leftJoin('ra.valeurChampsLibres', 'vcl');

        foreach ($filters as $filter) {
            $index++;

            // cas champ fixe
            if ($label = $filter['champFixe']) {
                $array = $linkChampLibreLabelToField[$label];
                $field = $array['field'];
                $typage = $array['typage'];

                switch ($typage) {
                    case 'text':
                        $qb
                            ->andWhere("ra." . $field . " LIKE :value")
                            ->setParameter('value', '%' . $filter['value'] . '%');
                        break;
                    case 'number':
                        $qb->andWhere("ra." . $field . " = " . $filter['value']);
                        break;
                    case 'list':
                        // cas particulier du type (pas besoin de généraliser pour l'instant, voir selon besoins)
                        $qb
                            ->leftJoin('ra.type', 't')
                            ->andWhere('t.label = :typeLabel')
                            ->setParameter('typeLabel', $filter['value']);
                        break;
                }

                // cas champ libre
            } else if ($filter['champLibre']) {
                $qbSub = $em->createQueryBuilder();
                $qbSub
                    ->select('ra' . $index . '.id')
                    ->from('App\Entity\ReferenceArticle', 'ra' . $index)
                    ->leftJoin('ra' . $index . '.valeurChampsLibres', 'vcl' . $index);

                switch ($filter['typage']) {
                    case 'booleen':
                        $value = $filter['value'] == 1 ? '1' : '0';
                        $qbSub
                            ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                            ->andWhere('vcl' . $index . '.valeur = ' . $value);
                        break;
                    case 'text':
                        $qbSub
                            ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                            ->andWhere('vcl' . $index . '.valeur LIKE :value')
                            ->setParameter('value', '%' . $filter['value'] . '%');
                        break;
                    case 'number':
                    case 'list':
                        $qbSub
                            ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                            ->andWhere('vcl' . $index . '.valeur = :value')
                            ->setParameter('value', $filter['value']);
                        break;
                    case 'date':
                        $date = explode('-', $filter['value']);
                        $formattedDated = $date[2] . '/' . $date[1] . '/' . $date[0];
                        $qbSub
                            ->andWhere('vcl' . $index . '.champLibre = ' . $filter['champLibre'])
                            ->andWhere('vcl' . $index . ".valeur = '" . $formattedDated . "'");
                        break;
                }
                $subQueries[] = $qbSub->getQuery()->getResult();
            }
        }

        foreach ($subQueries as $subQuery) {
            $ids = [];
            foreach ($subQuery as $idArray) {
                $ids[] = $idArray['id'];
            }
            if (empty($ids)) $ids = 0;
            $qb->andWhere($qb->expr()->in('ra.id', $ids));
        }

        $countQuery = count($qb->getQuery()->getResult());

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('ra.libelle LIKE :value OR ra.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $query = $qb->getQuery();

        return ['data' => $query->getResult(), 'count' => $countQuery];
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function setTypeIdNull($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "UPDATE App\Entity\ReferenceArticle ra
            SET ra.type = null 
            WHERE ra.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function getIdAndLabelByFournisseur($fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT DISTINCT(ra.id) as id, ra.libelle, IDENTITY(af.fournisseur) as fournisseur
            FROM App\Entity\ArticleFournisseur af
            JOIN af.referenceArticle ra
            WHERE af.fournisseur = :fournisseurId
            "
        )->setParameter('fournisseurId', $fournisseurId);

        return $query->execute();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
           "
        );

        return $query->getSingleScalarResult();
    }
}
