<?php
namespace App\Command;

use Framework\Converter\ToString;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends Command
{
    protected static string $defaultName = 'app:example';
    protected static string $defaultDescription = 'Demo command using DI.';

    protected function configure(): void
    {
        $this->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
            ->addArgument('name', InputArgument::OPTIONAL, 'Name', 'world');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = ToString::convert($input->getArgument('name'));
        $output->writeln("Hello, $name");
        return Command::SUCCESS;
    }
}
