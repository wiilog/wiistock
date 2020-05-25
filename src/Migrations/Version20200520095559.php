<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200520095559 extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function getDescription() : string
    {
        return 'On enlève "Référence" du champ recherche_for_article des utilisateurs';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }

    public function postUp(Schema $schema): void
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $usersRepository = $em->getRepository(Utilisateur::class);
        $users = $usersRepository->findByFieldNotNull('rechercheForArticle');
        foreach ($users as $user) {
            /**
             * @var Utilisateur $user
             */
            $userArticleSearch = $user->getRechercheForArticle();
            if (in_array('Référence', $userArticleSearch)) {
                $newArticleSearch = array_filter($userArticleSearch, function (string $field) {
                    return $field !== 'Référence';
                });
                $user->setRechercheForArticle($newArticleSearch);
            }
        }
        $em->flush();
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }
}
