<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Alert;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201027131155 extends AbstractMigration {

    public function up(Schema $schema): void {
        if($schema->hasTable("tmp_reference_article")) {
            $references = $this->connection->executeQuery("SELECT ra.id, ra.quantite_disponible, ra.limit_warning, ra.limit_security, ra.date_emergency_triggered
                                                            FROM tmp_reference_article AS ra
                                                            LEFT JOIN alert a on ra.id = a.reference_id
                                                            WHERE a.date < '2020-10-27 00:01'");

            $this->addSql("DELETE FROM alert WHERE date < '2020-10-27 00:01'");

            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));
            $now = $now->format("Y-m-d H:i:s");

            while($reference = $references->fetch()) {
                $available = $reference["quantite_disponible"];
                $warning = $reference["limit_warning"];
                $security = $reference["limit_security"];
                $date = $reference["date_emergency_triggered"];
                $type = null;

                if($security !== null && $security >= $available) {
                    $type = Alert::SECURITY;
                } else if($warning !== null && $warning >= $available) {
                    $type = Alert::WARNING;
                }

                if(isset($type)) {
                    $this->addSql("INSERT INTO alert(reference_id, type, date) VALUES(?, ?, ?)", [
                        $reference["id"],
                        $type,
                        $date ?? $now
                    ]);
                }
            }
        } else {
            $this->addSql("TRUNCATE TABLE alert");
            $this->addSql("UPDATE reference_article SET limit_warning = NULL, limit_security = NULL");
        }
    }

}
