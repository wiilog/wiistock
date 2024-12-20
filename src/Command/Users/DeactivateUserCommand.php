<?php

namespace App\Command\Users;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/** Exemples :
 * php bin/console app:user:deactivate --regex ".*@wiilog\.fr"
 * php bin/console app:user:deactivate --email admin@wiilog.fr
 */
#[AsCommand(
    name: 'app:user:deactivate',
    description: 'Passe les utilisateurs correspondant à un regex en rôle aucun accès et inactif. Ou si un email est fourni, désactive l\'utilisateur correspondant.',
)]
class DeactivateUserCommand extends Command
{
    private UtilisateurRepository $userRepository;
    private RoleRepository $roleRepository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->userRepository = $this->entityManager->getRepository(Utilisateur::class);
        $this->roleRepository = $this->entityManager->getRepository(Role::class);
    }

    protected function configure(): void
    {
        $this
            ->addOption('regex', null, InputOption::VALUE_OPTIONAL, 'Le pattern regex pour filtrer les emails des utilisateurs.')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'L\'adresse email d\'un utilisateur à désactiver.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupérer les options
        $regex = $input->getOption('regex');
        $emailToDeactivate = $input->getOption('email');

        // Si un email est passé en option, on désactive cet utilisateur directement
        if ($emailToDeactivate) {
            $user = $this->userRepository->findOneByEmail($emailToDeactivate);

            if (!$user) {
                $output->writeln('<error>L\'utilisateur avec cet email n\'a pas été trouvé.</error>');
                return Command::FAILURE;
            }

            $this->deactivateUser($user, $output);
            return Command::SUCCESS;
        }

        // Si un regex est fourni, désactiver les utilisateurs correspondant à ce regex
        if ($regex) {
            $users = iterator_to_array($this->userRepository->findAllMatching($regex));

            // Vérifier si des utilisateurs correspondent
            if (empty($users)) {
                $output->writeln('<error>Aucun utilisateur correspondant à ce pattern.</error>');
                return Command::FAILURE;
            }

            // Afficher les utilisateurs trouvés
            $output->writeln('<info>Utilisateurs trouvés :</info>');
            foreach ($users as $index => $user) {
                $output->writeln(sprintf('%d. %s', $index + 1, $user->getEmail()));
            }

            // Demander à l'utilisateur de choisir ceux à désactiver
            $question = new ChoiceQuestion(
                'Choisissez les utilisateurs à désactiver (sélection multiple, séparez par des virgules) :',
                array_map(fn($user) => $user->getEmail(), $users),
                0
            );
            $question->setMultiselect(true);

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $emailsToDeactivate = $helper->ask($input, $output, $question);

            if (empty($emailsToDeactivate)) {
                $output->writeln('<error>Aucun utilisateur sélectionné.</error>');
                return Command::FAILURE;
            }

            // Désactivation des utilisateurs sélectionnés et ajout du rôle sans accès
            foreach ($users as $user) {
                if (in_array($user->getEmail(), $emailsToDeactivate, true)) {
                    $this->deactivateUser($user, $output);
                }
            }

            return Command::SUCCESS;
        }

        // Si aucun argument n'est passé, afficher un message d'erreur
        $output->writeln('<error>Veuillez spécifier soit un email, soit un pattern regex.</error>');
        return Command::FAILURE;
    }

    private function deactivateUser(Utilisateur $user, OutputInterface $output): void
    {
        $withoutAccessRole = $this->roleRepository->findByLabel(Role::NO_ACCESS_USER);

        $user->setRole($withoutAccessRole);
        $user->setStatus(false);
        $this->entityManager->flush();

        $output->writeln(sprintf('<info>%s désactivé avec succès.</info>', $user->getEmail()));
    }
}
