<?php

namespace App\Repository;

use App\Entity\Action;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Utilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateur[]    findAll()
 * @method Utilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateurRepository extends EntityRepository implements UserLoaderInterface
{

    public function findOneByEmail(string $email) {
        return $this->createQueryBuilder("user")
            ->where("LOWER(user.email) = :email")
            ->setParameter('email', strtolower($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getForSelect(?string $term, array $options = []) {
        $qb = $this->createQueryBuilder("user")
            ->select("user.id AS id")
            ->addSelect("user.username AS text");

        if (isset($options['addDropzone']) && $options['addDropzone']) {
            $qb->addSelect("IF(location_dropzone.id IS NOT NULL, CONCAT('location:', location_dropzone.label),
                                    IF(locationGroup_dropzone.id IS NOT NULL, CONCAT('locationGroup:', locationGroup_dropzone.label), NULL)) AS locationLabel"
                )
                ->addSelect("IF(location_dropzone.id IS NOT NULL, CONCAT('location:', location_dropzone.id),
                                    IF(locationGroup_dropzone.id IS NOT NULL, CONCAT('locationGroup:', locationGroup_dropzone.id), NULL)) AS locationId"
                )
                ->leftJoin('user.locationDropzone', 'location_dropzone')
                ->leftJoin('user.locationGroupDropzone', 'locationGroup_dropzone');
        }

        if (isset($options['delivererOnly']) && $options['delivererOnly']) {
            $qb
                ->addSelect("join_startingHour.hour AS startingHour")
                ->leftJoin('user.transportRoundStartingHour', 'join_startingHour')
                ->andWhere("user.deliverer = true");
        }

        return $qb
            ->andWhere("user.username LIKE :term")
            ->andWhere('user.status = 1')
            ->setParameter("term", "%$term%")
            ->orderBy('user.username','ASC')
            ->getQuery()
            ->getArrayResult();
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

    public function countByRoleId(int $roleId): int {

        return $this->createQueryBuilder("user")
            ->select("COUNT(user)")
            ->join("user.role", "role")
            ->where("role.id = :roleId")
            ->setParameter("roleId", $roleId)
            ->getQuery()
            ->getSingleScalarResult();
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

    public function findByParams(InputBag $params): array
    {
        $qb = $this->createQueryBuilder('user');

        if (!empty($params->all('order'))) {
            $order = $params->all('order')[0]['dir'];

            if (!empty($order)) {
                $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                switch ($column) {
                    case 'dropzone':
                        $qb
                            ->leftJoin('user.locationDropzone', 'order_locationDropzone')
                            ->leftJoin('user.locationGroupDropzone', 'order_locationGroupDropzone')
                            ->orderBy('order_locationDropzone.label', $order)
                            ->addOrderBy('order_locationGroupDropzone.label', $order);
                        break;
                    case 'role':
                        $qb
                            ->leftJoin('user.role', 'order_role')
                            ->orderBy('order_role.label', $order);
                        break;
                    case 'visibilityGroup':
                        $qb
                            ->leftJoin('user.visibilityGroups', 'order_visibility_group')
                            ->orderBy('order_visibility_group.label', $order);
                        break;
                    default:
                        if (property_exists(Utilisateur::class, $column)) {
                            $qb->orderBy("user.$column", $order);
                        }
                        break;
                }
            }
        }

        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
            if (!empty($search)) {
                $exprBuilder = $qb->expr();
                $qb
                    ->leftJoin('user.locationDropzone', 'search_locationDropzone')
                    ->leftJoin('user.locationGroupDropzone', 'search_locationGroupDropzone')
                    ->leftJoin('user.visibilityGroups', 'search_visibility_group')
                    ->andWhere($exprBuilder
                        ->orX(
                            'user.username LIKE :value',
                            'user.email LIKE :value',
                            'search_locationDropzone.label LIKE :value',
                            'search_locationGroupDropzone.label LIKE :value',
                            'search_visibility_group.label LIKE :value'
                        )
                    )->setParameter('value', '%' . $search . '%');
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

    public function loadUserByIdentifier(string $identifier): ?UserInterface {
        return $this->findOneBy(["email" => $identifier]);
    }

}
