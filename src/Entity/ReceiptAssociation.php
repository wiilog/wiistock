<?php

namespace App\Entity;

use App\Helper\FormatHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceiptAssociationRepository")
 */
class ReceiptAssociation
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $creationDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="receptionsTraca")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity=Pack::class, inversedBy="receiptAssociations")
     */
    private $pack;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $receptionNumber;

    public function __construct()
    {
        $this->packs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(?\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }


    public function serialize(): array {
        return [
            'creationDate' => FormatHelper::datetime($this->getCreationDate()),
            'pack' => $this->getPack() ? $this->getPack()->getCode() : '',
            'lastLocation' => $this->getPack()
                ? ($this->getPack()->getLastDrop()
                    ? FormatHelper::location($this->getPack()->getLastDrop()->getEmplacement())
                    : '')
                : '',
            'lastTrackingDate' => $this->getPack()
                ? ($this->getPack()->getLastTracking()
                    ? FormatHelper::datetime($this->getPack()->getLastTracking()->getDatetime())
                    : '')
                : '',
            'reception' => $this->getReceptionNumber() ?? '',
            'user' => FormatHelper::user($this->getUser()),
        ];
    }

    public function getReceptionNumber(): ?string
    {
        return $this->receptionNumber;
    }

    public function setReceptionNumber(?string $receptionNumber): self
    {
        $this->receptionNumber = $receptionNumber;

        return $this;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        $this->pack = $pack;

        return $this;
    }
}
