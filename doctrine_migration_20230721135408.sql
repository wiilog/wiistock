-- Doctrine Migration File Generated on 2023-07-21 13:54:08

-- Version DoctrineMigrations\Version20230718122212
CREATE TABLE reserve_type (id INT NOT NULL, label VARCHAR(255) NOT NULL, default_reserve_type TINYINT(1) DEFAULT NULL, active TINYINT(1) DEFAULT 1);
CREATE TABLE reserve_type_utilisateur (reserve_type_id INT NOT NULL, utilisateur_id INT NOT NULL);
ALTER TABLE reserve ADD reserve_type_id INT NOT NULL;
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
