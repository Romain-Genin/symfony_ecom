<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Command;

use StudioAstra\AstraCheckoutBundle\Enum\OrderStatus;
use StudioAstra\AstraCheckoutBundle\Order\OrderStateMachine;
use StudioAstra\AstraCheckoutBundle\Repository\OrderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'astra:order:status', description: 'Shows or changes the status of an order (e.g. mark a bank transfer as paid)')]
final class OrderStatusCommand extends Command
{
    public function __construct(private readonly OrderRepository $orders, private readonly OrderStateMachine $stateMachine)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('number', InputArgument::REQUIRED, 'Order number')
            ->addArgument('status', InputArgument::OPTIONAL, 'New status: ' . implode(', ', array_column(OrderStatus::cases(), 'value')));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $order = $this->orders->findOneWithItems(['number' => $input->getArgument('number')]);
        if (null === $order) {
            $io->error('Order not found.');

            return Command::FAILURE;
        }

        $status = $input->getArgument('status');
        if (null === $status) {
            $io->definitionList(
                ['Number' => $order->getNumber()],
                ['Status' => $order->getStatus()->value],
                ['Customer' => $order->getEmail()],
                ['Total' => number_format($order->getTotal() / 100, 2) . ' ' . $order->getCurrency()],
                ['Allowed transitions' => implode(', ', array_column($order->getStatus()->allowedTransitions(), 'value')) ?: '—'],
            );

            return Command::SUCCESS;
        }

        $to = OrderStatus::tryFrom($status);
        if (null === $to || !$this->stateMachine->can($order, $to)) {
            $io->error(sprintf('Cannot go from "%s" to "%s". Allowed: %s', $order->getStatus()->value, $status, implode(', ', array_column($order->getStatus()->allowedTransitions(), 'value')) ?: 'none'));

            return Command::FAILURE;
        }
        $this->stateMachine->apply($order, $to);
        $io->success(sprintf('Order %s is now "%s".', $order->getNumber(), $to->value));

        return Command::SUCCESS;
    }
}
