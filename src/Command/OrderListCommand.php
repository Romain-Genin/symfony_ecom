<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Command;

use StudioAstra\AstraCheckoutBundle\Enum\OrderStatus;
use StudioAstra\AstraCheckoutBundle\Repository\OrderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'astra:order:list', description: 'Lists the latest orders')]
final class OrderListCommand extends Command
{
    public function __construct(private readonly OrderRepository $orders)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Filter by status')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of orders', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = [];
        if (null !== ($status = $input->getOption('status'))) {
            $criteria['status'] = OrderStatus::from($status);
        }
        $rows = [];
        foreach ($this->orders->findBy($criteria, ['createdAt' => 'DESC'], (int) $input->getOption('limit')) as $order) {
            $rows[] = [$order->getNumber(), $order->getCreatedAt()->format('Y-m-d H:i'), $order->getEmail(), $order->getStatus()->value, number_format($order->getTotal() / 100, 2) . ' ' . $order->getCurrency()];
        }
        (new SymfonyStyle($input, $output))->table(['Number', 'Date', 'E-mail', 'Status', 'Total'], $rows);

        return Command::SUCCESS;
    }
}
