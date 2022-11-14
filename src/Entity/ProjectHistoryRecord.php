<?php


namespace App\Entity;


use App\Repository\ProjectHistoryRecordRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

#[ORM\Entity(repositoryClass: ProjectHistoryRecordRepository::class)]
class ProjectHistoryRecord {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'projectHistoryRecords')]
    private ?Pack $pack = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack !== $pack) {
            $this->pack->removeProjectHistoryRecord($this);
        }
        $this->pack = $pack;
        $pack?->addProjectHistoryRecord($this);

        return $this;
    }

    public function getProject(): ?Project {
        return $this->project;
    }

    public function setProject(?Project $project): self {
        $this->project = $project;

        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        if($this->article && $this->article !== $article) {
            $this->article->removeProjectHistoryRecord($this);
        }
        $this->article = $article;
        $article?->addProjectHistoryRecord($this);

        return $this;
    }

}
