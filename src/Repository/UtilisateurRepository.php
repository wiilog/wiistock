<?php

namespace App\Repository;

use App\Entity\Action;
use App\Entity\Dispute;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
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
            ->andWhere("user.kioskUser = 0")
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
            ->andWhere("user.kioskUser = 0")
            ->setParameter("term", "%$term%")
            ->orderBy('user.username','ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countByRoleId(int $roleId): int {

        return $this->createQueryBuilder("user")
            ->select("COUNT(user)")
            ->join("user.role", "role")
            ->where("role.id = :roleId")
            ->andWhere("user.kioskUser = 0")
            ->setParameter("roleId", $roleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByApiKey(string $key): Utilisateur|null
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.apiKey = :key
              AND u.status = true
              AND u.kioskUser = 0"
        )->setParameter('key', $key);

        return $query->getOneOrNullResult();
    }

    public function findByParams(InputBag $params): array
    {
        $qb = $this->createQueryBuilder('user')
            ->groupBy('user')
            ->where("user.kioskUser = 0");

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

        $filtered = QueryBuilderHelper::count($qb, 'user');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        return [
            'data' => $qb->getQuery()->getResult(),
            'total' => $this->count([]),
            'filtered' => $filtered,
        ];
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
            ->andWhere("utilisateur.kioskUser = 0")
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
            ->andWhere("utilisateur.kioskUser = 0")
            ->setParameter('actionLabel', Action::REFERENCE_VALIDATOR)
            ->getQuery()
            ->execute();

        return array_map(function (array $userMail) {
            return $userMail['email'];
        }, $result);
    }

    public function iterateAll(): iterable {
        return $this->createQueryBuilder('user')
            ->where("user.kioskUser = 0")
            ->getQuery()
            ->toIterable();
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface {
        return $this->findOneBy(["email" => $identifier]);
    }

    public function getDisputeBuyers(Dispute $dispute): array {
        return $this->createQueryBuilder('buyer')
            ->distinct()
            ->join('buyer.arrivagesAcheteur', 'arrival')
            ->join('arrival.packs', 'pack')
            ->join('pack.disputes', 'dispute')
            ->andWhere('dispute = :dispute')
            ->setParameter('dispute', $dispute)
            ->getQuery()
            ->getResult();
    }

    // return one user
    public function getKioskUser(): ?Utilisateur {
        $result = $this->createQueryBuilder("user")
            ->where("user.kioskUser = 1");

        return $result->getQuery()->getOneOrNullResult();
    }

    public function getAll(): array {
        return $this->createQueryBuilder("user")
            ->select("user.id AS id")
            ->addSelect("user.username AS username")
            ->andWhere("user.status = 1")
            ->getQuery()
            ->getResult();
    }
}
