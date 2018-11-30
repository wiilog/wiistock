<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_au_plus_tot;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_au_plus_tard;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_prevue;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseurs", inversedBy="receptions")
     */
    private $fournisseur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Contenu", mappedBy="reception")
     */
    private $contenu;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Historiques", mappedBy="reception")
     */
    private $historiques;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reference_SAP;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom_CEA;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $prenom_CEA;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mail_CEA;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code_ref_transporteur;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom_transporteur;

    public function __construct()
    {
        $this->contenu = new ArrayCollection();
        $this->historiques = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateAuPlusTot(): ?\DateTimeInterface
    {
        return $this->date_au_plus_tot;
    }

    public function setDateAuPlusTot(?\DateTimeInterface $date_au_plus_tot): self
    {
        $this->date_au_plus_tot = $date_au_plus_tot;

        return $this;
    }

    public function getDateAuPlusTard(): ?\DateTimeInterface
    {
        return $this->date_au_plus_tard;
    }

    public function setDateAuPlusTard(?\DateTimeInterface $date_au_plus_tard): self
    {
        $this->date_au_plus_tard = $date_au_plus_tard;

        return $this;
    }

    public function getDatePrevue(): ?\DateTimeInterface
    {
        return $this->date_prevue;
    }

    public function setDatePrevue(?\DateTimeInterface $date_prevue): self
    {
        $this->date_prevue = $date_prevue;

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

    /**
     * @return Collection|Contenu[]
     */
    public function getContenu(): Collection
    {
        return $this->contenu;
    }

    public function addContenu(Contenu $contenu): self
    {
        if (!$this->contenu->contains($contenu)) {
            $this->contenu[] = $contenu;
            $contenu->setReception($this);
        }

        return $this;
    }

    public function removeContenu(Contenu $contenu): self
    {
        if ($this->contenu->contains($contenu)) {
            $this->contenu->removeElement($contenu);
            // set the owning side to null (unless already changed)
            if ($contenu->getReception() === $this) {
                $contenu->setReception(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Historiques[]
     */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    public function addHistoriques(Historiques $historique): self
    {
        if (!$this->historiques->contains($historique)) {
            $this->historiques[] = $historique;
            $historique->setReception($this);
        }

        return $this;
    }

    public function removeHistoriques(Historiques $historique): self
    {
        if ($this->historiques->contains($historique)) {
            $this->historiques->removeElement($historique);
            // set the owning side to null (unless already changed)
            if ($historique->getReception() === $this) {
                $historique->setReception(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getReferenceSAP(): ?string
    {
        return $this->reference_SAP;
    }

    public function setReferenceSAP(?string $reference_SAP): self
    {
        $this->reference_SAP = $reference_SAP;

        return $this;
    }

    public function getNomCEA(): ?string
    {
        return $this->nom_CEA;
    }

    public function setNomCEA(?string $nom_CEA): self
    {
        $this->nom_CEA = $nom_CEA;

        return $this;
    }

    public function getPrenomCEA(): ?string
    {
        return $this->prenom_CEA;
    }

    public function setPrenomCEA(?string $prenom_CEA): self
    {
        $this->prenom_CEA = $prenom_CEA;

        return $this;
    }

    public function getMailCEA(): ?string
    {
        return $this->mail_CEA;
    }

    public function setMailCEA(?string $mail_CEA): self
    {
        $this->mail_CEA = $mail_CEA;

        return $this;
    }

    public function getCodeRefTransporteur(): ?string
    {
        return $this->code_ref_transporteur;
    }

    public function setCodeRefTransporteur(?string $code_ref_transporteur): self
    {
        $this->code_ref_transporteur = $code_ref_transporteur;

        return $this;
    }

    public function getNomTransporteur(): ?string
    {
        return $this->nom_transporteur;
    }

    public function setNomTransporteur(?string $nom_transporteur): self
    {
        $this->nom_transporteur = $nom_transporteur;

        return $this;
    }
}
