<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09.
 */

namespace App\Service;

use App\Repository\ArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\AlerteRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Swift_SmtpTransport;
use Swift_Mailer;

class SeuilAlerteService
{
    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /*
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /*
     * @var StatutRepository
     */
    private $statutRepository;

    /*
     * @var StatutRepository
     */
    private $utilisateurRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var AlerteRepository
     */
    private $alerteRepository;

    /**
     * @var object|string
     */
    private $user;

    /**
     * @var Swift_SmtpTransport
     */
    private $transport;

    /**
     * @var Swift_Mailer
     */
    private $mailer;
    /**
     * @var password
     */
    private $username;

    /**
     * @var password
     */
    private $password;

    private $em;

    public function __construct(AlerteRepository $alerteRepository, RefArticleDataService $refArticleDataService, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, TypeRepository  $typeRepository, StatutRepository $statutRepository, EntityManagerInterface $em, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FiltreRefRepository $filtreRefRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage, UtilisateurRepository $utilisateurRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->filtreRefRepository = $filtreRefRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->typeRepository = $typeRepository;
        $this->alerteRepository = $alerteRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->username = 'admin@wiilog.fr'; // TODO
        $this->password = 'Kellhus16^^'; // TODO
        $this->transport = (new Swift_SmtpTransport('smtp.sendgrid.net', 465, 'ssl'))
            ->setUsername($this->username)
            ->setPassword($this->password);
        $this->mailer = (new Swift_Mailer($this->transport));
    }

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function thresholdReaches()
    {
        $seuils = $this->alerteRepository->findAll();
        $nbSeuilAtteint = 0;
        foreach ($seuils as $seuil) {
            $quantiteAR = $this->referenceArticleRepository->getQuantiteStockById($seuil->getAlerteRefArticle()->getId());
            if ($seuil->getAlerteSeuil() > $quantiteAR) {
                $seuil->setSeuilAtteint(true);
                ++$nbSeuilAtteint;
            } else {
                $seuil->getSeuilAtteint(false);
            }
        }
        $entityManager = $this->em;
        $entityManager->flush();

        return $nbSeuilAtteint;
    }

    public function warnUsers()
    {
        $seuils = $this->alerteRepository->findAll();
        foreach ($seuils as $seuil) {
            $quantiteAR = $this->referenceArticleRepository->getQuantiteStockById($seuil->getAlerteRefArticle()->getId());
            if ($seuil->getAlerteSeuil() > $quantiteAR) {
                $user = $this->utilisateurRepository->find($seuil->getAlerteUtilisateur()->getId());
                $this->sendWarning($user->getEmail(), $seuil);
            }
        }
    }

    public function sendWarning($to, $alerte)
    {
        $message = (new \Swift_Message('Alerte de seuil Wiilog.'))
            ->setFrom([$this->username => 'L\'équipe de Wiilog.'])
            ->setTo($to)
            ->setBody('Vous avez une alerte de seuil concernant l\'article '.$alerte->getAlerteRefArticle()->getLibelle().'!');
        $this->mailer->send($message);
    }
}
