<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use DateTime;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200819145755 extends AbstractMigration
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE acheminements ADD numero_acheminement VARCHAR(64) DEFAULT NULL');

        $acheminements = $this->connection
            ->executeQuery('SELECT id, date FROM acheminements')
            ->fetchAll();

        $daysCounter = [];

        foreach ($acheminements as $acheminement) {
            $creationDate = DateTime::createFromFormat('Y-m-d H:i:s', $acheminement['date']);
            $dateStr = $creationDate->format('Ymd');

            $dayCounterKey = 'A' . '-' . $dateStr;

            if (!isset($daysCounter[$dayCounterKey])) {
                $daysCounter[$dayCounterKey] = 0;
            }

            $suffix = '';
            $daysCounter[$dayCounterKey]++;
            if ($daysCounter[$dayCounterKey] < 10) {
                $suffix = '0';
            } else if ($daysCounter[$dayCounterKey] < 100) {
                $suffix = '';
            }

            $id = $acheminement['id'];
            $numeroAcheminement = 'A-'. $dateStr . $suffix . $daysCounter[$dayCounterKey];
            $sqlNumeroAcheminement = ("UPDATE acheminements SET numero_acheminement = '$numeroAcheminement' WHERE acheminements.id = '$id'");
            $this->addSql($sqlNumeroAcheminement);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE acheminements DROP numero_acheminement');
    }
}

