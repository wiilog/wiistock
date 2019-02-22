<?php
namespace App\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\Entity(repositoryClass="App\Repository\LivraisonRepository")
 */
class Livraison
{
    const CATEGORIE = 'livraison';
    const STATUT_EN_COURS = 'en cours de livraison';
    const STATUT_DEMANDE = 'demande de livraison';
    const STATUT_TERMINE = 'livraison terminée';

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
     * @ORM\ManyToOne(targetEntity="App\Entity\emplacement", inversedBy="livraisons")
     */
    private $destination;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="livraison")
     */
    private $demande;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statuts", inversedBy="livraisons")
     */
    private $Statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs", inversedBy="livraisons")
     */
    private $utilisateur;
    public function __construct()
    {
        $this->demande = new ArrayCollection();
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
    public function getDestination(): ?emplacement
    {
        return $this->destination;
    }
    public function setDestination(?emplacement $destination): self
    {
        $this->destination = $destination;
        return $this;
    }
    /**
     * @return Collection|Demande[]
     */
    public function getDemande(): Collection
    {
        return $this->demande;
    }
    public function addDemande(Demande $demande): self
    {
        if (!$this->demande->contains($demande)) {
            $this->demande[] = $demande;
            $demande->setLivraison($this);
        }
        return $this;
    }
    public function removeDemande(Demande $demande): self
    {
        if ($this->demande->contains($demande)) {
            $this->demande->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getLivraison() === $this) {
                $demande->setLivraison(null);
            }
        }
        return $this;
    }
    public function getStatut(): ?Statuts
    {
        return $this->Statut;
    }
    public function setStatut(?Statuts $Statut): self
    {
        $this->Statut = $Statut;
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

    public function getUtilisateur(): ?Utilisateurs
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateurs $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}