<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionsRepository")
 */
class Receptions
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $statut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     * @ORM\JoinColumn(nullable=false)
     */
    private $quai_reception;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $fournisseur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CommandesFournisseurs")
     */
    private $commande_fournisseur;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Historiques", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Ordres", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $ordre;

    public function getId()
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getQuaiReception(): ?Quais
    {
        return $this->quai_reception;
    }

    public function setQuaiReception(?Quais $quai_reception): self
    {
        $this->quai_reception = $quai_reception;

        return $this;
    }

    public function getDateReception(): ?\DateTimeInterface
    {
        return $this->date_reception;
    }

    public function setDateReception(\DateTimeInterface $date_reception): self
    {
        $this->date_reception = $date_reception;

        return $this;
    }

    public function getFournisseur(): ?Fournisseurs
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseurs $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getCommandeFournisseur(): ?CommandesFournisseurs
    {
        return $this->commande_fournisseur;
    }

    public function setCommandeFournisseur(?CommandesFournisseurs $commande_fournisseur): self
    {
        $this->commande_fournisseur = $commande_fournisseur;

        return $this;
    }

    public function getHistorique(): ?Historiques
    {
        return $this->historique;
    }

    public function setHistorique(Historiques $historique): self
    {
        $this->historique = $historique;

        return $this;
    }

    public function getOrdre(): ?Ordres
    {
        return $this->ordre;
    }

    public function setOrdre(Ordres $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }
}
