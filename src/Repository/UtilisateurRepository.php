<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @method Utilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateur[]    findAll()
 * @method Utilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateurRepository extends EntityRepository implements UserLoaderInterface
{
    private const DtToDbLabels = [
        'Nom d\'utilisateur' => 'username',
        'Email' => 'email',
        'Dropzone' => 'dropzone',
        'Dernière connexion' => 'lastLogin',
        'Rôle' => 'role',
    ];

    public function getIdAndLibelleBySearch($search)
    {
        return $this->createQueryBuilder('u')
            ->select('u.id')
            ->addSelect('u.username as text')
            ->addSelect('d.id as idEmp')
            ->addSelect('d.label as textEmp')
            ->leftJoin('u.dropzone', 'd')
            ->where('u.username LIKE :search')
            ->andWhere('u.status = true')
            ->setParameter('search', '%' . $search . '%')
            ->getQuery()
            ->execute();
    }

    public function removeFromSearch(string $searchField, string $fieldToRemove) {
        $queryBuilder = $this->createQueryBuilder('utilisateur');
        return $queryBuilder
            ->update(Utilisateur::class, 'utilisateur')
            ->set("utilisateur.${searchField}", "JSON_REMOVE(utilisateur.${searchField}, REPLACE(JSON_SEARCH(utilisateur.${searchField}, 'one', '${fieldToRemove}'), '\"', ''))")
            ->where("utilisateur.${searchField} LIKE :searchField")
            ->setParameter('searchField', '%' . $fieldToRemove . '%')
            ->getQuery()
            ->execute();
    }

    /**
     * @param $roleId
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByRoleId($roleId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            JOIN u.role r
            WHERE r.id = :roleId"
        )->setParameter('roleId', $roleId);
        return $query->getSingleScalarResult();
    }

    /**
     * @param $key
     * @return Utilisateur | null
     * @throws NonUniqueResultException
     */
    public function findOneByApiKey($key)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.apiKey = :key
              AND u.status = true"
        )->setParameter('key', $key);

        return $query->getOneOrNullResult();
    }

    public function findByFieldNotNull(string $field) {
        $qb = $this->createQueryBuilder('u');
        return $qb
            ->where(
                $qb->expr()->isNotNull("u.$field")
            )
            ->getQuery()
            ->execute();
    }

    public function findByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Utilisateur', 'a');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];

                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'Dropzone':
                            $qb
                                ->leftJoin('a.dropzone', 'd_order')
                                ->orderBy('d_order.label', $order);
                            break;
                        default:
                            $qb->orderBy('a.' . self::DtToDbLabels[$column], $order);
                    }
                }
            }

            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('a.dropzone', 'd_search')
                        ->andWhere('a.username LIKE :value OR a.email LIKE :value OR d_search.label LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Utilisateur a
           "
        );

        return $query->getSingleScalarResult();
    }

    public function getUsernameBuyersGroupByArrival()
    {
        $queryBuilder = $this->createQueryBuilder('utilisateur')
            ->select('arrival.id AS arrivalId')
            ->addSelect('utilisateur.username')
            ->join('utilisateur.arrivagesAcheteur', 'arrival');

        $result = $queryBuilder
            ->getQuery()
            ->getResult();

        return array_reduce($result, function ($acc, $attachment) {
            $arrivalId = (int)$attachment['arrivalId'];
            if (empty($acc[$arrivalId])) {
                $acc[$arrivalId] = '';
            } else {
                $acc[$arrivalId] .= ' / ';
            }

            $acc[$arrivalId] .= $attachment['username'];
            return $acc;
        }, []);
    }

    public function findByUsernames(array $usernames) {
        if (!empty($usernames)) {
            $result = $this->createQueryBuilder("u")
                ->where("u.email IN (:emails)")
                ->orWhere("u.username IN (:emails)")
                ->setParameter("emails", $usernames);
            return $result->getQuery()->getResult();
        }
        else {
            return [];
        }
    }

    public function getUserMailByIsMailSendRole()
    {
        $result = $this->createQueryBuilder('utilisateur')
            ->select('utilisateur.email AS email')
            ->join('utilisateur.role','role' )
            ->where('role.isMailSendAccountCreation = :isMailSend')
            ->setParameter('isMailSend', true)
            ->getQuery()
            ->execute();

        return array_map(function (array $userMail) {
            return $userMail['email'];
        }, $result);
    }

    // implemented
    public function loadUserByUsername($username) {
        return $this->findOneBy(['email' => $username]);
    }

    public function getUsernameManagersGroupByReference() {
        $result = $this->createQueryBuilder('utilisateur')
            ->select('referencesArticle.id AS referencesArticleId')
            ->addSelect('utilisateur.username')
            ->join('utilisateur.referencesArticle', 'referencesArticle')
            ->getQuery()
            ->getResult();

        return array_reduce($result, function ($acc, $attachment) {
            $referenceArticleId = (int)$attachment['referencesArticleId'];
            if (empty($acc[$referenceArticleId])) {
                $acc[$referenceArticleId] = '';
            } else {
                $acc[$referenceArticleId] .= ', ';
            }

            $acc[$referenceArticleId] .= $attachment['username'];
            return $acc;
        }, []);
    }

}
