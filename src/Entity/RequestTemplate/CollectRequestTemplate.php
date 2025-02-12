<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\CollectRequestTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CollectRequestTemplateRepository::class)]
class CollectRequestTemplate extends RequestTemplate {

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $subject = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class, inversedBy: 'collectRequestTemplates')]
    private ?Emplacement $collectPoint = null;

    #[ORM\Column(type: 'integer')]
    private ?int $destination = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(targetEntity: RequestTemplateLine::class, mappedBy: 'collectRequestTemplate', cascade: ["remove"])]
    private Collection $lines;

    public function __construct() {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    public function getSubject(): ?string {
        return $this->subject;
    }

    public function setSubject(string $subject): self {
        $this->subject = $subject;

        return $this;
    }

    public function setCollectPoint(?Emplacement $collectPoint): self {
        if($this->collectPoint && $this->collectPoint !== $collectPoint) {
            $this->collectPoint->removeCollectRequestTemplate($this);
        }
        $this->collectPoint = $collectPoint;
        if($collectPoint) {
            $collectPoint->addCollectRequestTemplate($this);
        }

        return $this;
    }

    public function getCollectPoint(): ?Emplacement {
        return $this->collectPoint;
    }

    public function getDestination(): ?int {
        return $this->destination;
    }

    public function isStock(): ?bool {
        return $this->destination == 1;
    }

    public function isDestruct(): ?bool {
        return $this->destination == 0;
    }

    public function setDestination(int $destination): self {
        $this->destination = $destination;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection|RequestTemplateLine[]
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ReferenceArticle $ref): self {
        if(!$this->lines->contains($ref)) {
            $this->lines[] = $ref;
        }

        return $this;
    }

    public function removeLine(ReferenceArticle $ref): self {
        $this->lines->removeElement($ref);

        return $this;
    }

}
