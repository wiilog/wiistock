-- Doctrine Migration File Generated on 2025-04-04 13:59:09

-- Version DoctrineMigrations\Version20250225091820
ALTER TABLE pack ADD current_tracking_delay_id INT DEFAULT NULL;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id;
-- Version DoctrineMigrations\Version20250225091820 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250225091820', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250226092516
UPDATE request_template SET request_template.discr = "deliveryrequesttemplatetriggeraction" WHERE request_template.discr = "deliveryrequesttemplate";
RENAME TABLE delivery_request_template TO delivery_request_template_trigger_action;
-- Version DoctrineMigrations\Version20250226092516 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250226092516', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250306123740
DELETE FROM translation where source_id = :source_id;
DELETE FROM translation_source where id = :source_id;
DELETE FROM translation where source_id = :source_id;
DELETE FROM translation_source where id = :source_id;
DELETE FROM translation where source_id = :source_id;
DELETE FROM translation_source where id = :source_id;
DELETE FROM translation where source_id = :source_id;
DELETE FROM translation_source where id = :source_id;
-- Version DoctrineMigrations\Version20250306123740 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250306123740', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250311140744
-- Version DoctrineMigrations\Version20250311140744 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250311140744', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250319143004
ALTER TABLE article ADD last_movement_id INT DEFAULT NULL;

                UPDATE article
                SET last_movement_id = (
                    SELECT id
                    FROM mouvement_stock
                    WHERE article.id = mouvement_stock.article_id AND  mouvement_stock.type IN (:movementTypeEntree, :movementTypeSortie )
                    ORDER BY mouvement_stock.date DESC LIMIT 1
                )
            ;
ALTER TABLE reference_article ADD last_movement_id INT DEFAULT NULL;

                UPDATE reference_article
                SET last_movement_id = (
                    SELECT id
                    FROM mouvement_stock
                    WHERE reference_article.id = mouvement_stock.ref_article_id AND  mouvement_stock.type IN (:movementTypeEntree, :movementTypeSortie)
                    ORDER BY mouvement_stock.date DESC LIMIT 1
                )
            ;
-- Version DoctrineMigrations\Version20250319143004 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250319143004', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250320090435
-- Version DoctrineMigrations\Version20250320090435 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250320090435', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250320154245
ALTER TABLE request_template_line RENAME TO request_template_line_reference;
-- Version DoctrineMigrations\Version20250320154245 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250320154245', '2025-04-04 13:59:09', 0);

-- Version DoctrineMigrations\Version20250404090937
INSERT INTO menu (label) VALUES (:label);
INSERT INTO action (label, menu_id) VALUES (:actionLabel, (SELECT id FROM menu WHERE label = :menuLabel LIMIT 1));
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action (label, menu_id) VALUES (:actionLabel, (SELECT id FROM menu WHERE label = :menuLabel LIMIT 1));
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action (label, menu_id) VALUES (:actionLabel, (SELECT id FROM menu WHERE label = :menuLabel LIMIT 1));
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId);
INSERT INTO action (label, menu_id) VALUES (:actionLabel, (SELECT id FROM menu WHERE label = :menuLabel LIMIT 1));
-- Version DoctrineMigrations\Version20250404090937 update table metadata;
INSERT INTO migration_versions (version, executed_at, execution_time) VALUES ('DoctrineMigrations\\Version20250404090937', '2025-04-04 13:59:09', 0);
