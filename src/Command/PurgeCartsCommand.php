<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Command;

use RomainGenin\AstraCheckoutBundle\Repository\CartRepository;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'astra:cart:purge', description: 'Deletes abandoned anonymous carts (run it daily from a cron)')]
final class PurgeCartsCommand extends Command
{
    public function __construct(private readonly CartRepository $carts, private readonly ShopSettings $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Age in days (default: astra_checkout.cart.lifetime_days)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) ($input->getOption('days') ?? $this->settings->getCartLifetimeDays());
        $count = $this->carts->purgeAnonymous(new \DateTimeImmutable(sprintf('-%d days', $days)));
        (new SymfonyStyle($input, $output))->success(sprintf('%d abandoned cart(s) deleted.', $count));

        return Command::SUCCESS;
    }
}
