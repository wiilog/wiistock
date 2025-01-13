<?php

namespace App\Command\Users;

use App\Entity\Utilisateur;
use App\Repository\UserRepository;
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
use WiiCommon\Helper\StringHelper;

/** Examples:
 * php bin/console app:user:deactivate --regex ".*@wiilog\.fr" --force
 * php bin/console app:user:deactivate --email admin@wiilog.fr
 */
#[AsCommand(
    name: 'app:user:deactivate',
    description: 'Assign users matching a regex to inactive status. Or, if an email is provided, deactivate the corresponding user. Examples: php bin/console app:user:deactivate --regex ".*@wiilog\.fr" --force php bin/console app:user:deactivate --email admin@wiilog.fr',
)]
class DeactivateUserCommand extends Command
{
    private UtilisateurRepository $userRepository;
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserService $userService,
    ) {
        parent::__construct();
        $this->userRepository = $this->entityManager->getRepository(Utilisateur::class);
    }

    protected function configure(): void
    {
        $this
            ->addOption('regex', null, InputOption::VALUE_OPTIONAL, 'The regex pattern to filter users\' emails.')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'The email address of a user to deactivate.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Directly deactivate users matching the regex, without confirmation.');
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

        $output->writeln('<error>Please specify either an email or a regex pattern.</error>');
        return Command::FAILURE;
    }

    private function deactivateByEmail(string $email, OutputInterface $output): int
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user) {
            $output->writeln('<error>User with this email not found.</error>');
            return Command::FAILURE;
        }

        if (!$user->getStatus()) {
            $output->writeln('<error>The user is already deactivated.</error>');
            return Command::SUCCESS;
        }

        $this->userService->deactivateUser($user);
        $output->writeln(sprintf('<info>%s successfully deactivated.</info>', $user->getEmail()));

        return Command::SUCCESS;
    }

    private function deactivateByRegex(string $regex, bool $force, InputInterface $input, OutputInterface $output): int
    {
        $users = $this->userRepository->iterateAllMatching($regex);
        $userCount = $this->userRepository->countAllMatching($regex);

        if ($userCount === 0) {
            $output->writeln('<error>No users found matching the given regex.</error>');
            return Command::FAILURE;
        }

        if ($force) {
            return $this->deactivateUsers($users, $output);
        }

        $output->writeln('<info>Users found:</info>');

        // iterable to array in interactive mode
        $usersArray = iterator_to_array($users);
        $userEmails = Stream::from($usersArray)
            ->map(fn(Utilisateur $user) => $user->getEmail())
            ->toArray();

        foreach ($userEmails as $index => $email) {
            $output->writeln(sprintf('%d. %s', $index + 1, $email));
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Choose the users to deactivate (multiple selections, separate by commas):',
            $userEmails
        );
        $question->setMultiselect(true);

        /** @var QuestionHelper $helper */
        $emailsToDeactivate = $helper->ask($input, $output, $question);

        if (empty($emailsToDeactivate)) {
            $output->writeln('<error>No users selected.</error>');
            return Command::FAILURE;
        }
        $selectedUsers = Stream::from($usersArray)
            ->filter(static fn(Utilisateur $user) => in_array($user->getEmail(), $emailsToDeactivate, true))
            ->toArray();
        return $this->deactivateUsers($selectedUsers, $output);
    }

    /**
     * @param iterable<Utilisateur> $users
     */
    private function deactivateUsers(iterable $users, OutputInterface $output): int
    {
        foreach ($users as $user) {
            $this->userService->deactivateUser($user);
            $output->writeln(sprintf('<info>%s successfully deactivated.</info>', $user->getEmail()));
        }

        return Command::SUCCESS;
    }
}
