<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\PreparationOrder\Preparation;
use App\Repository\LivraisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LivraisonRepository::class)]
class Livraison {

    const CATEGORIE = 'livraison';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_LIVRE = 'livré';
    const STATUT_INCOMPLETE = 'partiellement livré';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $numero;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'livraisons')]
    private $destination;

    /**
     * @var Statut
     */
    #[ORM\ManyToOne(targetEntity: Statut::class, inversedBy: 'livraisons')]
    private $statut;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $date;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $dateFin;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'livraisons')]
    private $utilisateur;

    #[ORM\OneToOne(targetEntity: 'App\Entity\PreparationOrder\Preparation', inversedBy: 'livraison')]
    private ?Preparation $preparation = null;

    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'livraisonOrder')]
    private $mouvements;

    public function __construct() {
        $this->mouvements = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumero(): ?string {
        return $this->numero;
    }

    public function setNumero(?string $numero): self {
        $this->numero = $numero;
        return $this;
    }

    public function getDestination(): ?emplacement {
        return $this->destination;
    }

    public function setDestination(?emplacement $destination): self {
        $this->destination = $destination;
        return $this;
    }

    /**
     * @return Demande|null
     */
    public function getDemande(): ?Demande {
        return isset($this->preparation)
            ? $this->preparation->getDemande()
            : null;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self {
        $this->date = $date;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        $this->preparation = $preparation;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self {
        if(!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setLivraisonOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getLivraisonOrder() === $this) {
                $mouvement->setLivraisonOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isCompleted(): bool {
        return (
            isset($this->statut)
            && in_array($this->statut->getCode(), [Livraison::STATUT_LIVRE, Livraison::STATUT_INCOMPLETE])
        );
    }

}
