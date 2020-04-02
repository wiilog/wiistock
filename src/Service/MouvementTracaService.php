<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
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
        } elseif ($mouvement->getReception()) {
            $fromPath = 'reception_show';
            $fromLabel = 'réception.réception';
            $fromEntityId = $mouvement->getReception()->getId();
            $originFrom = $mouvement->getReception()->getNumeroReception();
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
            'type' => $mouvement->getType() ? $mouvement->getType()->getNom() : '',
            'operateur' => $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '',
            'Actions' => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $mouvement,
            ])
        ];
    }

    /**
     * @param string $colis
     * @param Emplacement $location
     * @param Utilisateur $user
     * @param DateTime $date
     * @param bool $fromNomade
     * @param bool|null $finished
     * @param string|int $typeMouvementTraca label ou id du mouvement traca
     * @param array $options = ['commentaire' => string|null, 'mouvementStock' => MouvementStock|null, 'fileBag' => FileBag|null, from => Arrivage|Reception|null]
     * @return MouvementTraca
     * @throws Exception
     */
    public function persistMouvementTraca(string $colis,
                                          ?Emplacement $location,
                                          Utilisateur $user,
                                          DateTime $date,
                                          bool $fromNomade,
                                          ?bool $finished,
                                          $typeMouvementTraca,
                                          array $options = []): MouvementTraca
    {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);

        $type = is_string($typeMouvementTraca)
            ? $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, $typeMouvementTraca)
            : $statutRepository->find($typeMouvementTraca);

        if (!isset($type)) {
            throw new Exception('Le type de mouvement traca donné est invalide');
        }

        $commentaire = $options['commentaire'] ?? null;
        $mouvementStock = $options['mouvementStock'] ?? null;
        $fileBag = $options['fileBag'] ?? null;
        $from = $options['from'] ?? null;
        $uniqueIdForMobile = $options['uniqueIdForMobile'] ?? null;

        $mouvementTraca = new MouvementTraca();
        $mouvementTraca
            ->setColis($colis)
            ->setEmplacement($location)
            ->setOperateur($user)
            ->setUniqueIdForMobile($uniqueIdForMobile ?: ($fromNomade ? $this->generateUniqueIdForMobile($date) : null))
            ->setDatetime($date)
            ->setFinished($finished)
            ->setType($type)
            ->setMouvementStock($mouvementStock)
            ->setCommentaire(!empty($commentaire) ? $commentaire : null);

        $refOrArticle = $referenceArticleRepository->findOneBy(['barCode' => $colis])
                        ?: $articleRepository->findOneBy(['barCode' => $colis]);
        if ($refOrArticle instanceof ReferenceArticle) {
            $mouvementTraca->setReferenceArticle($refOrArticle);
        }
        else if ($refOrArticle instanceof Article) {
            $mouvementTraca->setArticle($refOrArticle);
        }

        if (isset($from)) {
            if ($from instanceof Arrivage) {
                $mouvementTraca->setArrivage($from);
            } else if ($from instanceof Reception) {
                $mouvementTraca->setReception($from);
            }
        }

        $this->entityManager->persist($mouvementTraca);

        if (isset($fileBag)) {
            $this->attachmentService->addAttachements($fileBag, $mouvementTraca);
        }

        return $mouvementTraca;
    }

    private function generateUniqueIdForMobile(DateTime $date): string
    {
        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);

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
}
