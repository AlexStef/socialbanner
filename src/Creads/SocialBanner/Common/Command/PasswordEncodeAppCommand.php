<?php

namespace Creads\SocialBanner\Common\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PasswordEncodeAppCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this->setName('app:password:encode')
            ->setDescription('Encode a password to use to setup security')
            ->addArgument(
                'password',
                InputArgument::OPTIONAL,
                'The password to encode'
            );
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getSilexApplication();

        $password = $input->getArgument('password');

        $encodePassword = $app['security.encoder.digest']->encodePassword($password, null);

        $output->writeln(sprintf('Encoded password is: <info>%s</info>', $encodePassword));
    }
}
