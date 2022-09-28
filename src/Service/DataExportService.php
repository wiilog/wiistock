<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\Fournisseur;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;

class DataExportService
{

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public Security $security;

    #[Required]
    public ArrivageService $arrivalService;

    public function createReferencesHeader(array $freeFieldsConfig) {
        return array_merge([
            'reference',
            'libellé',
            'quantité',
            'type',
            'acheteur',
            'type quantité',
            'statut',
            'commentaire',
            'emplacement',
            'seuil sécurite',
            'seuil alerte',
            'prix unitaire',
            'code barre',
            'catégorie inventaire',
            'date dernier inventaire',
            'synchronisation nomade',
            'gestion de stock',
            'gestionnaire(s)',
            'Labels Fournisseurs',
            'Codes Fournisseurs',
            'Groupe de visibilité',
            'date de création',
            'crée par',
            'date de dérniere modification',
            'modifié par',
            "date dernier mouvement d'entrée",
            "date dernier mouvement de sortie",
        ], $freeFieldsConfig["freeFieldsHeader"]);
    }

    public function createArticlesHeader(array $freeFieldsConfig) {
        return array_merge([
            'reference',
            'libelle',
            'quantité',
            'type',
            'statut',
            'commentaire',
            'emplacement',
            'code barre',
            'date dernier inventaire',
            'lot',
            'date d\'entrée en stock',
            'date de péremption',
            'groupe de visibilité'
        ], $freeFieldsConfig['freeFieldsHeader']);
    }

    public function createDeliveryRoundHeader() {
        return [
            'N° Tournée',
            'Date tournée',
            'Transport',
            'Livreur',
            'Immatriculation',
            'Kilomètres',
            'N° dossier patient',
            'N° Demande',
            'Adresse transport',
            'Métropole',
            'Numéro dans la tournée',
            'Urgence',
            'Date de création',
            'Demandeur',
            'Date demandée',
            'Date demande terminée',
            'Objets',
            'Anomalie température',
        ];
    }

    public function exportReferences(RefArticleDataService $refArticleDataService,
                                     array $freeFieldsConfig,
                                     iterable $data,
                                     mixed $output) {
        $managersByReference = $this->entityManager
            ->getRepository(Utilisateur::class)
            ->getUsernameManagersGroupByReference();

        $suppliersByReference = $this->entityManager
            ->getRepository(Fournisseur::class)
            ->getCodesAndLabelsGroupedByReference();

        foreach($data as $reference) {
            $refArticleDataService->putReferenceLine($output, $managersByReference, $reference, $suppliersByReference, $freeFieldsConfig);
        }
    }

    public function exportArticles(ArticleDataService $articleDataService, array $freeFieldsConfig, iterable $data, mixed $output) {
        foreach($data as $article) {
            $articleDataService->putArticleLine($output, $article, $freeFieldsConfig);
        }
    }

    public function exportTransportRounds(TransportRoundService $transportRoundService, iterable $data, mixed $output, DateTime $begin, DateTime $end) {
        /** @var TransportRound $round */
        foreach ($data as $round) {
            $transportRoundService->putLineRoundAndRequest($output, $round, function(TransportRoundLine $line) use ($begin, $end) {
                $order = $line->getOrder();
                $treatedAt = $order?->getTreatedAt() ?: null;

                return (
                    $treatedAt >= $begin
                    && $treatedAt <= $end
                );
            });
        }
    }

    public function createUniqueExportLine(string $entity, DateTime $from) {
        $type = $this->entityManager->getRepository(Type::class)->findOneByCategoryLabelAndLabel(
            CategoryType::EXPORT,
            Type::LABEL_UNIQUE_EXPORT,
        );

        $status = $this->entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(
            CategorieStatut::EXPORT,
            Export::STATUS_FINISHED,
        );

        $to = new DateTime();

        $export = new Export();
        $export->setEntity($entity);
        $export->setType($type);
        $export->setStatus($status);
        $export->setCreator($this->security->getUser());
        $export->setCreatedAt($from);
        $export->setBeganAt($from);
        $export->setEndedAt($to);
        $export->setForced(false);

        $this->entityManager->persist($export);
        $this->entityManager->flush();

        return $export;
    }

    public function exportArrivages(iterable $data,
                                    mixed $output,
                                    array $columnToExport)
    {
        /** @var Arrivage $arrival */
        foreach ($data as $arrival) {
            $this->arrivalService->putArrivalLineInUniqueExport($output, $arrival, $columnToExport);
        }
    }
}
