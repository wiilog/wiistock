<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandeRepository")
 */
class Demande
{
    const CATEGORIE = 'demande';
    const STATUT_EN_COURS = 'en cours';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_TERMINEE = 'terminée';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="demandes")
     */
    private $destination;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="demandes")
     */
    private $utilisateur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="demandes")
     */
    private $preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Livraison", inversedBy="demande")
     */
    private $livraison;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="demandes")
     */
    private $statut;

//    /**
//     * @ORM\Column(type="datetime", nullable=true)
//     */
//    private $DateAttendu;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticle", mappedBy="demande")
     */
    private $ligneArticle;

    public function __construct()
    {
        $this->ligneArticle = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDestination(): ?Emplacement
    {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        $this->preparation = $preparation;

        return $this;
    }

    public function getLivraison(): ?Livraison
    {
        return $this->livraison;
    }

    public function setLivraison(?Livraison $livraison): self
    {
        $this->livraison = $livraison;

        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

//    public function getDateAttendu(): ?\DateTimeInterface
//    {
//        return $this->DateAttendu;
//    }
//
//    public function setDateAttendu(?\DateTimeInterface $DateAttendu): self
//    {
//        $this->DateAttendu = $DateAttendu;
//
//        return $this;
//    }

    /**
     * @return Collection|LigneArticle[]
     */
    public function getLigneArticle(): Collection
    {
        return $this->ligneArticle;
    }

    public function addLigneArticle(LigneArticle $ligneArticle): self
    {
        if (!$this->ligneArticle->contains($ligneArticle)) {
            $this->ligneArticle[] = $ligneArticle;
            $ligneArticle->setDemande($this);
        }

        return $this;
    }

    public function removeLigneArticle(LigneArticle $ligneArticle): self
    {
        if ($this->ligneArticle->contains($ligneArticle)) {
            $this->ligneArticle->removeElement($ligneArticle);
            // set the owning side to null (unless already changed)
            if ($ligneArticle->getDemande() === $this) {
                $ligneArticle->setDemande(null);
            }
        }

        return $this;
    }


}
