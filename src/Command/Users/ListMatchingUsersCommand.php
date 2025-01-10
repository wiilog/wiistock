<?php

namespace App\Command\Users;

use App\Entity\User;
use App\Entity\Utilisateur;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Examples:
 * php bin/console app:user:list-matching --regex ".*@wiilog\.fr"
 */
#[AsCommand(
    name: 'app:user:list-matching',
    description: 'List matching regex users. Examples: php bin/console app:user:list-matching --regex ".*@wiilog\.fr"',
)]
class ListMatchingUsersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->userRepository = $entityManager->getRepository(Utilisateur::class);
    }

    protected function configure(): void
    {
        $this
            ->addOption('regex', null, InputOption::VALUE_REQUIRED, 'The regex pattern to filter users\' emails.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $regex = $input->getOption('regex');

        if (!$regex) {
            $output->writeln('<error>You must specify a regex pattern.</error>');
            return Command::FAILURE;
        }

        $matchingUsers = $this->userRepository->iterateAllMatching($regex);

        foreach ($matchingUsers as $matchingUser) {
            $output->writeln($matchingUser->getEmail());
        }

        return Command::SUCCESS;
    }
}
