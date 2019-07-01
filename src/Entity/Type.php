<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TypeRepository")
 */
class Type
{
    // types de la catégorie articles et références CEA
    const LABEL_CSP = 'CSP';
    const LABEL_PDT = 'PDT';
    const LABEL_SILI = 'SILI';
    const LABEL_SILI_EXT = 'SILI-ext';
    const LABEL_SILI_INT = 'SILI-int';
    const LABEL_MOB = 'MOB';
    const LABEL_SLUGCIBLE = 'SLUGCIBLE';
    // type de la catégorie réception
    const LABEL_RECEPTION = 'RECEPTION';
    // types de la catégorie litige
    const LABEL_MANQUE_BL = 'manque BL';
    const LABEL_MANQUE_INFO_BL = 'manque info BL';
    const LABEL_ECART_QTE = 'écart quantité + ou -';
    const LABEL_ECART_QUALITE = 'écart qualité';
    const LABEL_PB_COMMANDE = 'problème de commande';
    const LABEL_DEST_NON_IDENT = 'destinataire non identifiable';

    //commentaires liés aux types de litige
//    const MESSAGE_MANQUE_BL = 'Nous venons de recevoir un colis à votre attention sans Bordereau de Livraison.
//        Dans l’attente du document votre colis est placé en litige. Nous rappelons que le BL doit être émis au titre
//        d’une commande ou à titre gracieux.';
//    const MESSAGE_MANQUE_INFO_BL ='Nous venons de recevoir un colis à votre attention. Pour pouvoir finaliser la réception
//        nous avons besoin d’un BL au titre d’une commande ou à titre gracieux.
//        Dans l’attente du document votre colis est placé en litige.';
//    const MESSAGE_ECART_QUALITE = 'Nous venons de recevoir un colis à votre attention, nous avons constaté un problème qualité.
//        « décrire le problème qualité et joindre une ou plusieurs photos du problème constaté »
//        Dans l’attente de vos instructions le colis est placé en zone litige';
//    const MESSAGE_PB_COMMANDE = 'Nous venons de recevoir un colis au titre de la commande XXX.
//        « décrire le problème constaté »
//        Dans l’attente de vos instructions le colis est placé en zone litige';
//    const MESSAGE_DEST_NON_IDENT = 'Nous venons de recevoir un colis à titre gracieux, nous sommes dans l’incapacité
//        d’ identifier un destinataire.
//        Dans l’attente de vos instructions le colis est placé en zone litige';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ChampsLibre", mappedBy="type")
     */
    private $champsLibres;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="type")
     */
    private $referenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="type")
     */
    private $articles;


    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryType", inversedBy="types")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="type")
     */
    private $receptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Litige", mappedBy="type")
     */
    private $litiges;

    public function __construct()
    {
        $this->champsLibres = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->litiges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|ChampsLibre[]
     */
    public function getChampsLibres(): Collection
    {
        return $this->champsLibres;
    }

    public function addChampsLibre(ChampsLibre $champsLibre): self
    {
        if (!$this->champsLibres->contains($champsLibre)) {
            $this->champsLibres[] = $champsLibre;
            $champsLibre->setType($this);
        }

        return $this;
    }

    public function removeChampsLibre(ChampsLibre $champsLibre): self
    {
        if ($this->champsLibres->contains($champsLibre)) {
            $this->champsLibres->removeElement($champsLibre);
            // set the owning side to null (unless already changed)
            if ($champsLibre->getType() === $this) {
                $champsLibre->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection
    {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setType($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getType() === $this) {
                $referenceArticle->setType(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?CategoryType
    {
        return $this->category;
    }

    public function setCategory(?CategoryType $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setType($this);
        }

        return $this;
    }
    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getType() === $this) {
                $article->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setType($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getType() === $this) {
                $reception->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Litige[]
     */
    public function getLitiges(): Collection
    {
        return $this->litiges;
    }

    public function addCommentaire(Litige $commentaire): self
    {
        if (!$this->litiges->contains($commentaire)) {
            $this->litiges[] = $commentaire;
            $commentaire->setType($this);
        }

        return $this;
    }

    public function removeCommentaire(Litige $commentaire): self
    {
        if ($this->litiges->contains($commentaire)) {
            $this->litiges->removeElement($commentaire);
            // set the owning side to null (unless already changed)
            if ($commentaire->getType() === $this) {
                $commentaire->setType(null);
            }
        }

        return $this;
    }

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->setType($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            // set the owning side to null (unless already changed)
            if ($litige->getType() === $this) {
                $litige->setType(null);
            }
        }

        return $this;
    }
}
