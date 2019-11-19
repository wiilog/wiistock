
-- Insertion des nouveaux statuts

INSERT INTO statut (id, categorie_id, nom, treated, comment, display_order) VALUES (NULL, 13, 'manque BL', 0, NULL, 1);
INSERT INTO statut (id, categorie_id, nom, treated, comment, display_order) VALUES (NULL, 13, 'manque info BL', 0, NULL, 2);
INSERT INTO statut (id, categorie_id, nom, treated, comment, display_order) VALUES (NULL, 13, 'écart quantité + ou -', 0, NULL, 3);
INSERT INTO statut (id, categorie_id, nom, treated, comment, display_order) VALUES (NULL, 13, 'écart qualité', 0, NULL, 4);
INSERT INTO statut (id, categorie_id, nom, treated, comment, display_order) VALUES (NULL, 13, 'problème de commande', 0, NULL, 5);
INSERT INTO statut (id, categorie_id, nom, treated, comment, display_order) VALUES (NULL, 13, 'destinataire non identifiable', 0, NULL, 6);

-- Remplissage des nouveaux statuts

UPDATE statut SET comment = 'Nous venons de recevoir un colis à votre attention sans bordereau de livraison. Dans l’attente du document votre colis est placé en litige. Nous rappelons que le BL doit être émis au titre d’une commande ou à titre gracieux.' WHERE nom = 'manque BL';
UPDATE statut SET comment = 'Nous venons de recevoir un colis à votre attention. Pour pouvoir finaliser la réception nous avons besoin d’un BL au titre d’une commande ou à titre gracieux. Dans l’attente du document votre colis est placé en litige.' WHERE nom = 'manque info BL';
UPDATE statut SET comment = 'Nous venons de recevoir un colis à votre attention, nous avons constaté un écart en quantité, [décrire la quantité de l’écart] Dans l’attente de vos instructions la quantité en écart est placée en litige.' WHERE nom = 'écart quantité + ou -';
UPDATE statut SET comment = 'Nous venons de recevoir un colis à votre attention et nous avons constaté un problème qualité. [décrire le problème qualité et joindre une ou plusieurs photos du problème constaté] Dans l’attente de vos instructions le colis est placé en zone litige.' WHERE nom = 'écart qualité';
UPDATE statut SET comment = 'Nous venons de recevoir un colis au titre de la commande [rentrer le numéro de commande] [décrire le problème constaté]. Dans l’attente de vos instructions le colis est placé en zone litige.' WHERE nom = 'problème de commande';
UPDATE statut SET comment = 'Nous venons de recevoir un colis à titre gracieux et nous sommes dans l’incapacité d’identifier un destinataire. Dans l’attente de vos instructions le colis est placé en zone litige.' WHERE nom = 'destinataire non identifiable';
