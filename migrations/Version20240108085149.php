<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use App\Service\MailerService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240108085149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'change the value of the setting "MAILER_PROTOCOL" from a string to an boolean ( true if TLS) and rename it "MAILER_IS_TLS_PROTOCOL"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE setting SET value = 1 WHERE label = "MAILER_PROTOCOL" AND value = "TLS"');
        $this->addSql('UPDATE setting SET value = 0 WHERE label = "MAILER_PROTOCOL" AND value != "TLS"');

        $this->addSql('UPDATE setting SET label = :newLabel WHERE label = :oldLabel', [
            'oldLabel' => 'MAILER_PROTOCOL',
            'newLabel' => Setting::MAILER_IS_TLS_PROTOCOL,
        ]);
    }
}
