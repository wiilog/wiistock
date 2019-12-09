<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="FieldsParamRepository")
 */
class FieldsParam
{
    const ENTITY_CODE_RECEPTION = 'réception';

    const FIELD_CODE_FOURNISSEUR = 'fournisseur';
    const FIELD_CODE_NUM_COMMANDE = 'numero de commande';
    const FIELD_CODE_DATE_ATTENDUE = 'date attendue';
    const FIELD_CODE_DATE_COMMANDE = 'date commande';
    const FIELD_CODE_COMMENTAIRE = 'commentaire';
    const FIELD_CODE_UTILISATEUR = 'utilisateur';
    const FIELD_CODE_NUM_RECEPTION = 'numero reception';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $entityCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fieldCode;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustToCreate;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustToModify;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(string $entityCode): self
    {
        $this->entityCode = $entityCode;

        return $this;
    }

    public function getFieldCode(): ?string
    {
        return $this->fieldCode;
    }

    public function setFieldCode(string $fieldCode): self
    {
        $this->fieldCode = $fieldCode;

        return $this;
    }

    public function getMustToCreate(): ?bool
    {
        return $this->mustToCreate;
    }

    public function setMustToCreate(?bool $mustToCreate): self
    {
        $this->mustToCreate = $mustToCreate;

        return $this;
    }

    public function getMustToModify(): ?bool
    {
        return $this->mustToModify;
    }

    public function setMustToModify(?bool $mustToModify): self
    {
        $this->mustToModify = $mustToModify;

        return $this;
    }
}
