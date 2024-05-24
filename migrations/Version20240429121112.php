<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Language;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240429121112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $generalTranslationCategory = $this->connection->fetchAssociative("SELECT id FROM `translation_category` WHERE `label` = :name AND parent_id IS NULL", [
            'name' => 'Général',
        ]);

        $nullTranslationCategory = $this->connection->fetchAssociative("SELECT id FROM `translation_category` WHERE `label` = '' AND parent_id = :parent", [
            'parent' => $generalTranslationCategory['id'],
        ]);

        $headerTranslationCategory = $this->connection->fetchAssociative("SELECT id FROM `translation_category` WHERE `label` = :name AND parent_id = :parent", [
            'name' => 'Header',
            'parent' => $nullTranslationCategory['id'],
        ]);

        $this->connection->executeQuery("
                INSERT INTO `translation_source` (`category_id` )
                VALUES (:category)
            ",
            [
                'category' => $headerTranslationCategory['id'],
            ]
        );

        // the id of the source we just created, with lastInsertId()
        $sourceId = $this->connection->fetchAssociative("SELECT LAST_INSERT_ID() as id");

        $applicationNames = [
            Language::FRENCH_SLUG => "Follow GT",
            Language::ENGLISH_SLUG => "Follow GT",
            Language::FRENCH_DEFAULT_SLUG => "Wiilog",
            Language::ENGLISH_DEFAULT_SLUG => "Wiilog",
        ];

        foreach ($applicationNames as $slug => $name) {
            // the id of the language we want to insert the translation for
            $language = $this->connection->fetchAssociative("SELECT id FROM `language` WHERE `slug` = :slug", [
                'slug' => $slug,
            ]);

            $this->addSql("
                INSERT INTO `translation` (`source_id`, `language_id`, `translation`)
                VALUES (:source, :language, :translation)
                ",
                [
                    'source' => $sourceId['id'],
                    'language' => $language['id'],
                    'translation' => $name
                ]);
        }

        $translationsToCreate = [
            Language::FRENCH_SLUG => [
                "L'équipe GT Logistics." => "L'équipe GT Logistics.",
                "Cliquez ici pour accéder à Follow GT" => "Cliquez ici pour accéder à Follow GT",
            ],
            Language::ENGLISH_SLUG => [
                "The GT Logistics team" => "The GT Logistics team",
                "Click here to access Follow GT" => "Click here to access Follow GT",
            ],
        ];

        foreach ($translationsToCreate as $slug => $translations) {
            $language = $this->connection->fetchAssociative("SELECT id FROM `language` WHERE `slug` = :slug", [
                'slug' => $slug,
            ]);

            foreach ($translations as $key => $value) {
                $source = $this->connection->fetchAssociative("SELECT source_id FROM `translation` WHERE `translation` = :translation", [
                    'translation' => $key,
                    'language' => $language['id']
                ]);

                if ($source['source_id'] ?? false) {
                    $this->addSql("
                        INSERT INTO `translation` (`source_id`, `language_id`, `translation`)
                        VALUES (:source, :language, :translation)
                    ",
                        [
                            'source' => $source['source_id'],
                            'language' => $language['id'],
                            'translation' => $value
                        ]);
                }

            }
        }

        $translationsToChange = [
            Language::FRENCH_DEFAULT_SLUG => [
                "L'équipe GT Logistics." => "L'équipe Wiilog.",
                "Cliquez ici pour accéder à Follow GT" => "Cliquez ici pour accéder à ",
                "FOLLOW GT // Dépose effectuée" => "Dépose effectuée",
                "FOLLOW GT // Demande de service effectuée" => "Demande de service effectuée",
                "FOLLOW GT // Arrivage UL" => "Arrivage UL",
                "FOLLOW GT // Arrivage UL urgent" => "Arrivage UL urgent",
                "FOLLOW GT // Litige sur {1}" => "Litige sur {1}",
                "FOLLOW GT // Changement de statut d'un litige sur {1}" => "Changement de statut d'un litige sur {1}",
                "FOLLOW GT // Récapitulatif de vos litiges" => "Récapitulatif de vos litiges",
                "FOLLOW GT // Notification de traitement d'une demande d'acheminement" => "Notification de traitement d'une demande d'acheminement",
                "FOLLOW GT // Création d'une demande d'acheminement" => "Création d'une demande d'acheminement",
                "FOLLOW GT // Urgence : Notification de traitement d'une demande d'acheminement" => "Urgence : Notification de traitement d'une demande d'acheminement",
                "FOLLOW GT // Création d'une demande de service" => "Création d'une demande de service",
                "FOLLOW GT // Changement de statut d'une demande d'acheminement" => "Changement de statut d'une demande d'acheminement",
            ],
            Language::ENGLISH_DEFAULT_SLUG => [
                "FOLLOW GT // Creation of a service operation" => "Creation of a service operation",
                "The GT Logistics team" => "The Wiilog team",
                "Click here to access Follow GT" => "Click here to access ",
                "FOLLOW GT // Drop done" => "Drop done",
                "FOLLOW GT // Service operation completed" => "Service operation completed",
                "FOLLOW GT // LU Arrivals" => "LU Arrivals",
                "FOLLOW GT // Urgent LU arrival" => "Urgent LU arrival",
                "FOLLOW GT // Dispute on {1}" => "Dispute on {1}",
                "FOLLOW GT // Notification upon transfer operation finishing" => "Notification upon transfer operation finishing",
                "FOLLOW GT // Status change for a transfer operation" => "Status change for a transfer operation",
                "FOLLOW GT // Transfer operation has been updated" => "Transfer operation has been updated",
                "FOLLOW GT // Summary of your disputes" => "Summary of your disputes",
                "FOLLOW GT // Emergency : Notification upon transfer operation finishing" => "Emergency : Notification upon transfer operation finishing",
            ]
        ];

        foreach ($translationsToChange as $slug => $translations) {

            $language = $this->connection->fetchAssociative("SELECT id FROM `language` WHERE `slug` = :slug", [
                'slug' => $slug,
            ]);

            foreach ($translations as $key => $value) {
                $this->addSql("
                        UPDATE `translation`
                        SET `translation` = :newContent
                        WHERE `translation` = :oldContent
                        AND `language_id` = :language
                    ",
                    [
                        'newContent' => $value,
                        'oldContent' => $key,
                        'language' => $language['id']
                    ]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {

    }
}
