<?php

namespace App\Command\Users;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use WiiCommon\Helper\Stream;

/** Exemples :
 * php bin/console app:user:deactivate --regex ".*@wiilog\.fr" --force
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserService $userService,
    ) {
        parent::__construct();
        $this->userRepository = $this->entityManager->getRepository(Utilisateur::class);
        $this->roleRepository = $this->entityManager->getRepository(Role::class);
    }

    protected function configure(): void
    {
        $this
            ->addOption('regex', null, InputOption::VALUE_OPTIONAL, 'Le pattern regex pour filtrer les emails des utilisateurs.')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'L\'adresse email d\'un utilisateur à désactiver.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Désactive directement les utilisateurs correspondant à un regex, sans confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getOption('email');
        $regex = $input->getOption('regex');
        $force = $input->getOption('force');

        if ($email) {
            return $this->deactivateByEmail($email, $output);
        }

        if ($regex) {
            return $this->deactivateByRegex($regex, $force, $input, $output);
        }

        $output->writeln('<error>Veuillez spécifier soit un email, soit un pattern regex.</error>');
        return Command::FAILURE;
    }

    private function deactivateByEmail(string $email, OutputInterface $output): int
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user) {
            $output->writeln('<error>L\'utilisateur avec cet email n\'a pas été trouvé.</error>');
            return Command::FAILURE;
        }

        if (!$user->getStatus()) {
            $output->writeln('<error>L\'utilisateur est déjà désactivé.</error>');
            return Command::SUCCESS;
        }

        $this->userService->deactivateUser($user, $this->roleRepository);
        $output->writeln(sprintf('<info>%s désactivé avec succès.</info>', $user->getEmail()));

        return Command::SUCCESS;
    }

    private function deactivateByRegex(string $regex, bool $force, InputInterface $input, OutputInterface $output): int
    {
        $users = Stream::from($this->userRepository->iterateAllMatching($regex))
            ->filter(fn(Utilisateur $user) => $user->getStatus())
            ->toArray();

        if (empty($users)) {
            $output->writeln('<error>Aucun utilisateur correspondant à ce pattern ou tous les utilisateurs sont déjà désactivés.</error>');
            return Command::FAILURE;
        }

        if ($force) {
            return $this->deactivateUsers($users, $output);
        }

        $output->writeln('<info>Utilisateurs trouvés :</info>');
        $userEmails = array_map(fn(Utilisateur $user) => $user->getEmail(), $users);

        foreach ($userEmails as $index => $email) {
            $output->writeln(sprintf('%d. %s', $index + 1, $email));
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Choisissez les utilisateurs à désactiver (sélection multiple, séparez par des virgules) :',
            $userEmails
        );
        $question->setMultiselect(true);

        /** @var QuestionHelper $helper */
        $emailsToDeactivate = $helper->ask($input, $output, $question);

        if (empty($emailsToDeactivate)) {
            $output->writeln('<error>Aucun utilisateur sélectionné.</error>');
            return Command::FAILURE;
        }

        $selectedUsers = array_filter($users, fn(Utilisateur $user) => in_array($user->getEmail(), $emailsToDeactivate, true));
        return $this->deactivateUsers($selectedUsers, $output);
    }

    private function deactivateUsers(array $users, OutputInterface $output): int
    {
        foreach ($users as $user) {
            $this->userService->deactivateUser($user, $this->roleRepository);
            $output->writeln(sprintf('<info>%s désactivé avec succès.</info>', $user->getEmail()));
        }

        return Command::SUCCESS;
    }
}
