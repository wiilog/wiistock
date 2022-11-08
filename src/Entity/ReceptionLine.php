<?php

namespace App\Entity;

use App\Repository\ReceptionLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;


#[ORM\Entity(repositoryClass: ReceptionLineRepository::class)]
class ReceptionLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'lines')]
    private ?Reception $reception = null;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Pack $pack = null;

    #[ORM\OneToMany(mappedBy: 'receptionLine', targetEntity: ReceptionReferenceArticle::class)]
    private Collection $receptionReferenceArticles;

    public function __construct() {
        $this->receptionReferenceArticles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getReception(): ?Reception {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self {
        if($this->reception && $this->reception !== $reception) {
            $this->reception->removeLine($this);
        }
        $this->reception = $reception;
        $reception?->addLine($this);

        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        $this->pack = $pack;
        return $this;
    }

    public function hasPack(): bool {
        return $this->pack !== null;
    }

    /**
     * @return Collection<ReceptionReferenceArticle>
     */
    public function getReceptionReferenceArticles(): Collection {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self {
        if(!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setReceptionLine($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self {
        if($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if($receptionReferenceArticle->getReceptionLine() === $this) {
                $receptionReferenceArticle->setReceptionLine(null);
            }
        }

        return $this;
    }

    public function getReceptionReferenceArticle(ReferenceArticle $referenceArticle,
                                                 ?string $orderNumber): ?ReceptionReferenceArticle {
        return Stream::from($this->receptionReferenceArticles->toArray())
            ->find(fn(ReceptionReferenceArticle $receptionReferenceArticle) => (
                $receptionReferenceArticle->getReferenceArticle()?->getId() === $referenceArticle->getId()
                && $receptionReferenceArticle->getCommande() === $orderNumber
            ));
    }
}
