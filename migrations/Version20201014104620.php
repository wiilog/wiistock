<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Alert;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201014104620 extends AbstractMigration {

    public function up(Schema $schema): void {
        $now = new DateTime("now", new DateTimeZone('Europe/Paris'));
        $now = $now->format("Y-m-d H:i:s");

        $this->addSql("CREATE TABLE alert (
                id INT AUTO_INCREMENT NOT NULL,
                reference_id INT DEFAULT NULL,
                article_id INT DEFAULT NULL,
                type INT NOT NULL,
                date DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");

        $references = $this->connection->executeQuery("SELECT * FROM reference_article");
        while($reference = $references->fetch()) {
            $available = $reference["quantite_disponible"];
            $warning = $reference["limit_warning"];
            $security = $reference["limit_security"];

            if($security !== null && $security >= $available) {
                $type = Alert::SECURITY;
            } else if($warning !== null && $warning >= $available) {
                $type = Alert::WARNING;
            }

            if(isset($type)) {
                $this->addSql("INSERT INTO alert(reference_id, type, date) VALUES(?, ?, ?)", [
                    $reference["id"],
                    Alert::WARNING,
                    $now
                ]);
            }
        }
    }

}
