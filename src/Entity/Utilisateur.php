<?php
namespace App\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
/**
 * @ORM\Entity(repositoryClass="App\Repository\UtilisateurRepository")
 * @UniqueEntity(fields="email", message="Cette adresse email est déjà utilisée.")
 * @UniqueEntity(fields="username", message="Ce nom d'utilisateur est déjà utilisé.")
 */
class Utilisateur implements UserInterface, EquatableInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $username;
    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank()
     * @Assert\Email()
     */
    private $email;
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $password;
    /**
     * @Assert\NotBlank()
     * @Assert\Length(min=8, max=4096)
     * @Assert\Regex(pattern="/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*\W)(?!.*\s).*$/", message="Doit contenir au moins une majuscule, une minuscule, un symbole, et un nombre.")
     */
    private $plainPassword;
    /**
     * @ORM\Column(type="array")
     */
    private $roles;
    private $salt;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastLogin;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="utilisateur")
     */
    private $receptions;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="utilisateur")
     */
    private $demandes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Alerte", mappedBy="AlerteUtilisateur")
     */
    private $UtilisateurAlertes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="demandeur")
     */
    private $collectes;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Preparation", mappedBy="Utilisateur")
     */
    private $preparations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Livraison", mappedBy="utilisateur")
     */
    private $livraisons;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Mouvement", mappedBy="user")
     */
    private $mouvements;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $apiKey;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Service", mappedBy="demandeur")
     */
    private $services;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Filter", mappedBy="utilisateur", orphanRemoval=true)
     */
    private $filters;
    
    public function __construct()
    {
        $this->receptions = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->UtilisateurAlertes = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->filters = new ArrayCollection();
    }
    public function getId()
    {
        return $this->id;
    }
    public function getUsername() : ? string
    {
        return $this->username;
    }
    public function setUsername(string $username) : self
    {
        $this->username = $username;
        return $this;
    }
    public function getEmail() : ? string
    {
        return $this->email;
    }
    public function setEmail(string $email) : self
    {
        $this->email = $email;
        return $this;
    }
    public function getPassword() : ? string
    {
        return $this->password;
    }
    public function setPassword(string $password) : self
    {
        $this->password = $password;
        return $this;
    }
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }
    public function setPlainPassword($password)
    {
        $this->plainPassword = $password;
    }
    public function getSalt()
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }
    public function getRoles()
    {
        return $this->roles;
    }
    public function setRoles(array $roles) : self
    {
        $this->roles = $roles;
        return $this;
    }
    public function eraseCredentials()
    {
    }
    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof Utilisateur) {
            return false;
        }
        if ($this->password !== $user->getPassword()) {
            return false;
        }
        if ($this->email !== $user->getEmail()) {
            return false;
        }
        return true;
    }
    public function getLastLogin() : ? \DateTimeInterface
    {
        return $this->lastLogin;
    }
    public function setLastLogin(? \DateTimeInterface $lastLogin) : self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }
    /**
     * @return Collection|Reception[]
     */
    public function getReceptions() : Collection
    {
        return $this->receptions;
    }
    public function addReception(Reception $reception) : self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setUtilisateur($this);
        }
        return $this;
    }
    public function removeReception(Reception $reception) : self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getUtilisateur() === $this) {
                $reception->setUtilisateur(null);
            }
        }
        return $this;
    }
    public function __toString()
    {
        return $this->username;
    }
    /**
     * @return Collection|Demande[]
     */
    public function getDemandes() : Collection
    {
        return $this->demandes;
    }
    public function addDemande(Demande $demande) : self
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->setUtilisateur($this);
        }
        return $this;
    }
    public function removeDemande(Demande $demande) : self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getUtilisateur() === $this) {
                $demande->setUtilisateur(null);
            }
        }
        return $this;
    }
    /**
     * @return Collection|Alerte[]
     */
    public function getUtilisateurAlertes() : Collection
    {
        return $this->UtilisateurAlertes;
    }
    public function addUtilisateurAlerte(Alerte $utilisateurAlerte) : self
    {
        if (!$this->UtilisateurAlertes->contains($utilisateurAlerte)) {
            $this->UtilisateurAlertes[] = $utilisateurAlerte;
            $utilisateurAlerte->setAlerteUtilisateur($this);
        }
        return $this;
    }
    public function removeUtilisateurAlerte(Alerte $utilisateurAlerte) : self
    {
        if ($this->UtilisateurAlertes->contains($utilisateurAlerte)) {
            $this->UtilisateurAlertes->removeElement($utilisateurAlerte);
            // set the owning side to null (unless already changed)
            if ($utilisateurAlerte->getAlerteUtilisateur() === $this) {
                $utilisateurAlerte->setAlerteUtilisateur(null);
            }
        }
        return $this;
    }
    /**
     * @return Collection|Collecte[]
     */
    public function getCollectes() : Collection
    {
        return $this->collectes;
    }
    public function addCollecte(Collecte $collecte) : self
    {
        if (!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setDemandeur($this);
        }
        return $this;
    }
    public function removeCollecte(Collecte $collecte) : self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getDemandeur() === $this) {
                $collecte->setDemandeur(null);
            }
        }
        return $this;
    }
    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations() : Collection
    {
        return $this->preparations;
    }
    public function addPreparation(Preparation $preparation) : self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setUtilisateur($this);
        }
        return $this;
    }
    public function removePreparation(Preparation $preparation) : self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getUtilisateur() === $this) {
                $preparation->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|Livraison[]
     */
    public function getLivraisons(): Collection
    {
        return $this->livraisons;
    }

    public function addLivraison(Livraison $livraison): self
    {
        if (!$this->livraisons->contains($livraison)) {
            $this->livraisons[] = $livraison;
            $livraison->setUtilisateur($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self
    {
        if ($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if ($livraison->getUtilisateur() === $this) {
                $livraison->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Mouvement[]
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    public function addMouvement(Mouvement $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setUser($this);
        }

        return $this;
    }

    public function removeMouvement(Mouvement $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getUser() === $this) {
                $mouvement->setUser(null);
            }
        }

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @return Collection|Service[]
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services[] = $service;
            $service->setDemandeur($this);
        }

        return $this;
    }

    public function removeService(Service $service): self
    {
        if ($this->services->contains($service)) {
            $this->services->removeElement($service);
            // set the owning side to null (unless already changed)
            if ($service->getDemandeur() === $this) {
                $service->setDemandeur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Filter[]
     */
    public function getFilters(): Collection
    {
        return $this->filters;
    }

    public function addFilter(Filter $filter): self
    {
        if (!$this->filters->contains($filter)) {
            $this->filters[] = $filter;
            $filter->setUtilisateur($this);
        }

        return $this;
    }

    public function removeFilter(Filter $filter): self
    {
        if ($this->filters->contains($filter)) {
            $this->filters->removeElement($filter);
            // set the owning side to null (unless already changed)
            if ($filter->getUtilisateur() === $this) {
                $filter->setUtilisateur(null);
            }
        }

        return $this;
    }
}