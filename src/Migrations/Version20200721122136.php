<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Controller\ChampLibreController;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Validator\Constraints\Json;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200721122136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
//        // this up() migration is auto-generated, please modify it to your needs
//        $this
//            ->addSql('ALTER TABLE reception ADD free_fields JSON DEFAULT NULL;');
//
//        $receptionFreeFieldLabel = CategoryType::RECEPTION;
//
//        $receptionFreeFields =
//            $this
//                ->connection
//                ->executeQuery("
//                    SELECT
//                            champ_libre.id,
//                            champ_libre.typage,
//                            champ_libre.default_value,
//                            champ_libre.elements,
//                            champ_libre.required_create,
//                            champ_libre.required_edit,
//                            champ_libre.label,
//                            champ_libre.elements
//                        FROM champ_libre
//                        INNER JOIN type ON type.id = champ_libre.type_id
//                        INNER JOIN category_type ON category_type.id = type.category_id
//                        WHERE category_type.label = '${receptionFreeFieldLabel}'
//                ")->fetchAll();
//
//        $allReceptions =
//            $this
//                ->connection
//                ->executeQuery('
//                    SELECT reception.id
//                    FROM reception
//                ')->fetchAll();
//        foreach ($allReceptions as $reception) {
//            $freeFieldsToBeInsertedInJSON = [];
//            $receptionId = intval($reception['id']);
//            foreach ($receptionFreeFields as $receptionFreeField) {
//                $freeFieldId = intval($receptionFreeField['id']);
//                $receptionFreeFieldInDB = $this
//                    ->connection
//                    ->executeQuery("
//                        SELECT
//                            reception.id,
//                            valeur_champ_libre.valeur
//                        FROM reception
//                        INNER JOIN reception_valeur_champ_libre ON reception_valeur_champ_libre.reception_id = reception.id
//                        INNER JOIN valeur_champ_libre ON valeur_champ_libre.id = reception_valeur_champ_libre.valeur_champ_libre_id
//                        INNER JOIN champ_libre ON champ_libre.id = valeur_champ_libre.champ_libre_id
//                        WHERE champ_libre.id = '${freeFieldId}' AND reception.id = '${receptionId}'
//                    ")->fetchAll();
//                $value = count($receptionFreeFieldInDB) > 0
//                    ? (isset($receptionFreeFieldInDB[0]['valeur'])
//                        ? $receptionFreeFieldInDB[0]['valeur']
//                        : "")
//                    : "";
//
//                $freeFieldsToBeInsertedInJSON[] = [
//                    'value' => $value,
//                    'label' => $receptionFreeField['label'],
//                    'requiredCreate' => $receptionFreeField['required_create'],
//                    'requiredEdit' => $receptionFreeField['required_edit'],
//                    'typage' => $receptionFreeField['typage'],
//                    'defaultValue' => $receptionFreeField['default_value'],
//                    'id' => $receptionFreeField['id'],
//                    'elements' => json_decode($receptionFreeField['elements'] ?? "")
//                ];
//            }
//
//            $encodedFreeFields = json_encode($freeFieldsToBeInsertedInJSON);
//
//            $this
//                ->addSql("UPDATE reception SET free_fields = '${encodedFreeFields}' WHERE reception.id = ${receptionId}");
//        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
