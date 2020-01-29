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

    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_LIVRE = 'livré';
    const STATUT_INCOMPLETE = 'partiellement livré';

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
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="livraisons")
     */
    private $destination;
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="livraisons")
     */
    private $statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateFin;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="livraisons")
     */
    private $utilisateur;

    /**
     * @var Preparation|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="livraisons")
     */
    private $preparation;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="livraisonOrder")
	 */
	private $mouvements;


    public function __construct()
    {
        $this->mouvements = new ArrayCollection();
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

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }
    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;
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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

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

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setLivraisonOrder($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getLivraisonOrder() === $this) {
                $mouvement->setLivraisonOrder(null);
            }
        }

        return $this;
    }
}
