<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AlerteRepository")
 */
class Alerte
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
    private $AlerteNom;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $AlerteNumero;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $AlerteSeuil;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs", inversedBy="UtilisateurAlertes")
     */
    private $AlerteUtilisateur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferencesArticles", inversedBy="RefArticleAlerte")
     */
    private $AlerteRefArticle;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $SeuilAtteint;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlerteNom(): ?string
    {
        return $this->AlerteNom;
    }

    public function setAlerteNom(?string $AlerteNom): self
    {
        $this->AlerteNom = $AlerteNom;

        return $this;
    }

    public function getAlerteNumero(): ?string
    {
        return $this->AlerteNumero;
    }

    public function setAlerteNumero(?string $AlerteNumero): self
    {
        $this->AlerteNumero = $AlerteNumero;

        return $this;
    }

    public function getAlerteSeuil(): ?int
    {
        return $this->AlerteSeuil;
    }

    public function setAlerteSeuil(?int $AlerteSeuil): self
    {
        $this->AlerteSeuil = $AlerteSeuil;

        return $this;
    }

    public function getAlerteUtilisateur(): ?Utilisateurs
    {
        return $this->AlerteUtilisateur;
    }

    public function setAlerteUtilisateur(?Utilisateurs $AlerteUtilisateur): self
    {
        $this->AlerteUtilisateur = $AlerteUtilisateur;

        return $this;
    }

    public function getAlerteRefArticle(): ?ReferencesArticles
    {
        return $this->AlerteRefArticle;
    }

    public function setAlerteRefArticle(?ReferencesArticles $AlerteRefArticle): self
    {
        $this->AlerteRefArticle = $AlerteRefArticle;

        return $this;
    }

    public function getSeuilAtteint(): ?bool
    {
        return $this->SeuilAtteint;
    }

    public function setSeuilAtteint(?bool $SeuilAtteint): self
    {
        $this->SeuilAtteint = $SeuilAtteint;

        return $this;
    }

}
