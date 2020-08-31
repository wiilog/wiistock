<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CategorieStatutRepository")
 */
class CategorieStatut
{
    const REFERENCE_ARTICLE = 'referenceArticle';
    const ARTICLE = 'article';
    const DEM_COLLECTE = 'collecte';
    const ORDRE_COLLECTE = 'ordreCollecte';
    const DEM_LIVRAISON = 'demande';
    const ORDRE_LIVRAISON = 'livraison';
    const PREPARATION = 'preparation';
    const RECEPTION = 'reception';
    const MANUTENTION = 'manutention';
    const ARRIVAGE = 'arrivage';
    const MVT_TRACA = 'mouvement_traca';
    const MVT_STOCK = 'mouvement_stock';
    const LITIGE_ARR = 'litige arrivage';
    const LITIGE_RECEPT = 'litige reception';
    const DISPATCH = 'acheminement';
    const IMPORT = 'import';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $nom;

    /**
     * @ORM\OneToMany(targetEntity="Statut", mappedBy="categorie")
     */
    private $statuts;


    public function __construct()
    {
        $this->statuts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection|CategorieStatut[]
     */
    public function getStatuts(): Collection
    {
        return $this->statuts;
    }

    public function addStatut(Statut $statut): self
    {
        if (!$this->statuts->contains($statut)) {
            $this->statuts[] = $statut;
            $statut->setCategorie($this);
        }

        return $this;
    }

    public function removeStatut(Statut $statut): self
    {
        if ($this->statuts->contains($statut)) {
            $this->statuts->removeElement($statut);
            // set the owning side to null (unless already changed)
            if ($statut->getCategorie() === $this) {
                $statut->setCategorie(null);
            }
        }

        return $this;
    }
}
