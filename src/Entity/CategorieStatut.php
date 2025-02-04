<?php

namespace App\Entity;

use App\Repository\CategorieStatutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieStatutRepository::class)]
class CategorieStatut {

    const REFERENCE_ARTICLE = 'referenceArticle';
    const ARTICLE = 'article';
    const DEM_COLLECTE = 'collecte';
    const ORDRE_COLLECTE = 'ordreCollecte';
    const DEM_LIVRAISON = 'demande';
    const ORDRE_LIVRAISON = 'livraison';
    const PREPARATION = 'preparation';
    const RECEPTION = 'reception';
    const HANDLING = 'service';
    const ARRIVAGE = 'arrivage';
    const MVT_TRACA = 'mouvement_traca';
    const TRACKING_DELAY_RECORD = 'tracking_delay_record';
    const MVT_STOCK = 'mouvement_stock';
    const DISPUTE_ARR = 'litige arrivage';
    const LITIGE_RECEPT = 'litige reception';
    const DISPATCH = 'acheminement';
    const TRANSFER_REQUEST = 'demande de transfert';
    const PURCHASE_REQUEST = 'demande d\'achat';
    const TRANSFER_ORDER = 'ordre de transfert';
    const TRANSPORT_REQUEST_DELIVERY = 'demande de transport livraison';
    const TRANSPORT_REQUEST_COLLECT = 'demande de transport collecte';
    const TRANSPORT_ORDER_DELIVERY = 'ordre de transport livraison';
    const TRANSPORT_ORDER_COLLECT = 'ordre de transport collecte';
    const TRANSPORT_ROUND = 'tournée';
    const SHIPPING_REQUEST = 'expédition';
    const IMPORT = 'import';
    const EXPORT = 'export';
    const PRODUCTION = 'production';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private ?string $nom = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getNom(): ?string {
        return $this->nom;
    }

    public function setNom(string $nom): self {
        $this->nom = $nom;

        return $this;
    }
}
