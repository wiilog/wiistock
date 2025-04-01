CREATE TABLE dw_acheminement_champs_libres
(
    acheminement_id integer,
    libelle         varchar(255),
    valeur          text
);

CREATE TABLE dw_jours_non_travailles
(
    jour date
);

CREATE TABLE dw_jours_horaires_travailles
(
    jour      varchar(255),
    travaille varchar(3),
    horaire1  varchar(255),
    horaire2  varchar(255),
    horaire3  varchar(255),
    horaire4  varchar(255)
);

CREATE TABLE dw_association_br
(
    date        timestamp(0),
    code_ul     varchar(255),
    reception   varchar(255),
    utilisateur varchar(255)
);

CREATE TABLE dw_service_statut
(
    date_statut timestamp(0),
    service_id  varchar(255),
    numero      varchar(255),
    statut      varchar(255),
    utilisateur varchar(255)
);

CREATE TABLE dw_arrivage_champs_libres
(
    arrivage_id integer,
    libelle     varchar(255),
    valeur      text
);

CREATE TABLE dw_collecte_champs_libres
(
    collecte_id integer,
    libelle     varchar(255),
    valeur      text
);

CREATE TABLE dw_reception_champs_libres
(
    reception_id integer,
    libelle      varchar(255),
    valeur       text
);

CREATE TABLE dw_reference_article_champs_libres
(
    reference_article_id integer,
    libelle              varchar(255),
    valeur               text
);

CREATE TABLE dw_demande_livraison_champs_libres
(
    demande_livraison_id integer,
    libelle              varchar(255),
    valeur               text
);

CREATE TABLE dw_arrivage_nature_ul
(
    arrivage_id integer,
    nature_ul   varchar(255),
    quantite_ul integer
);

CREATE TABLE dw_indicateur_arrivage
(
    nb_fournisseurs_differents integer,
    pourcentage_urgence        double precision,
    analyse_destinataire       double precision
);

CREATE TABLE dw_inventaire
(
    id                   integer,
    code_barre_reference varchar(255),
    code_barre_article   varchar(255),
    reference            varchar(255),
    libelle              varchar(255),
    type_flux            varchar(255),
    date                 date,
    quantite_comptee     integer
);

CREATE TABLE dw_mouvement_stock
(
    id                   integer,
    demande_collecte_id  integer,
    ordre_collecte_id    integer,
    demande_livraison_id integer,
    ordre_livraison_id   integer,
    demande_transfert_id integer,
    ordre_transfert_id   integer,
    ordre_reception_id   integer,
    type_mouvement       varchar(255),
    type_flux            varchar(255),
    emplacement_prise    varchar(255),
    emplacement_depose   varchar(255),
    demandeur            varchar(255),
    reference            varchar(255),
    libelle              varchar(255),
    quantite_livree      integer,
    operateur            varchar(255),
    code_barre_reference varchar(255),
    date                 timestamp(0),
    emplacement_stock    varchar(255),
    code_barre_article   varchar(255),
    prix_unitaire        float,
    commentaire          text
);

CREATE TABLE dw_reception
(
    id                       integer,
    no_commande              varchar(255),
    statut                   varchar(255),
    commentaire              text,
    date                     timestamp(0),
    date_attendue            date,
    numero                   varchar(255),
    fournisseur              varchar(255),
    emplacement              varchar(255),
    reference                varchar(255),
    libelle                  varchar(255),
    quantite_reference       integer,
    quantite_article_associe integer,
    code_barre_article       varchar(255),
    code_UL                  varchar(255),
    type_flux                varchar(255),
    quantite_recue           integer,
    quantite_a_recevoir      integer,
    code_barre_reference     varchar(255),
    urgence_reference        varchar(3),
    urgence_reception        varchar(3),
    numero_demande_achat     varchar(255),
    arrivage_id              integer,
    prix_unitaire            float,
    frais_livraison          float
);

CREATE TABLE dw_reference_article
(
    id                         integer,
    reference                  varchar(255),
    libelle                    varchar(255),
    quantite_stock             integer,
    type                       varchar(255),
    commentaire                text,
    emplacement                varchar(255),
    seuil_securite             integer,
    seuil_alerte               integer,
    date_securite_stock        timestamp(0),
    date_alerte_stock          timestamp(0),
    prix_unitaire              integer,
    code_barre                 varchar(255),
    categorie_inventaire       varchar(255),
    gestion_stock              varchar(255),
    gestionnaires              text,
    statut                     varchar(255),
    date_dernier_inventaire    timestamp(0),
    synchronisation_nomade     varchar(3),
    groupe_visibilite          varchar(255),
    acheteur                   varchar(255),
    creee_par                  varchar(255),
    date_creation              timestamp(0),
    editee_par                 varchar(255),
    date_modification          timestamp(0),
    date_derniere_entree       timestamp(0),
    date_derniere_sortie       timestamp(0),
    materiel_hors_format       varchar(3),
    code_fabricant             varchar(255),
    longueur                   varchar(255),
    largeur                    varchar(255),
    hauteur                    varchar(255),
    volume                     varchar(255),
    poids                      varchar(255),
    marchandise_dangeureuse    varchar(255),
    code_onu                   varchar(255),
    classe_produit             varchar(255),
    code_ndp                   varchar(255),
    derniere_reponse_stockage  timestamp(0),
    duree_stockage_maximal     integer,
    temps_immobilisation       integer
);

CREATE TABLE dw_service
(
    id                           integer,
    type                         varchar(255),
    objet                        text,
    demandeur                    varchar(255),
    date_creation                timestamp(0),
    date_attendue                timestamp(0),
    date_realisation             timestamp(0),
    operateur                    varchar(255),
    emplacement_prise            varchar(255),
    emplacement_depose           varchar(255),
    numero                       varchar(255),
    statut                       varchar(255),
    urgence                      varchar(255),
    delais_traitement_attendu    float,
    delais_traitement_validation float
);

CREATE TABLE dw_service_champs_libres
(
    service_id integer,
    libelle    varchar(255),
    valeur     text
);

CREATE TABLE dw_tracabilite
(
    date_mouvement        timestamp(0),
    code_ul               varchar(255),
    code_barre_article    varchar(255),
    type_mouvement        varchar(255),
    groupe                varchar(255),
    quantite_mouvement    integer,
    emplacement_mouvement varchar(255),
    operateur             varchar(255),
    mouvement_traca_id    integer,
    arrivage_id           integer,
    acheminement_id       integer,
    reception_id          integer
);

CREATE TABLE dw_tracabilite_champs_libres
(
    mouvement_traca_id integer,
    libelle            varchar(255),
    valeur             text
);

CREATE TABLE dw_urgence
(
    id                     integer,
    debut_delais_livraison date,
    fin_delais_livraison   date,
    no_commande            varchar(255),
    no_poste               varchar(255),
    acheteur               varchar(255),
    fournisseur            varchar(255),
    transporteur           varchar(255),
    no_tracking            varchar(255),
    date_arrivage          timestamp(0),
    numero_arrivage        varchar(255),
    date_creation          timestamp(0)
);

CREATE TABLE dw_arrivage
(
    id                       integer,
    no_arrivage              varchar(255),
    date                     timestamp(0),
    nb_ul                    integer,
    destinataires            varchar(255),
    fournisseur              varchar(255),
    transporteur             varchar(255),
    chauffeur                varchar(255),
    no_tracking_transporteur text,
    no_commande_bl           text,
    type                     varchar(255),
    acheteurs                text,
    urgence                  varchar(255),
    douane                   varchar(255),
    congele                  varchar(255),
    statut                   varchar(255),
    commentaire              text,
    utilisateur              varchar(255),
    numero_projet            varchar(255),
    business_unit            varchar(255),
    reception_id             integer,
    no_arrivage_camion       varchar(255)
);

CREATE TABLE dw_acheminement
(
    id                           integer,
    numero                       varchar(255),
    date_creation                timestamp(0),
    date_validation              timestamp(0),
    date_traitement              timestamp(0),
    date_echeance_debut          date,
    date_echeance_fin            date,
    type                         varchar(255),
    transporteur                 varchar(255),
    numero_tracking_transporteur varchar(255),
    numero_commande              varchar(255),
    demandeur                    varchar(255),
    destinataires                varchar(255),
    code_ul                      varchar(255),
    quantite_ul                  integer,
    quantite_a_acheminer         integer,
    nature_ul                    varchar(255),
    longueur_ul                  varchar(255),
    largeur_ul                   varchar(255),
    hauteur_ul                   varchar(255),
    emplacement_prise            varchar(255),
    emplacement_depose           varchar(255),
    destination                  varchar(255),
    nb_ul                        integer,
    statut                       varchar(255),
    operateur                    varchar(255),
    traite_par                   varchar(255),
    dernier_emplacement          varchar(255),
    date_dernier_mouvement       timestamp(0),
    urgence                      varchar(255),
    numero_projet                varchar(255),
    business_unit                varchar(255),
    delais_traitement_attendu    float,
    delais_traitement_validation float,
    reference                    varchar(255),
    quantite_reference           integer,
    numero_lot                   varchar(255),
    numero_serie                 varchar(255),
    numero_plombage_scelle       varchar(255),
    adr                          varchar(3),
    commentaire                  text,
    nom_client                   varchar(255),
    adresse_client               varchar(255),
    telephone_client             varchar(255),
    destinataire_client          varchar(255),
    types_documents_associes     varchar(255)
);

CREATE TABLE dw_acheminement_statut
(
    id                    integer,
    statut                varchar(255),
    date_statut           timestamp(0),
    signataire_enlevement varchar(255),
    signataire_livraison  varchar(255),
    utilisateur           varchar(255)
);

CREATE TABLE dw_demande_collecte
(
    id              integer,
    numero          varchar(255),
    date_creation   timestamp(0),
    date_validation timestamp(0),
    point_collecte  varchar(255),
    demandeur       varchar(255),
    objet           varchar(255),
    destination     varchar(255),
    statut          varchar(255),
    type            varchar(255),
    commentaire     text,
    code_barre      varchar(255),
    quantite        integer
);

CREATE TABLE dw_demande_transfert
(
    id              integer,
    numero          varchar(255),
    date_creation   timestamp(0),
    date_validation timestamp(0),
    statut          varchar(255),
    demandeur       varchar(255),
    origine         varchar(255),
    destination     varchar(255),
    commentaire     text,
    reference       varchar(255),
    code_barre      varchar(255)
);

CREATE TABLE dw_demande_livraison
(
    id                  integer,
    numero              varchar(255),
    date_creation       timestamp(0),
    date_traitement     timestamp(0),
    date_validation     timestamp(0),
    date_attendue       date,
    projet              varchar(255),
    demandeur           varchar(255),
    destinataire        varchar(255),
    type                varchar(255),
    statut              varchar(255),
    codes_preparations  text,
    codes_livraisons    text,
    destination         varchar(255),
    commentaire         text,
    reference_article   varchar(255),
    libelle             varchar(255),
    code_barre          varchar(255),
    quantite_disponible integer,
    quantite_a_prelever integer,
    delais_traitement   float,
    code_UL             varchar(255),
    projet_article      varchar(255),
    commentaire_article text
);

CREATE TABLE dw_ordre_transfert
(
    id             integer,
    numero_ordre   varchar(255),
    numero_demande varchar(255),
    statut         varchar(255),
    demandeur      varchar(255),
    operateur      varchar(255),
    origine        varchar(255),
    destination    varchar(255),
    date_creation  timestamp(0),
    date_transfert timestamp(0),
    commentaire    text,
    reference      varchar(255),
    code_barre     varchar(255),
    delta_date     float
);

CREATE TABLE dw_ordre_collecte
(
    id                   integer,
    numero               varchar(255),
    statut               varchar(255),
    date_creation        timestamp(0),
    date_traitement      timestamp(0),
    operateur            varchar(255),
    type                 varchar(255),
    reference            varchar(255),
    libelle              varchar(255),
    emplacement          varchar(255),
    quantite_a_collecter integer,
    code_barre           varchar(255),
    destination          varchar(255),
    delta_date           float
);

CREATE TABLE dw_ordre_livraison
(
    id                integer,
    numero            varchar(255),
    statut            varchar(255),
    date_creation     timestamp(0),
    date_livraison    timestamp(0),
    date_demande      timestamp(0),
    demandeur         varchar(255),
    operateur         varchar(255),
    type              varchar(255),
    commentaire       text,
    reference         varchar(255),
    libelle           varchar(255),
    emplacement       varchar(255),
    quantite_a_livrer integer,
    quantite_en_stock integer,
    code_barre        varchar(255),
    delta_date        float
);

CREATE TABLE dw_demande_achat
(
    id                   integer,
    numero               varchar(255),
    date_creation        timestamp(0),
    date_validation      timestamp(0),
    date_prise_en_compte timestamp(0),
    date_traitement      timestamp(0),
    demandeur            varchar(255),
    acheteur             varchar(255),
    statut               varchar(255),
    reference            varchar(255),
    libelle              varchar(255),
    quantite_demandee    integer,
    quantite_stock       integer,
    quantite_commandee   integer,
    numero_commande      varchar(255),
    numero_reception     varchar(255),
    fournisseur          varchar(255),
    date_commande        timestamp(0),
    date_attendue        timestamp(0),
    prix_unitaire        float,
    frais_livraison      float
);

CREATE TABLE dw_litige
(
    litige_id           integer,
    numero              varchar(255),
    type                varchar(255),
    date_creation       timestamp(0),
    dernier_statut      varchar(255),
    dernier_commentaire text,
    acheteurs           varchar(255),
    declarant           varchar(255),
    urgence             varchar(3),
    numero_arrivage     varchar(255),
    numero_reception    varchar(255),
    numero_commande_bl  varchar(255),
    numero_ligne        varchar(255),
    fournisseur         varchar(255),
    reference           varchar(255),
    transporteur        varchar(255),
    ul_article          varchar(255),
    libelle_article     varchar(255),
    reference_article   varchar(255),
    quantite_article    integer
);

CREATE TABLE dw_litige_statut
(
    litige_id   integer,
    numero      varchar(255),
    type        varchar(255),
    statut      varchar(255),
    date_statut timestamp(0),
    utilisateur varchar(255)
);

CREATE TABLE dw_article_champs_libres
(
    article_id integer,
    libelle    varchar(255),
    valeur     text
);

CREATE TABLE dw_projet_article
(
    article_id       integer,
    projet           varchar(255),
    date_assignation timestamp(0)
);

CREATE TABLE dw_arrivage_camion
(
    id                    integer,
    no_arrivage_camion    varchar(255),
    no_tracking           varchar(255),
    date_creation         timestamp(0),
    transporteur          varchar(255),
    chauffeur             varchar(255),
    immatriculation       varchar(255),
    emplacement           varchar(255),
    operateur             varchar(255),
    nb_tracking_total     integer,
    reserve_generale      varchar(255),
    reserve_quantite      varchar(255),
    reserve_tracking_type varchar(255)
);

CREATE TABLE dw_article
(
    id                          integer,
    reference                   varchar(255),
    libelle                     varchar(255),
    code_barre                  varchar(255),
    statut                      varchar(255),
    quantite                    integer,
    commentaire                 varchar(255),
    emplacement                 text,
    date_dernier_inventaire     timestamp(0),
    lot                         varchar(255),
    date_entree_stock           timestamp(0),
    date_peremption             date,
    code_fournisseur            varchar(255),
    reference_fournisseur       varchar(255),
    label_fournisseur           varchar(255),
    label_reference_fournisseur varchar(255),
    projet                      varchar(255),
    date_assignation_projet     timestamp(0),
    code_ul                     varchar(255),
    prix_unitaire               integer,
    anomalie                    varchar(255),
    tag_rfid                    varchar(255),
    type                        varchar(255),
    numero_commande             varchar(255),
    numero_bon_livraison        varchar(255),
    pays_origine                varchar(255),
    date_fabrication            timestamp(0),
    derniere_reponse_stockage   timestamp(0),
    duree_stockage_maximal      integer,
    temps_immobilisation        integer
);

CREATE TABLE dw_unite_logistique
(
    code_unite_logistique varchar(255),
    nature                varchar(255),
    quantite              integer,
    projet                varchar(255),
    acheminement_id       integer,
    arrivage_id           integer,
    nb_articles_contenus  integer,
    code_barre_article    varchar(255)
);

CREATE TABLE dw_demande_expedition
(
    id                             integer,
    numero                         varchar(255),
    statut                         varchar(255),
    date_creation                  timestamp(0),
    date_validation                timestamp(0),
    date_planification             timestamp(0),
    date_enlevement_prevu          timestamp(0),
    date_expedition                timestamp(0),
    date_prise_en_charge_souhaitee timestamp(0),
    demandeur                      varchar(255),
    numero_commande_client         varchar(255),
    livraison_titre_gracieux       varchar(255),
    articles_conformes             varchar(255),
    client                         varchar(255),
    destinataire_client            varchar(255),
    telephone_client               varchar(255),
    adresse_livraison              varchar(255),
    code_reference                 varchar(255),
    quantite_reference             integer,
    code_UL                        varchar(255),
    nature_UL                      varchar(255),
    code_article                   varchar(255),
    quantite_article               varchar(255),
    prix_unitaire_reference        integer,
    poids_net_reference            integer,
    montant_total_reference        integer,
    envoi                          varchar(255),
    port                           varchar(255),
    poids_net_transport            integer,
    dimension_ul                   varchar(255),
    valeur_totale_transport        integer,
    nombre_ul                      integer,
    poids_brut_transport           integer
);

CREATE TABLE dw_numero_tracking
(
    no_tracking        varchar(255),
    no_arrivage_camion varchar(255),
    reserve_qualite    varchar(255),
    retard             varchar(255),
    no_arrivage_UL     varchar(255)
);

CREATE TABLE dw_capteur
(
    code_capteur      varchar(255),
    nom_capteur       varchar(255),
    type_capteur      varchar(255),
    profil_capteur    varchar(255),
    derniere_remontee timestamp(0),
    niveau_batterie   varchar(255),
    gestionnaire      varchar(255)
);

CREATE TABLE dw_actionneur
(
    code_capteur  varchar(255),
    type_modele   varchar(255),
    nom_modele    varchar(255),
    seuil_capteur varchar(255),
    type_seuil    varchar(255)
);

CREATE TABLE dw_capteur_messages
(
    code_capteur      varchar(255),
    date_message      timestamp(0),
    type_donnee       varchar(255),
    donnee_principale text
);

CREATE TABLE dw_capteur_elements_associes
(
    code_capteur         varchar(255),
    element_associe      varchar(255),
    date_association     timestamp(0),
    date_fin_association timestamp(0)
);

CREATE TABLE dw_capteur_champs_libres
(
    code_capteur varchar(255),
    libelle      varchar(255),
    valeur       text
);

CREATE TABLE dw_production
(
    production_id           integer,
    numero_of               varchar(255),
    urgence                 varchar(255),
    date_attendue           timestamp(0),
    numero_projet           varchar(255),
    code_produit_article    varchar(255),
    emplacement_depose      varchar(255),
    commentaire             text,
    piece_jointe            varchar(255),
    quantite                integer,
    nombre_lignes           integer,
    emplacement_destination varchar(255)
);

CREATE TABLE dw_production_statut
(
    production_id integer,
    statut        varchar(255),
    date_statut   timestamp(0),
    utilisateur   varchar(255)
);

CREATE TABLE dw_production_champs_libres
(
    production_id varchar(255),
    libelle       varchar(255),
    valeur        text
);

CREATE TABLE dw_informations
(
    version varchar(255)
);

