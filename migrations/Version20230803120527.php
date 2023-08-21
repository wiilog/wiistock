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
final class Version20230803120527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // find the first MailerServer in the database
        $mailerServer = $this->connection->fetchAssociative('SELECT * FROM mailer_server LIMIT 1');

        // MailerServer parameters
        $mailerServerParameters = [
            Setting::MAILER_URL => $mailerServer['smtp'],
            Setting::MAILER_PORT => MailerService::PORT_SSL,
            Setting::MAILER_USER => $mailerServer['user'],
            Setting::MAILER_PASSWORD => $mailerServer['password'],
            Setting::MAILER_PROTOCOL => MailerService::PROTOCOL_SSL,
            Setting::MAILER_SENDER_NAME => $mailerServer['sender_name'],
            Setting::MAILER_SENDER_MAIL => $mailerServer['sender_mail'],
        ];

        // insert the MailerServer parameters into the settings table
        foreach ($mailerServerParameters as $key => $value) {
            $this->addSql('INSERT INTO setting (label, value) VALUES (:label, :value)', [
                'label' => $key,
                'value' => $value,
            ]);
        }

    }

    public function down(Schema $schema): void
    {
    }
}
