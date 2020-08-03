<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200803081304 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add pack id in mouvement_traca, set existing Data';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE litige_colis CHANGE colis_id pack_id INT');
        $this->addSql('ALTER TABLE litige_colis RENAME TO litige_pack;');
        $this->addSql('ALTER TABLE colis RENAME TO pack;');
        $this->addSql('ALTER TABLE acheminements CHANGE colis packs JSON');

        $this->addSql('ALTER TABLE mouvement_traca ADD pack_id INT');

        $allPacks = 'SELECT pack.id, pack.code FROM pack';

        foreach ($allPacks as $index => $pack) {
            if ($index % 500 === 0) {
                dump('500 de plus!');
            }
            $packId = $pack['id'];
            $packCode = $pack['code'];
            $this
                ->addSql("UPDATE mouvement_traca SET pack_id = ${packId} WHERE mouvement_traca.colis = '${packCode}'");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }
}
