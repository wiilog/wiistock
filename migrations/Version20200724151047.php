<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Helper\FormatHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200724151047 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this
            ->addSql('ALTER TABLE demande ADD free_fields JSON DEFAULT NULL;');


        $requestsCategoryTypeLabel = CategoryType::DEMANDE_LIVRAISON;
        $requestsCategoryCLabel = CategorieCL::DEMANDE_LIVRAISON;

        $requestsFreeFields =
            $this
                ->connection
                ->executeQuery("
                    SELECT
                        champ_libre.id
                        FROM champ_libre
                        INNER JOIN categorie_cl cc on champ_libre.categorie_cl_id = cc.id
                        INNER JOIN type ON type.id = champ_libre.type_id
                        INNER JOIN category_type ON category_type.id = type.category_id
                        WHERE category_type.label = '${requestsCategoryTypeLabel}' AND cc.label = '${requestsCategoryCLabel}'
                ")->fetchAll();
        if (!empty($requestsFreeFields)) {
            $refsFreeFieldIds = array_map(function (array $freeField) {
                return intval($freeField['id']);
            }, $requestsFreeFields);

            $requestsFreeFieldIdsString = implode(',', $refsFreeFieldIds);

            $allRequests =
                $this
                    ->connection
                    ->executeQuery('
                    SELECT demande.id, t.id as typeId
                    FROM demande
                    INNER JOIN type t on demande.type_id = t.id
                ')->fetchAll();

            foreach ($allRequests as $index => $request) {
                if ($index % 500 === 0) dump('500 de plus!');
                $freeFieldsToBeInsertedInJSON = [];
                $requestId = intval($request['id']);
                $typeId = intval($request['typeId']);
                $requestFreeFieldValuesInDB = $this
                    ->connection
                    ->executeQuery("
                        SELECT
                            demande.id,
                            valeur_champ_libre.valeur,
                            champ_libre.id as freeFieldId,
                            champ_libre.typage,
                            champ_libre.label,
                            champ_libre.required_create,
                            champ_libre.required_edit,
                            champ_libre.elements,
                            champ_libre.default_value,
                            t.id as typeId
                        FROM demande
                        LEFT JOIN demande_valeur_champ_libre dvcl ON demande.id = dvcl.demande_id
                        LEFT JOIN valeur_champ_libre ON valeur_champ_libre.id = dvcl.valeur_champ_libre_id
                        LEFT JOIN champ_libre ON champ_libre.id = valeur_champ_libre.champ_libre_id
                        INNER JOIN categorie_cl cc on champ_libre.categorie_cl_id = cc.id
                        INNER JOIN type t on champ_libre.type_id = t.id
                        INNER JOIN category_type ON t.category_id = category_type.id
                        WHERE demande.id = '${requestId}' AND champ_libre.id IN (${requestsFreeFieldIdsString})
                    ")->fetchAll();

                foreach ($requestFreeFieldValuesInDB as $freeFieldValue) {
                    $freeFieldId = intval($freeFieldValue['freeFieldId']);
                    $clTypeId = intval($freeFieldValue['typeId']);

                    $value = !empty($freeFieldValue['valeur'])
                        ? $freeFieldValue['valeur']
                        : "";
                    $value = $freeFieldValue['typage'] === FreeField::TYPE_BOOL
                        ? (empty($value)
                            ? "0"
                            : "1")
                        : $value;
                    if ($typeId === $clTypeId && ($value || $value === "0")) {
                        if ($freeFieldValue['typage'] !== FreeField::TYPE_LIST || in_array($value, json_decode($freeFieldValue['elements']))) {
                            $freeFieldsToBeInsertedInJSON[$freeFieldId] = strval($value);
                        }
                    }
                }

                $encodedFreeFields = FormatHelper::sqlString(json_encode($freeFieldsToBeInsertedInJSON));
                $this
                    ->addSql("UPDATE demande SET free_fields = '${encodedFreeFields}' WHERE demande.id = ${requestId}");

            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
