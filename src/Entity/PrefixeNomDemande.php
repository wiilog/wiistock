<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PrefixeNomDemandeRepository")
 */
class PrefixeNomDemande
{
	const TYPE_LIVRAISON = 'livraison';
	const TYPE_COLLECTE = 'collecte';
	const TYPE_HANDLING = 'service';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $typeDemandeAssociee;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $prefixe;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeDemandeAssociee(): ?string
    {
        return $this->typeDemandeAssociee;
    }

    public function setTypeDemandeAssociee(string $typeDemandeAssociee): self
    {
        $this->typeDemandeAssociee = $typeDemandeAssociee;

        return $this;
    }

    public function getPrefixe(): ?string
    {
        return $this->prefixe;
    }

    public function setPrefixe(?string $prefixe): self
    {
        $this->prefixe = $prefixe;

        return $this;
    }
}
