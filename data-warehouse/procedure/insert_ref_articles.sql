use db_stock_terega;
DROP procedure IF EXISTS insert_refs;

delimiter //
CREATE procedure db_stock_terega.insert_refs()
wholeblock:BEGIN
    DECLARE x INT;
    SET x = 3500;
    loop_label: LOOP
        IF x >= 8000 THEN
            LEAVE loop_label;
        END IF;
        INSERT INTO reference_article (id, type_id, statut_id, emplacement_id, libelle, reference, quantite_disponible,
                                       quantite_reservee, quantite_stock, type_quantite, commentaire, category_id,
                                       bar_code, prix_unitaire, date_last_inventory, limit_security, limit_warning,
                                       is_urgent, emergency_comment, user_that_triggered_emergency_id, needs_mobile_sync,
                                       free_fields, stock_management, cleaned_comment, buyer_id, order_state, visibility_group_id,
                                       image_id, created_by_id, edited_by_id, created_at, edited_at, last_stock_entry,
                                       last_stock_exit, up_to_date_inventory, sheet_id, description, ndp_code, onu_code,
                                       product_class, dangerous_goods)
        VALUES (null, 9, 1, null, CONCAT('label REFDECLO n°', x), CONCAT('REFDECLO n°', x),
                RAND(300), RAND(100), RAND(500),
                'article', CONCAT('commentaire n°', x), null, LEFT(UUID(), 8), RAND(100),
                null, null, null, 0, null, null, 1, '[]', null, null, null,
                null, null, null, 561, null, NOW(), null, null, null, null,
                null, CONCAT('{"width": "', RAND(10), '", "height": "', RAND(10), '", "length": "', RAND(10), '", "volume": "', RAND(10), '", "weight": "', RAND(10), '", "manufacturerCode": "defvssdqc", "outFormatEquipment": "0", "associatedDocumentTypes": ""}'),
                'ezdsg', 'fiqgu', 'dddd', 0);
        SET x = x + 1;
        ITERATE loop_label;
    END LOOP;
END //

call insert_refs;
