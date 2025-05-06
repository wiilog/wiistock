<?php

namespace App\Entity;

use App\Entity\Fields\FixedFieldEnum;
use App\Repository\FournisseurRepository;
use App\Service\FormatService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FournisseurRepository::class)]
class Fournisseur {

    const REF_A_DEFINIR = 'A DEFINIR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private ?string $codeReference = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $nom = null;

    #[ORM\OneToMany(mappedBy: 'fournisseur', targetEntity: ArticleFournisseur::class)]
    private Collection $articlesFournisseur;

    #[ORM\OneToMany(mappedBy: 'fournisseur', targetEntity: ReceptionReferenceArticle::class)]
    private Collection $receptionReferenceArticles;

    #[ORM\OneToMany(mappedBy: 'fournisseur', targetEntity: Arrivage::class)]
    private Collection $arrivages;

    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: PurchaseRequestLine::class)]
    private Collection $purchaseRequestLines;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $urgent = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $possibleCustoms = false;

    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: Urgence::class)]
    private Collection $urgences; // TODO WIIS-12734

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $receiver = null;

    public function __construct() {
        $this->articlesFournisseur = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
        $this->purchaseRequestLines = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCodeReference(): ?string {
        return $this->codeReference;
    }

    public function setCodeReference(?string $codeReference): self {
        $this->codeReference = $codeReference;

        return $this;
    }

    public function getNom(): ?string {
        return $this->nom;
    }

    public function setNom(?string $nom): self {
        $this->nom = $nom;

        return $this;
    }

    public function __toString() {
        return $this->nom;
    }

    /**
     * @return Collection|ArticleFournisseur[]
     */
    public function getArticlesFournisseur(): Collection {
        return $this->articlesFournisseur;
    }

    /**
     * @return Collection|ReceptionReferenceArticle[]
     */
    public function getReceptionReferenceArticles(): Collection {
        return $this->receptionReferenceArticles;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivages(): Collection {
        return $this->arrivages;
    }

    public function addArrivage(Arrivage $arrivage): self {
        if(!$this->arrivages->contains($arrivage)) {
            $this->arrivages[] = $arrivage;
            $arrivage->setFournisseur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self {
        if($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if($arrivage->getFournisseur() === $this) {
                $arrivage->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|PurchaseRequestLine[]
     */
    public function getPurchaseRequestLines(): Collection {
        return $this->purchaseRequestLines;
    }

    public function addPurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if(!$this->purchaseRequestLines->contains($purchaseRequestLine)) {
            $this->purchaseRequestLines[] = $purchaseRequestLine;
            $purchaseRequestLine->setSupplier($this);
        }

        return $this;
    }

    public function removePurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if($this->purchaseRequestLines->removeElement($purchaseRequestLine)) {
            if($purchaseRequestLine->getSupplier() === $this) {
                $purchaseRequestLine->setSupplier(null);
            }
        }

        return $this;
    }

    public function setPurchaseRequestLines(?array $purchaseRequestLines): self {
        foreach($this->getPurchaseRequestLines()->toArray() as $purchaseRequestLine) {
            $this->removePurchaseRequestLine($purchaseRequestLine);
        }

        $this->purchaseRequestLines = new ArrayCollection();
        foreach($purchaseRequestLines as $purchaseRequestLine) {
            $this->addPurchaseRequestLine($purchaseRequestLine);
        }

        return $this;
    }

    public function isUrgent(): bool {
        return $this->urgent;
    }

    public function setUrgent(bool $urgent): self {
        $this->urgent = $urgent;
        return $this;
    }

    public function isPossibleCustoms(): bool {
        return $this->possibleCustoms;
    }

    public function setPossibleCustoms(bool $possibleCustoms): self {
        $this->possibleCustoms = $possibleCustoms;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getReceiver(): ?string
    {
        return $this->receiver;
    }

    public function setReceiver(?string $receiver): self
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function serialize(FormatService $formatService): array {
        return [
            FixedFieldEnum::name->value => $this->getNom(),
            'code' => $this->getCodeReference(),
            'possibleCustoms' => $formatService->bool($this->isPossibleCustoms()),
            FixedFieldEnum::urgent->value => $formatService->bool($this->isUrgent()),
            FixedFieldEnum::address->value => $this->getAddress(),
            FixedFieldEnum::receiver->value => $this->getReceiver(),
            FixedFieldEnum::phoneNumber->value => $formatService->phone($this->getPhoneNumber()),
            FixedFieldEnum::email->value => $this->getEmail(),
        ];
    }
}
