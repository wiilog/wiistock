<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200525125049 extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'On supprime les mauvaises valeur cl';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }

    public function postUp(Schema $schema): void
    {
//        $em = $this->container->get('doctrine.orm.entity_manager');
//        /** @var DemandeRepository $demandeRepository */
//        $demandeRepository = $em->getRepository(Demande::class);
//        /** @var ValeurChampLibreRepository $valeurChampLibreRepository */
//        $valeurChampLibreRepository = $em->getRepository(ValeurChampLibre::class);
//        $demandes = $demandeRepository->findAll();
//        foreach ($demandes as $demande) {
//            $vclToDelete = $demandeRepository->findByDemandeWhereTypeIsDifferent($demande);
//            $valeurChampLibreRepository->deleteIn($vclToDelete);
//        }
//        $em->flush();
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }
}
