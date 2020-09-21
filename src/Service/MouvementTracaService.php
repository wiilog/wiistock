<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementTraca;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use DateTime;
use Exception;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MouvementTracaService
{
    public const INVALID_LOCATION_TO = 'invalid-location-to';

    private $templating;
    private $router;
    private $userService;
    private $security;
    private $entityManager;
    private $attachmentService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security,
                                AttachmentService $attachmentService)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
        $this->attachmentService = $attachmentService;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws Exception
     */
    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_TRACA, $this->security->getUser());
        $queryResult = $mouvementTracaRepository->findByParamsAndFilters($params, $filters);

        $mouvements = $queryResult['data'];

        $rows = [];
        foreach ($mouvements as $mouvement) {
            $rows[] = $this->dataRowMouvement($mouvement);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param MouvementTraca $mouvement
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowMouvement($mouvement)
    {
        if ($mouvement->getArrivage()) {
            $fromPath = 'arrivage_show';
            $fromLabel = 'arrivage.arrivage';
            $fromEntityId = $mouvement->getArrivage()->getId();
            $originFrom = $mouvement->getArrivage()->getNumeroArrivage();
        } else if ($mouvement->getReception()) {
            $fromPath = 'reception_show';
            $fromLabel = 'réception.réception';
            $fromEntityId = $mouvement->getReception()->getId();
            $originFrom = $mouvement->getReception()->getNumeroReception();
        } else if ($mouvement->getDispatch()) {
            $fromPath = 'dispatch_show';
            $fromLabel = 'acheminement.Acheminement';
            $fromEntityId = $mouvement->getDispatch()->getId();
            $originFrom = $mouvement->getDispatch()->getNumber();
        } else {
            $fromPath = null;
            $fromEntityId = null;
            $fromLabel = null;
            $originFrom = '-';
        }
        return [
            'id' => $mouvement->getId(),
            'date' => $mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '',
            'colis' => $mouvement->getColis(),
            'origin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', [
                'from' => $originFrom,
                'fromLabel' => $fromLabel,
                'entityPath' => $fromPath,
                'entityId' => $fromEntityId
            ]),
            'location' => $mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '',
            'reference' => $mouvement->getReferenceArticle()
                ? $mouvement->getReferenceArticle()->getReference()
                : ($mouvement->getArticle()
                    ? $mouvement->getArticle()->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : ''),
            'label' => $mouvement->getReferenceArticle()
                ? $mouvement->getReferenceArticle()->getLibelle()
                : ($mouvement->getArticle()
                    ? $mouvement->getArticle()->getLabel()
                    : ''),
            'quantity' => $mouvement->getQuantity() ? $mouvement->getQuantity() : '',
            'type' => $mouvement->getType() ? $mouvement->getType()->getNom() : '',
            'operateur' => $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '',
            'Actions' => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $mouvement,
            ])
        ];
    }

    /**
     * @param string|Pack $pack
     * @param Emplacement|null $location
     * @param Utilisateur $user
     * @param DateTime $date
     * @param bool $fromNomade
     * @param bool|null $finished
     * @param string|int $typeMouvementTraca label ou id du mouvement traca
     * @param array $options = [
     *      'commentaire' => string|null,
     *      'quantity' => int|null,
     *      'natureId' => int|null,
     *      'mouvementStock' => MouvementStock|null,
     *      'fileBag' => FileBag|null, from => Arrivage|Reception|null],
     *      'entityManager' => EntityManagerInterface|null
     * @return MouvementTraca
     * @throws Exception
     */
    public function createTrackingMovement($pack,
                                           ?Emplacement $location,
                                           Utilisateur $user,
                                           DateTime $date,
                                           bool $fromNomade,
                                           ?bool $finished,
                                           $typeMouvementTraca,
                                           array $options = []): MouvementTraca
    {
        $entityManager = $options['entityManager'] ?? $this->entityManager;
        $statutRepository = $entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $codePack = $pack instanceof Pack ? $pack->getCode() : $pack;

        $type = ($typeMouvementTraca instanceof Statut)
            ? $typeMouvementTraca
            : (is_string($typeMouvementTraca)
                ? $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, $typeMouvementTraca)
                : $statutRepository->find($typeMouvementTraca));

        if (!isset($type)) {
            throw new Exception('Le type de mouvement traca donné est invalide');
        }

        $commentaire = $options['commentaire'] ?? null;
        $mouvementStock = $options['mouvementStock'] ?? null;
        $fileBag = $options['fileBag'] ?? null;
        $quantity = $options['quantity'] ?? 1;
        $from = $options['from'] ?? null;
        $receptionReferenceArticle = $options['receptionReferenceArticle'] ?? null;
        $uniqueIdForMobile = $options['uniqueIdForMobile'] ?? null;

        $mouvementTraca = new MouvementTraca();
        $mouvementTraca
            ->setColis($codePack)
            ->setQuantity($quantity)
            ->setEmplacement($location)
            ->setOperateur($user)
            ->setUniqueIdForMobile($uniqueIdForMobile ?: ($fromNomade ? $this->generateUniqueIdForMobile($entityManager, $date) : null))
            ->setDatetime($date)
            ->setFinished($finished)
            ->setType($type)
            ->setMouvementStock($mouvementStock)
            ->setCommentaire(!empty($commentaire) ? $commentaire : null);


        $this->managePackLinksWithTracking(
            $mouvementTraca,
            $entityManager,
            $type,
            $pack,
            false,
            $quantity,
            $options['natureId'] ?? null
        );

        $refOrArticle = $referenceArticleRepository->findOneBy(['barCode' => $codePack])
            ?: $articleRepository->findOneBy(['barCode' => $codePack]);
        if ($refOrArticle instanceof ReferenceArticle) {
            $mouvementTraca->setReferenceArticle($refOrArticle);
        } else if ($refOrArticle instanceof Article) {
            $mouvementTraca->setArticle($refOrArticle);
        }

        if (isset($from)) {
            if ($from instanceof Arrivage) {
                $mouvementTraca->setArrivage($from);
            } else if ($from instanceof Reception) {
                $mouvementTraca->setReception($from);
            } else if ($from instanceof Dispatch) {
                $mouvementTraca->setDispatch($from);
            }
        }

        if (isset($receptionReferenceArticle)) {
            $mouvementTraca->setReceptionReferenceArticle($receptionReferenceArticle);
        }

        if (isset($fileBag)) {
            $attachements = $this->attachmentService->createAttachements($fileBag);
            foreach ($attachements as $attachement) {
                $mouvementTraca->addAttachment($attachement);
            }
        }
        return $mouvementTraca;
    }

    private function generateUniqueIdForMobile(EntityManagerInterface $entityManager,
                                               DateTime $date): string
    {
        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

        $uniqueId = null;
        //same format as moment.defaultFormat
        $dateStr = $date->format(DateTime::ATOM);
        $randomLength = 9;
        do {
            $random = strtolower(substr(sha1(rand()), 0, $randomLength));
            $uniqueId = $dateStr . '_' . $random;
            $existingMouvements = $mouvementTracaRepository->findBy(['uniqueIdForMobile' => $uniqueId]);
        } while (!empty($existingMouvements));

        return $uniqueId;
    }

    public function persistSubEntities(EntityManagerInterface $entityManager,
                                       MouvementTraca $mouvementTraca) {
        $pack = $mouvementTraca->getPack();
        if (!empty($pack)) {
            $entityManager->persist($pack);
        }
        foreach ($mouvementTraca->getLinkedPackLastDrops() as $colisMvt) {
            $entityManager->persist($colisMvt);
        }
        foreach ($mouvementTraca->getAttachments() as $attachement) {
            $entityManager->persist($attachement);
        }
    }

    /**
     * @param MouvementTraca $tracking
     * @param EntityManagerInterface $entityManager
     * @param Statut $type
     * @param string|Pack $pack
     * @param bool $persist
     * @param int $defaultQuantity Quantity used if pack does not exist
     * @param int|null $natureId
     */
    public function managePackLinksWithTracking(MouvementTraca $tracking,
                                                EntityManagerInterface $entityManager,
                                                Statut $type,
                                                $pack,
                                                bool $persist,
                                                int $defaultQuantity,
                                                int $natureId = null): void {
        $packRepository = $entityManager->getRepository(Pack::class);

        if (!empty($natureId)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $nature = $natureRepository->find($natureId);
        }

        $packs = ($pack instanceof Pack)
            ? [$pack]
            : $packRepository->findBy(['code' => $pack]);

        if (empty($packs)) {
            $newPack = new Pack();
            $newPack
                ->setQuantity($defaultQuantity)
                ->setCode($pack);

            $packs[] = $newPack;

            if ($persist) {
                $entityManager->persist($newPack);
            }
        }

        $tracking->setPack($packs[0]);

        $packsAlreadyExisting = $tracking->getLinkedPackLastDrops();
        // si c'est une prise ou une dépose on vide ses colis liés
        foreach ($packsAlreadyExisting as $packLastDrop) {
            $tracking->removeLinkedPacksLastDrop($packLastDrop);
        }

        foreach ($packs as $existingPack) {
            if ($type->getNom() === MouvementTraca::TYPE_DEPOSE) {
                $tracking->addLinkedPackLastDrop($existingPack);
            }

            if (!empty($nature)) {
                $existingPack->setNature($nature);
            }
        }
    }

    /**
     * @param MouvementTraca $mouvementTraca
     */
    public function manageMouvementTracaPreRemove(MouvementTraca $mouvementTraca) {
        foreach ($mouvementTraca->getLinkedPackLastDrops() as $pack) {
            $pack->setLastDrop(null);
        }
        foreach ($mouvementTraca->getLinkedPackLastTracking() as $pack) {
            $pack->setLastTracking($pack->getTrackingMovements()->count() <= 1 ? null : $pack->getTrackingMovements()->toArray()[1]);
        }
    }
}
