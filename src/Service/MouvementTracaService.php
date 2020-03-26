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
use App\Repository\MouvementTracaRepository;
use App\Repository\FiltreSupRepository;
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

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var UserService
     */
    private $userService;

    private $security;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;
    private $em;
    private $attachmentService;

    public function __construct(UserService $userService,
                                MouvementTracaRepository $mouvementTracaRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                FiltreSupRepository $filtreSupRepository,
                                Security $security,
                                AttachmentService $attachmentService)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->userService = $userService;
        $this->filtreSupRepository = $filtreSupRepository;
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
        $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MVT_TRACA, $this->security->getUser());

        $queryResult = $this->mouvementTracaRepository->findByParamsAndFilters($params, $filters);

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
        $articleRepository = $this->em->getRepository(Article::class);
        $refArticleRepository = $this->em->getRepository(ReferenceArticle::class);

        $articleOrRef = $articleRepository->findOneBy([
            'barCode' => $mouvement->getColis()
        ]);
        if (!$articleOrRef) {
            $articleOrRef = $refArticleRepository->findOneBy([
                'barCode' => $mouvement->getColis()
            ]);
        }

        $row = [
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
            'reference' => $articleOrRef
                ? ($articleOrRef instanceof ReferenceArticle
                    ? $articleOrRef->getReference()
                    : $articleOrRef->getArticleFournisseur()->getReferenceArticle()->getReference())
                : '',
            'label' => $articleOrRef
                ? ($articleOrRef instanceof ReferenceArticle
                    ? $articleOrRef->getLibelle()
                    : $articleOrRef->getLabel())
                : '',
            'type' => $mouvement->getType() ? $mouvement->getType()->getNom() : '',
            'operateur' => $mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '',
            'Actions' => $this->templating->render('mouvement_traca/datatableMvtTracaRow.html.twig', [
                'mvt' => $mouvement,
            ])
        ];

        return $row;
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
        $statutRepository = $this->em->getRepository(Statut::class);

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

        $mouvementTraca = new MouvementTraca();
        $mouvementTraca
            ->setColis($colis)
            ->setEmplacement($location)
            ->setOperateur($user)
            ->setUniqueIdForMobile($fromNomade ? $this->generateUniqueIdForMobile($date) : null)
            ->setDatetime($date)
            ->setFinished($finished)
            ->setType($type)
            ->setMouvementStock($mouvementStock)
            ->setCommentaire(!empty($commentaire) ? $commentaire : null);

        if (isset($from)) {
            if ($from instanceof Arrivage) {
                $mouvementTraca->setArrivage($from);
            } else if ($from instanceof Reception) {
                $mouvementTraca->setReception($from);
            }
        }

        $this->em->persist($mouvementTraca);

        if (isset($fileBag)) {
            $this->attachmentService->addAttachements($fileBag, $mouvementTraca);
        }

        return $mouvementTraca;
    }

    private function generateUniqueIdForMobile(DateTime $date): string
    {
        $uniqueId = null;
        //same format as moment.defaultFormat
        $dateStr = $date->format(DateTime::ATOM);
        $randomLength = 9;
        do {
            $random = strtolower(substr(sha1(rand()), 0, $randomLength));
            $uniqueId = $dateStr . '_' . $random;
            $existingMouvements = $this->mouvementTracaRepository->findBy(['uniqueIdForMobile' => $uniqueId]);
        } while (!empty($existingMouvements));

        return $uniqueId;
    }
}
