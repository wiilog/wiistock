<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230906134809 extends AbstractMigration
{

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE attachment SET full_path = REPLACE(full_path, 'attachements', 'attachments') WHERE full_path IS NOT NULL");
        $this->addSql("UPDATE setting SET value = REPLACE(value, 'attachements', 'attachments') WHERE value IS NOT NULL");
        $this->addSql("UPDATE language SET flag = REPLACE(flag, 'attachements', 'attachments')");

        $projectDir = getcwd();
        $currentAttachmentsDirectory = "{$projectDir}/public/uploads/attachements";
        $newAttachmentsDirectory = "{$projectDir}/public/uploads/attachments";

        if (file_exists($currentAttachmentsDirectory)) {
            rename($currentAttachmentsDirectory, $newAttachmentsDirectory);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
