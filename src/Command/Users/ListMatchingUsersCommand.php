<?php

namespace App\Command\Users;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exemples :
 * php bin/console app:user:list-matching --regex ".*@wiilog\.fr"
 */
#[AsCommand(
    name: 'app:user:list-matching',
    description: 'Liste les utilisateurs correspondant à un pattern regex.',
)]
class ListMatchingUsersCommand extends Command
{
    private UtilisateurRepository $utilisateurRepository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
    }

    protected function configure(): void
    {
        $this
            ->addOption('regex', null, InputOption::VALUE_REQUIRED, 'Le pattern regex pour filtrer les emails des utilisateurs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupérer le regex passé en option
        $regex = $input->getOption('regex');

        if (!$regex) {
            $output->writeln('<error>Vous devez spécifier un pattern regex.</error>');
            return Command::FAILURE;
        }

        // Trouver les utilisateurs correspondant au regex
        $matchingUsers = $this->utilisateurRepository->findAllMatching($regex);

        // Afficher les utilisateurs trouvés
        foreach ($matchingUsers as $matchingUser) {
            $output->writeln($matchingUser->getEmail());
        }

        return Command::SUCCESS;
    }
}
