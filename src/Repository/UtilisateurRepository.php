<?php

namespace App\Repository;

use App\Entity\Action;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\HttpFoundation\InputBag;

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
        'Actif' => 'status',
    ];

    public function getForSelect(?string $term) {
        return $this->createQueryBuilder("user")
            ->select("user.id AS id, user.username AS text")
            ->andWhere("user.username LIKE :term")
            ->andWhere('user.status = true')
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

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
            ->orderBy('u.username','ASC')
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

    public function findByParams(InputBag $params)
    {
        $qb = $this->createQueryBuilder('user');

        if (!empty($params->get('order'))) {
            $order = $params->get('order')[0]['dir'];

            if (!empty($order)) {
                $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                switch ($column) {
                    case 'Dropzone':
                        $qb
                            ->leftJoin('user.dropzone', 'd_order')
                            ->orderBy('d_order.label', $order);
                        break;
                    case 'role':
                        $qb
                            ->leftJoin('user.role', 'a_order')
                            ->orderBy('a_order.label', $order);
                        break;
                    case 'visibilityGroup':
                        $qb
                            ->leftJoin('user.visibilityGroups', 'order_visibility_group')
                            ->orderBy('order_visibility_group.label', $order);
                        break;
                    default:
                        $dbColumn = self::DtToDbLabels[$column] ?? $column;
                        if (property_exists(Utilisateur::class, $dbColumn)) {
                            $qb->orderBy("user.$dbColumn", $order);
                        }
                        break;
                }
            }
        }

        if (!empty($params->get('search'))) {
            $search = $params->get('search')['value'];
            if (!empty($search)) {
                $qb
                    ->leftJoin('user.dropzone', 'd_search')
                    ->leftJoin('user.visibilityGroups', 'search_visibility_group')
                    ->andWhere(
                        'user.username LIKE :value'
                        . ' OR user.email LIKE :value'
                        . ' OR d_search.label LIKE :value'
                        . ' OR search_visibility_group.label LIKE :value'
                    )
                    ->setParameter('value', '%' . $search . '%');
            }
        }

        $filtered = QueryCounter::count($qb, 'user');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'total' => $this->count([]),
            'filtered' => $filtered,
        ];
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

    public function getUserMailByReferenceValidatorAction()
    {
        $result = $this->createQueryBuilder('utilisateur')
            ->select('utilisateur.email AS email')
            ->join('utilisateur.role','role' )
            ->join('role.actions','actions' )
            ->where('actions.label = :actionLabel')
            ->setParameter('actionLabel', Action::REFERENCE_VALIDATOR)
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

    public function iterateAll(): iterable {
        return $this->createQueryBuilder('user')
            ->getQuery()
            ->toIterable();
    }

    public function findWithEmptyVisibleColumns() {
        $qb = $this->createQueryBuilder('user');
        $exprBuilder = $qb->expr();

        return $qb->where($exprBuilder->orX(
            "JSON_EXTRACT(user.visibleColumns, '$.arrival') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.article') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.dispute') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.dispatch') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.reception') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.reference') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.deliveryRequest') IS NULL",
            "JSON_EXTRACT(user.visibleColumns, '$.trackingMovement') IS NULL",
        ))
            ->getQuery()
            ->getResult();
    }

}
