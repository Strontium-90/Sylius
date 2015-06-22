<?php

namespace Sylius\Bundle\InstallerBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallAssetsCommand extends AbstractInstallCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sylius:install:assets')
            ->setDescription('Installs all Sylius assets.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command downloads and installs all Sylius media assets.
EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf('Installing Sylius assets for environment <info>%s</info>.', $this->getEnvironment()));

        $rep = $this->get('sylius.repository.api_user');
        $manager = $this->get('sylius.manager.api_user');
        $rep2 = $manager->getRepository($this->getContainer()->getParameter('sylius.model.api_user.class'));


        $commands = array(
            'assets:install',
            'assetic:dump',
        );

        $this->runCommands($commands, $input, $output);
    }
}
