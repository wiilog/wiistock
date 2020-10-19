<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Pack;
use App\Entity\TrackingMovement;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200623132732 extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return 'Création/MAJ des colis pour les mouvements traça';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("
            ALTER TABLE colis ADD COLUMN last_drop_id int(11)
        ");
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }

    public function postUp(Schema $schema): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->container->get('doctrine.orm.entity_manager');

        $mouvementTracaRepository = $em->getRepository(TrackingMovement::class);
        $packRepository = $em->getRepository(Pack::class);

//        $lastDropsGroupedByColis = $mouvementTracaRepository->getLastDropsGroupedByColis();

        dump('Starting colis creation/update -> ' . count($lastDropsGroupedByColis));
        $cpt = 0;
        $alreadyTreated = [];
        foreach ($lastDropsGroupedByColis as $drop) {
            if ($drop['colis'] && !in_array($drop['colis'], $alreadyTreated)) {
                $alreadyTreated[] = $drop['colis'];
                $colisIdsByCode = $packRepository->getIdsByCode($drop['colis']);
                if (!empty($colisIdsByCode)) {
                    $packRepository->updateByIds($colisIdsByCode, $drop['id']);
                } else {
                    $packRepository->createFromMvt($drop);
                }
                $cpt++;
                if ($cpt === 500) {
                    $cpt = 0;
                    $em->flush();
                    $em->clear();
                    dump('500 de plus');
                }
            }
        }
        $em->flush();
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }
}
