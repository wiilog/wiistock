<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200727074820 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this
            ->addSql('ALTER TABLE article ADD free_fields JSON DEFAULT NULL;');


        $entityCategoryTypeLabel = CategoryType::ARTICLE;
        $entityCategoryCLabel = CategorieCL::ARTICLE;

        $entityFreeFields =
            $this
                ->connection
                ->executeQuery("
                    SELECT
                        champ_libre.id
                        FROM champ_libre
                        INNER JOIN categorie_cl cc on champ_libre.categorie_cl_id = cc.id
                        INNER JOIN type ON type.id = champ_libre.type_id
                        INNER JOIN category_type ON category_type.id = type.category_id
                        WHERE category_type.label = '${entityCategoryTypeLabel}' AND cc.label = '${entityCategoryCLabel}'
                ")->fetchAll();
        if (!empty($entityFreeFields)) {
            $entityFreeFieldIds = array_map(function (array $freeField) {
                return intval($freeField['id']);
            }, $entityFreeFields);

            $entityFreeFieldIdsString = implode(',', $entityFreeFieldIds);

            $allEntities =
                $this
                    ->connection
                    ->executeQuery('
                    SELECT article.id, t.id as typeId
                    FROM article
                    INNER JOIN type t on article.type_id = t.id
                ')->fetchAll();

            foreach ($allEntities as $index => $entity) {
                if ($index % 500 === 0) dump('500 de plus!');
                $freeFieldsToBeInsertedInJSON = [];
                $entityID = intval($entity['id']);
                $typeId = intval($entity['typeId']);
                $entityFreeFieldValuesInDB = $this
                    ->connection
                    ->executeQuery("
                        SELECT
                            article.id,
                            valeur_champ_libre.valeur,
                            champ_libre.id as freeFieldId,
                            champ_libre.typage,
                            champ_libre.label,
                            champ_libre.required_create,
                            champ_libre.required_edit,
                            champ_libre.elements,
                            champ_libre.default_value,
                            t.id as typeId
                        FROM article
                        LEFT JOIN valeur_champ_libre_article vcla on article.id = vcla.article_id
                        LEFT JOIN valeur_champ_libre ON valeur_champ_libre.id = vcla.valeur_champ_libre_id
                        LEFT JOIN champ_libre ON champ_libre.id = valeur_champ_libre.champ_libre_id
                        INNER JOIN categorie_cl cc on champ_libre.categorie_cl_id = cc.id
                        INNER JOIN type t on champ_libre.type_id = t.id
                        INNER JOIN category_type ON t.category_id = category_type.id
                        WHERE article.id = '${entityID}' AND champ_libre.id IN (${entityFreeFieldIdsString})
                    ")->fetchAll();

                foreach ($entityFreeFieldValuesInDB as $freeFieldValue) {
                    $freeFieldId = intval($freeFieldValue['freeFieldId']);
                    $clTypeId = intval($freeFieldValue['typeId']);

                    $value = !empty($freeFieldValue['valeur'])
                        ? $freeFieldValue['valeur']
                        : "";
                    $value = $freeFieldValue['typage'] === ChampLibre::TYPE_BOOL
                        ? (empty($value)
                            ? "0"
                            : "1")
                        : $value;
                    if ($typeId === $clTypeId && ($value || $value === "0")) {
                        if ($freeFieldValue['typage'] !== ChampLibre::TYPE_LIST || in_array($value, json_decode($freeFieldValue['elements']))) {
                            $freeFieldsToBeInsertedInJSON[$freeFieldId] = strval($value);
                        }
                    }
                }

                $encodedFreeFields = json_encode($freeFieldsToBeInsertedInJSON);
                $encodedFreeFields = str_replace("\\", "\\\\", $encodedFreeFields);
                $encodedFreeFields = str_replace("'", "''", $encodedFreeFields);
                $this
                    ->addSql("UPDATE article SET free_fields = '${encodedFreeFields}' WHERE article.id = ${entityID}");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
