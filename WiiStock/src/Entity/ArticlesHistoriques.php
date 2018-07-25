<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticlesHistoriquesRepository")
 */
class ArticlesHistoriques
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Historiques")
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Articles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $article;

    public function getId()
    {
        return $this->id;
    }

    public function getHistorique(): ?Historiques
    {
        return $this->historique;
    }

    public function setHistorique(?Historiques $historique): self
    {
        $this->historique = $historique;

        return $this;
    }

    public function getArticle(): ?Articles
    {
        return $this->article;
    }

    public function setArticle(?Articles $article): self
    {
        $this->article = $article;

        return $this;
    }
}
