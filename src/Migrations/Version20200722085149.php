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
final class Version20200722085149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this
            ->addSql('ALTER TABLE reference_article ADD free_fields JSON DEFAULT NULL;');

        $refArticleCategoryTypeLabel = CategoryType::ARTICLE;
        $refArticleCategoryCLabel = CategorieCL::REFERENCE_ARTICLE;
        $allRefs =
            $this
                ->connection
                ->executeQuery('
                    SELECT reference_article.id, t.id as typeId
                    FROM reference_article
                    INNER JOIN type t on reference_article.type_id = t.id
                ')->fetchAll();
        foreach ($allRefs as $ref) {
            $freeFieldsToBeInsertedInJSON = [];
            $refId = intval($ref['id']);
            $typeId = intval($ref['typeId']);
            $refsFreeFieldValuesInDB = $this
                ->connection
                ->executeQuery("
                        SELECT
                            reference_article.id,
                            valeur_champ_libre.valeur,
                            champ_libre.id as freeFieldId,
                            champ_libre.typage,
                            champ_libre.label,
                            champ_libre.required_create,
                            champ_libre.required_edit,
                            champ_libre.elements,
                            champ_libre.default_value,
                            t.id as typeId
                        FROM reference_article
                        LEFT JOIN valeur_champ_libre_reference_article vclra on reference_article.id = vclra.reference_article_id
                        LEFT JOIN valeur_champ_libre ON valeur_champ_libre.id = vclra.valeur_champ_libre_id
                        LEFT JOIN champ_libre ON champ_libre.id = valeur_champ_libre.champ_libre_id
                        LEFT JOIN categorie_cl cc on champ_libre.categorie_cl_id = cc.id
                        LEFT JOIN type t on champ_libre.type_id = t.id
                        LEFT JOIN category_type ON t.category_id = category_type.id
                        WHERE reference_article.id = '${refId}' AND category_type.label = '${refArticleCategoryTypeLabel}' AND cc.label = '${refArticleCategoryCLabel}'
                    ")->fetchAll();

            foreach ($refsFreeFieldValuesInDB as $freeFieldValue) {
                $freeFieldId = intval($freeFieldValue['freeFieldId']);
                $clTypeId = intval($freeFieldValue['typeId']);

                $value = !empty($freeFieldValue['valeur'])
                    ? $freeFieldValue['valeur']
                    : "";
                if ($typeId === $clTypeId) {
                    $value = $freeFieldValue['typage'] === ChampLibre::TYPE_BOOL
                        ? (empty($value)
                            ? "0"
                            : "1")
                        : $value;
                    $freeFieldsToBeInsertedInJSON[] = [
                        'value' => strval($value),
                        'label' => $freeFieldValue['label'],
                        'requiredCreate' => $freeFieldValue['required_create'],
                        'requiredEdit' => $freeFieldValue['required_edit'],
                        'typage' => $freeFieldValue['typage'],
                        'defaultValue' => $freeFieldValue['default_value'],
                        'id' => $freeFieldId,
                        'elements' => json_decode($refFreeField['elements'] ?? "")
                    ];
                }
            }

            $encodedFreeFields = json_encode($freeFieldsToBeInsertedInJSON);

            $this
                ->addSql("UPDATE reference_article SET free_fields = '${encodedFreeFields}' WHERE reference_article.id = ${refId}");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
