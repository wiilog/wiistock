<?php

namespace App\Entity;

use App\Entity\IOT\PairedEntity;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorMessageTrait;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\OrdreCollecteRepository')]
class OrdreCollecte implements PairedEntity {

    use SensorMessageTrait;

    const CATEGORIE = 'ordreCollecte';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_TRAITE = 'traité';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $statut;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'ordreCollectes')]
    #[ORM\JoinColumn(nullable: true)]
    private $utilisateur;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private $numero;

    #[ORM\Column(type: 'datetime')]
    private $date;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $treatingDate;

    #[ORM\ManyToOne(targetEntity: Collecte::class, inversedBy: 'ordreCollecte')]
    private $demandeCollecte;

    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'ordreCollecte')]
    private $articles;

    #[ORM\OneToMany(targetEntity: OrdreCollecteReference::class, mappedBy: 'ordreCollecte')]
    private $ordreCollecteReferences;

    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'collecteOrder')]
    private $mouvements;

    #[ORM\OneToMany(targetEntity: Pairing::class, mappedBy: 'collectOrder', cascade: ['remove'])]
    private Collection $pairings;

    public function __construct() {
        $this->articles = new ArrayCollection();
        $this->ordreCollecteReferences = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->pairings = new ArrayCollection();
        $this->sensorMessages = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getStatut(): ?Statut {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self {
        $this->statut = $statut;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getNumero(): ?string {
        return $this->numero;
    }

    public function setNumero(string $numero): self {
        $this->numero = $numero;

        return $this;
    }

    public function getDate(): ?DateTimeInterface {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self {
        $this->date = $date;

        return $this;
    }

    public function getTreatingDate(): ?DateTimeInterface {
        return $this->treatingDate;
    }

    public function setTreatingDate(DateTimeInterface $date): self {
        $this->treatingDate = $date;

        return $this;
    }

    public function getDemandeCollecte(): ?Collecte {
        return $this->demandeCollecte;
    }

    public function setDemandeCollecte(?Collecte $demandeCollecte): self {
        $this->demandeCollecte = $demandeCollecte;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if(!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->addOrdreCollecte($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        if($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            $article->removeOrdreCollecte($this);
        }

        return $this;
    }

    /**
     * @return Collection|OrdreCollecteReference[]
     */
    public function getOrdreCollecteReferences(): Collection {
        return $this->ordreCollecteReferences;
    }

    public function addOrdreCollecteReference(OrdreCollecteReference $ordreCollecteReference): self {
        if(!$this->ordreCollecteReferences->contains($ordreCollecteReference)) {
            $this->ordreCollecteReferences[] = $ordreCollecteReference;
            $ordreCollecteReference->setOrdreCollecte($this);
        }

        return $this;
    }

    public function removeOrdreCollecteReference(OrdreCollecteReference $ordreCollecteReference): self {
        if($this->ordreCollecteReferences->contains($ordreCollecteReference)) {
            $this->ordreCollecteReferences->removeElement($ordreCollecteReference);
            // set the owning side to null (unless already changed)
            if($ordreCollecteReference->getOrdreCollecte() === $this) {
                $ordreCollecteReference->setOrdreCollecte(null);
            }
        }

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
            $mouvement->setCollecteOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self {
        if($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if($mouvement->getCollecteOrder() === $this) {
                $mouvement->setCollecteOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Pairing[]
     */
    public function getPairings(): Collection {
        return $this->pairings;
    }

    public function getActivePairing(): ?Pairing {
        $criteria = Criteria::create();
        return $this->pairings
            ->matching(
                $criteria
                    ->andWhere(Criteria::expr()->eq('active', true))
                    ->setMaxResults(1)
            )
            ->first() ?: null;
    }

    public function addPairing(Pairing $pairing): self {
        if(!$this->pairings->contains($pairing)) {
            $this->pairings[] = $pairing;
            $pairing->setCollectOrder($this);
        }

        return $this;
    }

    public function removePairing(Pairing $pairing): self {
        if($this->pairings->removeElement($pairing)) {
            // set the owning side to null (unless already changed)
            if($pairing->getCollectOrder() === $this) {
                $pairing->setCollectOrder(null);
            }
        }

        return $this;
    }

    public function __toString() {
        return $this->numero;
    }

}
