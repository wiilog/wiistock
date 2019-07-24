<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MouvementTracaRepository")
 */
class MouvementTraca
{

    const TYPE_PRISE = 'prise';
    const TYPE_DEPOSE = 'depose';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $refArticle;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $refEmplacement;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $operateur;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRefArticle(): ?string
    {
        return $this->refArticle;
    }

    public function setRefArticle(?string $refArticle): self
    {
        $this->refArticle = $refArticle;

        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(?string $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getRefEmplacement(): ?string
    {
        return $this->refEmplacement;
    }

    public function setRefEmplacement(?string $refEmplacement): self
    {
        $this->refEmplacement = $refEmplacement;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOperateur(): ?string
    {
        return $this->operateur;
    }

    public function setOperateur(?string $operateur): self
    {
        $this->operateur = $operateur;

        return $this;
    }
}
