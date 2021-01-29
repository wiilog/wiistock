create table dw_acheminement_champs_libres
(
    acheminement_id integer,
    libelle         varchar(255),
    valeur          text
);

create table dw_arrivage_champs_libres
(
    arrivage_id integer,
    libelle     varchar(255),
    valeur      text
);

create table dw_collecte_champs_libres
(
    collecte_id integer,
    libelle     varchar(255),
    valeur      text
);

create table dw_indicateur_arrivage
(
    nb_fournisseurs_differents integer,
    pourcentage_urgence        double precision,
    analyse_destinataire       double precision
);

create table dw_inventaire
(
    code_barre_reference varchar(255),
    code_barre_article   varchar(255),
    reference            varchar(255),
    libelle              varchar(255),
    type_flux            varchar(255),
    date                 date,
    quantite_comptee     integer,
    id                   integer not null
        constraint inventaire_pk
        primary key
);

create table dw_livraison_champs_libres
(
    livraison_id integer,
    libelle      varchar(255),
    valeur       text
);

create table dw_mouvement_stock
(
    type_mouvement       varchar(255),
    type_flux            varchar(255),
    emplacement_prise    varchar(255),
    emplacement_depose   varchar(255),
    demandeur            varchar(255),
    commentaire_demande  text,
    numero_demande       varchar(255),
    numero_ordre         varchar(255),
    date_validation_collecte    timestamp(0),
    date_validation_livraison   timestamp(0),
    reference            varchar(255),
    libelle              varchar(255),
    quantite_livree      integer,
    operateur            varchar(255),
    code_barre_reference varchar(255),
    date                 timestamp(0),
    emplacement_stock    varchar(255),
    emplacement_transfert    varchar(255),
    id                   integer not null
        constraint mouvement_stock_pk
        primary key,
    demande_livraison_id    integer,
    demande_collecte_id     integer,
    delta_date           float,
    code_barre_article   varchar(255),
    urgence              varchar(3)
);

create table dw_reception
(
    no_commande              varchar(255),
    statut                   varchar(255),
    commentaire              text,
    date                     timestamp(0),
    numero                   varchar(255),
    fournisseur              varchar(255),
    reference                varchar(255),
    libelle                  varchar(255),
    quantite_reference       integer,
    quantite_article_associe integer,
    code_barre_article       varchar(255),
    type_flux                varchar(255),
    quantite_recue           integer,
    quantite_a_recevoir      integer,
    code_barre_reference     varchar(255),
    reception_id             integer,
    article_id             integer,
    reception_reference_article_id             integer
);

create table dw_reception_champs_libres
(
    reception_id integer,
    libelle      varchar(255),
    valeur       text
);

create table dw_reference_article
(
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
    gestionnaires              varchar(255),
    statut                     varchar(255),
    id                         integer not null
        constraint reference_article_pk
        primary key,
    date_dernier_inventaire    timestamp(0),
    synchronisation_inventaire varchar(255)
);

create table dw_reference_article_champs_libres
(
    reference_article_id integer,
    libelle              varchar(255),
    valeur               text
);

create table dw_service
(
    type               varchar(255),
    objet              text,
    demandeur          varchar(255),
    date_creation      timestamp(0),
    date_attendue      timestamp(0),
    date_realisation   timestamp(0),
    operateur          varchar(255),
    emplacement_prise  varchar(255),
    emplacement_depose varchar(255),
    numero             varchar(255),
    statut             varchar(255),
    id                 integer not null
        constraint service_pk
        primary key,
    delta_date         float
);

create table dw_service_champs_libres
(
    service_id integer,
    libelle    varchar(255),
    valeur     text
);

create table dw_tracabilite
(
    date_mouvement              timestamp(0),
    code_colis                  varchar(255),
    type_mouvement              varchar(255),
    quantite_mouvement          integer,
    emplacement_mouvement       varchar(255),
    operateur                   varchar(255),
    mouvement_traca_id          integer,
    arrivage_id                 integer,
    acheminement_id             integer,
    commentaire_mouvement_traca text,
    piece_jointe                text
);

create table dw_urgence
(
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
    date_creation          timestamp(0),
    id                     integer not null
        constraint urgence_pk
        primary key
);

-- Nouvelle table SED
create table dw_arrivage
(
    id                     integer not null
        constraint arrivage_pk
        primary key,
    no_arrivage            varchar(255),
    date                   timestamp(0),
    nb_colis               integer,
    destinataire           varchar(255),
    fournisseur            varchar(255),
    transporteur           varchar(255),
    chauffeur              varchar(255),
    no_tracking_transporteur varchar(255),
    no_commande_bl            varchar(255),
    type                   varchar(255),
    acheteurs              varchar(255),
    urgence                varchar(255),
    douane                 varchar(255),
    congele                varchar(255),
    statut                 varchar(255),
    commentaire            text,
    utilisateur            varchar(255),
    numero_projet          varchar(255),
    business_unit          varchar(255)
);

CREATE TABLE dw_arrivage_nature_colis
(
    arrivage_id integer,
    nature_colis varchar(255),
    quantite_colis integer
);

CREATE TABLE dw_acheminement
(
    id                     integer not null
        constraint acheminement_pk
        primary key,
    numero                  varchar(255),
    date_creation           timestamp(0),
    date_validation         timestamp(0),
    date_traitement         timestamp(0),
    date_echeance_debut     date,
    date_echeance_fin       date,
    type                    varchar(255),
    transporteur            varchar(255),
    numero_tracking_transporteur varchar(255),
    numero_commande         varchar(255),
    demandeur               varchar(255),
    destinataire            varchar(255),
    code_colis              varchar(255),
    quantite_colis          integer,
    quantite_a_acheminer    integer,
    nature_colis            varchar(255),
    emplacement_prise       varchar(255),
    emplacement_depose      varchar(255),
    nb_colis                integer,
    statut                  varchar(255),
    operateur               varchar(255),
    traite_par              varchar(255),
    dernier_emplacement     varchar(255),
    date_dernier_mouvement  timestamp(0),
    urgence                 varchar(255),
    numero_projet           varchar(255),
    business_unit           varchar(255),
    delta_date              float
);

CREATE TABLE dw_demande_collecte
(
    id                   integer,
    numero               varchar(255),
    date_creation        timestamp(0),
    date_validation      timestamp(0),
    point_collecte       varchar(255),
    demandeur            varchar(255),
    objet                varchar(255),
    destination          varchar(255),
    statut               varchar(255),
    type                 varchar(255),
    commentaire          text,
    code_barre           varchar(255),
    quantite             integer
);

CREATE TABLE dw_demande_transfert
(
    id                   integer,
    numero               varchar(255),
    date_creation        timestamp(0),
    date_validation      timestamp(0),
    statut               varchar(255),
    demandeur            varchar(255),
    origine              varchar(255),
    destination          varchar(255),
    commentaire          text,
    reference            varchar(255),
    code_barre           varchar(255)
);

CREATE TABLE dw_demande_livraison
(
    id                   integer,
    numero               varchar(255),
    date_creation        timestamp(0),
    date_validation      timestamp(0),
    demandeur            varchar(255),
    type                 varchar(255),
    statut               varchar(255),
    codes_preparations   varchar(255),
    codes_livraisons     varchar(255),
    destination          varchar(255),
    commentaire          text,
    reference_article    varchar(255),
    libelle_article      varchar(255),
    code_barre_article   varchar(255),
    code_barre_reference varchar(255),
    quantite_disponible  integer,
    quantite_a_prelever  integer
);

create table dw_demande_livraison_champs_libres
(
    demande_livraison_id integer,
    libelle              varchar(255),
    valeur               text
);

CREATE TABLE dw_ordre_transfert
(
    id                   integer,
    numero_ordre         varchar(255),
    numero_demande       varchar(255),
    statut               varchar(255),
    demandeur            varchar(255),
    operateur            varchar(255),
    origine              varchar(255),
    destination          varchar(255),
    date_creation        timestamp(0),
    date_transfert       timestamp(0),
    commentaire          text,
    reference            varchar(255),
    code_barre           varchar(255),
    delta_date           float
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
    quantite_a_collecter varchar(255),
    code_barre           varchar(255),
    destination          varchar(255),
    delta_date           float
);

CREATE TABLE dw_ordre_livraison
(
    id                   integer,
    numero               varchar(255),
    statut               varchar(255),
    date_creation        timestamp(0),
    date_livraison       timestamp(0),
    date_demande         timestamp(0),
    demandeur            varchar(255),
    operateur            varchar(255),
    type                 varchar(255),
    commentaire          text,
    reference            varchar(255),
    libelle              varchar(255),
    emplacement          varchar(255),
    quantite_a_livrer    integer,
    quantite_en_stock    integer,
    code_barre           varchar(255),
    delta_date           float
);
