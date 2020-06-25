<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Demande;
use App\Entity\Litige;
use App\Entity\ValeurChampLibre;
use App\Repository\DemandeRepository;
use App\Repository\LitigeRepository;
use App\Repository\ValeurChampLibreRepository;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineExtensions\Query\Mysql\DateFormat;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200624131135 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE litige ADD numero_litige VARCHAR(64) DEFAULT NULL');

        $litiges = $this->connection
            ->executeQuery('SELECT id, creation_date FROM litige')
            ->fetchAll();

        $litigesInReception = array_map(function ($row) {
            return $row['litige_id'];
        }, $this->connection
            ->executeQuery('SELECT litige_id FROM litige_article')
            ->fetchAll());

        $daysCounter = [];

        foreach ($litiges as $litige) {

            $creationDate = DateTime::createFromFormat('Y-m-d H:i:s', $litige['creation_date']);
            $dateStr = $creationDate->format('ymd');

            if (in_array($litige['id'], $litigesInReception)) {
                $prefix = 'LR';
            } else {
                $prefix = 'LA';
            }
            $dayCounterKey = $prefix . '-' . $dateStr;

            if (!isset($daysCounter[$dayCounterKey])) {
                $daysCounter[$dayCounterKey] = 0;
            }

            $daysCounter[$dayCounterKey]++;
            $suffix = '';
            if ($daysCounter[$dayCounterKey] < 10) {
                $suffix = '000';
            } else if ($daysCounter[$dayCounterKey] < 100) {
                $suffix = '00';
            } else if ($daysCounter[$dayCounterKey] < 1000) {
                $suffix = '0';
            }
            $id = $litige['id'];
            $numeroLitige = $prefix . $dateStr . $suffix . $daysCounter[$dayCounterKey];
            $sqlNumeroLitige = ("UPDATE litige SET numero_litige = '$numeroLitige' WHERE litige.id = '$id'");
            $this->addSql($sqlNumeroLitige);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE litige DROP numero_litige');
    }
}
