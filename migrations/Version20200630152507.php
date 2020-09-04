<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\UserService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200630152507 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE utilisateur ADD mobile_login_key VARCHAR(255) DEFAULT NULL');


        $userIdsResult = $this->connection
            ->executeQuery('SELECT utilisateur.id AS user_id FROM utilisateur')
            ->fetchAll();

        $alreadyGeneratedKeys = [];

        foreach ($userIdsResult as $row) {
            $userId = $row['user_id'];
            do {
                $mobileLoginKey = UserService::CreateMobileLoginKey();
            }
            while(in_array($mobileLoginKey, $alreadyGeneratedKeys));
            $alreadyGeneratedKeys[] = $mobileLoginKey;
            $this->addSql("UPDATE utilisateur SET mobile_login_key = '$mobileLoginKey' WHERE utilisateur.id = $userId");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE utilisateur DROP mobile_login_key');
    }
}
