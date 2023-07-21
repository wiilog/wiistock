-- Doctrine Migration File Generated on 2023-07-21 14:09:47

-- Version DoctrineMigrations\Version20230721120146
CREATE TABLE reserve_type (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, default_reserve_type TINYINT(1) DEFAULT NULL, active TINYINT(1) DEFAULT 1, UNIQUE INDEX UNIQ_B7802434EA750E8 (label), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
CREATE TABLE reserve_type_utilisateur (reserve_type_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_A8CB84365973EA4D (reserve_type_id), INDEX IDX_A8CB8436FB88E14F (utilisateur_id), PRIMARY KEY(reserve_type_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
ALTER TABLE reserve_type_utilisateur ADD CONSTRAINT FK_A8CB84365973EA4D FOREIGN KEY (reserve_type_id) REFERENCES reserve_type (id) ON DELETE CASCADE;
ALTER TABLE reserve_type_utilisateur ADD CONSTRAINT FK_A8CB8436FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE;
ALTER TABLE reserve ADD reserve_type_id INT DEFAULT NULL, CHANGE type kind VARCHAR(255) NOT NULL;
ALTER TABLE reserve ADD CONSTRAINT FK_1FE0EA225973EA4D FOREIGN KEY (reserve_type_id) REFERENCES reserve_type (id);
CREATE INDEX IDX_1FE0EA225973EA4D ON reserve (reserve_type_id);
ALTER TABLE sensor_message ADD content_type INT NOT NULL;

-- Version DoctrineMigrations\Version20230721120244
INSERT INTO reserve_type (label, default_reserve_type, active) VALUES (:qualitylabel, 1, 1);

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 3
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 4
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 13
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 14
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 15
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 16
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 18
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 19
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 20
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 21
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 22
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 24
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 26
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 27
            ;

                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = 29
            ;
