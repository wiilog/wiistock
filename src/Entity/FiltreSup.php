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
	const FIELD_CARRIERS = 'transporteurs';
	const FIELD_PROVIDERS = 'fournisseurs';
	const FIELD_TYPE = 'type';
	const FIELD_EMPLACEMENT = 'emplacement';
	const FIELD_COLIS = 'colis';

	const PAGE_DEM_COLLECTE = 'dcollecte';
	const PAGE_DEM_LIVRAISON = 'dlivraison';
	const PAGE_MANUT = 'manutention';
	const PAGE_ORDRE_COLLECTE = 'ocollecte';
	const PAGE_ORDRE_LIVRAISON = 'olivraison';
	const PAGE_PREPA = 'prépa';
	const PAGE_ARRIVAGE = 'arrivage';
	const PAGE_MVT_STOCK = 'mvt_stock';
	const PAGE_MVT_TRACA = 'mvt_traca';

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
