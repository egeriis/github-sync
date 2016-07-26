<?php

namespace Piwik\GithubSync;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    /**
     * @var Github
     */
    private $github;

    protected function configure()
    {
        $this
            ->setName('sync')
            ->setDescription('Synchronize GitHub labels and milestones between repositories')
            ->addArgument(
                'from',
                InputArgument::REQUIRED,
                'The repository containing the labels and milestones to use'
            )
            ->addArgument(
                'to',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The repository to synchronize'
            )
            ->addOption(
                'token',
                null,
                InputOption::VALUE_REQUIRED,
                'The GitHub token to use for authentication. Required if you want to create/updated/delete.'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Directly update each label and milestone without prompting the user.'
            )
            ->addOption(
                'skipDelete',
                null,
                InputOption::VALUE_NONE,
                'Skip delete of labels that do not exist in receiver repos.',
                false
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->github = new Github($input->getOption('token'));

        $to = $input->getArgument('to');

        if (count($to) != 1) {
            return;
        }
        $to = reset($to);

        if (strpos($to, '*') !== false) {
            try {
                $repositories = $this->github->getUserRepositoriesMatching($to);
            } catch (AuthenticationRequiredException $e) {
                $output->writeln('<error>Using wildcards requires to be authenticated.</error>');
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                exit;
            }
            $input->setArgument('to', $repositories);

            $output->writeln(sprintf('<info>Synchronizing %d repositories</info>', count($repositories)));
            $output->writeln('');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from = $input->getArgument('from');
        $targetList = $input->getArgument('to');

        $input->setInteractive(! $input->getOption('force'));

        $labelSynchronizer = new LabelSynchronizer($this->github, $input, $output);
        $milestoneSynchronizer = new MilestoneSynchronizer($this->github, $input, $output);

        foreach ($targetList as $to) {
            $output->writeln(sprintf('<comment>Synchronizing labels from %s to %s</comment>', $from, $to));
            $labelSynchronizer->synchronize($from, $to);
            $output->writeln('');

            $output->writeln(sprintf('<comment>Synchronizing milestones from %s to %s</comment>', $from, $to));
            $milestoneSynchronizer->synchronize($from, $to);
            $output->writeln('');
        }

        $output->writeln('<comment>Finished</comment>');
    }
}
