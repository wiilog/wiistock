<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\Role;
use App\Entity\Statut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241119100647 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $em = $this->container->get('doctrine.orm.entity_manager');

        // get valid roles
        $roles = $em->getRepository(Role::class)->createQueryBuilder('r')
            ->where('r.label NOT LIKE :roleLabel')
            ->setParameter('roleLabel', '%aucun accès%')
            ->getQuery()
            ->getResult();

        // Get the ID of the "Dispatch" category
        $categorieStatutDispatchId = $em->getRepository(CategorieStatut::class)->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.nom = :categorieStatutDispatch')
            ->setParameter('categorieStatutDispatch', CategorieStatut::DISPATCH)
            ->getQuery()
            ->getSingleScalarResult();

        // Get statuts in "Brouillon" state and corresponding to the "Dispatch" category
        $statuts = $em->getRepository(Statut::class)->createQueryBuilder('s')
            ->where('s.state = :state')
            ->andWhere('s.categorie = :categorie')
            ->setParameter('state', Statut::DRAFT)
            ->setParameter('categorie', $categorieStatutDispatchId)
            ->getQuery()
            ->getResult();

        // Add all roles to each statut
        foreach ($statuts as $statut) {
            foreach ($roles as $role) {
                $statut->addStatusCreationAuthorization($role);
            }
            $em->persist($statut);
        }

        $em->flush();

    }




    public function down(Schema $schema): void
    {

    }
}
