<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FiltreSupRepository")
 */
class FiltreSup
{
	const FIELD_DATE_MIN = 'dateMin';
	const FIELD_DATE_MAX = 'dateMax';
	const FIELD_STATUT = 'statut';
    const FIELD_USERS = 'utilisateurs';
    const FIELD_DECLARANTS = 'declarants';
	const FIELD_CARRIERS = 'carriers';
	const FIELD_PROVIDERS = 'providers';
	const FIELD_TYPE = 'type';
	const FIELD_EMPLACEMENT = 'emplacement';
	const FIELD_COLIS = 'colis';
	const FIELD_REFERENCE = 'reference';
	const FIELD_DEM_COLLECTE = 'demCollecte';
	const FIELD_DEMANDE = 'demande';
	const FIELD_EMERGENCY = 'emergency';
	const FIELD_EMERGENCY_MULTIPLE = 'emergencyMultiple';
	const FIELD_ANOMALY = 'anomaly';
	const FIELD_ARRIVAGE_STRING = 'arrivage_string';
	const FIELD_RECEPTION_STRING = 'reception_string';
	const FIELD_COMMANDE = 'commande';
	const FIELD_LITIGE_ORIGIN = 'litigeOrigin';
	const FIELD_LITIGE_DISPUTE_NUMBER = 'disputeNumber';
	const FIELD_NUM_ARRIVAGE = 'numArrivage';
	const FIELD_NATURES = 'natures';
	const FIELD_CUSTOMS = 'customs';
	const FIELD_FROZEN = 'frozen';
	const FIELD_STATUS_ENTITY = 'statusEntity';
	const FIELD_MULTIPLE_TYPES = 'multipleTypes';
    const FIELD_RECEIVERS = 'receivers';
    const FIELD_OPERATORS = 'operators';
	const FIELD_REQUESTERS = 'requesters';
	const FIELD_DISPATCH_NUMBER = 'dispatchNumber';

	const PAGE_TRANSFER_REQUEST = 'rtransfer';
	const PAGE_TRANSFER_ORDER = 'otransfer';
	const PAGE_DEM_COLLECTE = 'dcollecte';
	const PAGE_DEM_LIVRAISON = 'dlivraison';
    const PAGE_HAND = 'handling';
    const PAGE_RECEPTION = 'reception';
	const PAGE_ORDRE_COLLECTE = 'ocollecte';
	const PAGE_ORDRE_LIVRAISON = 'olivraison';
    const PAGE_PREPA = 'prépa';
    const PAGE_PACK = 'pack';
	const PAGE_ARRIVAGE = 'arrivage';
	const PAGE_MVT_STOCK = 'mvt_stock';
	const PAGE_MVT_TRACA = 'mvt_traca';
	const PAGE_DISPATCH = 'acheminement';
    const PAGE_STATUS = 'status';
	const PAGE_INV_ENTRIES = 'inv_entries';
	const PAGE_INV_MISSIONS = 'inv_missions';
	const PAGE_INV_SHOW_MISSION = 'inv_mission_show';
	const PAGE_LITIGE = 'litige';
	const PAGE_RCPT_TRACA = 'reception_traca';
	const PAGE_ARTICLE = 'article';
    const PAGE_URGENCES = 'urgences';
    const PAGE_ALERTE = 'alerte';
    const PAGE_ENCOURS = 'encours';
    const PAGE_IMPORT = 'import';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

	/**
	 * @ORM\Column(type="string", length=32)
	 */
	private $field;

	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $value;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="filtresSup")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $user;

	/**
	 * @ORM\Column(type="string", length=64)
	 */
	private $page;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function setPage(string $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }
}
