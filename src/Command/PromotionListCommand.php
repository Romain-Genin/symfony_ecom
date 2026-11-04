<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Command;

use Psr\Clock\ClockInterface;
use StudioAstra\AstraCheckoutBundle\Entity\Promotion;
use StudioAstra\AstraCheckoutBundle\Enum\PromotionType;
use StudioAstra\AstraCheckoutBundle\Repository\PromotionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'astra:promotion:list', description: 'Lists promotions with their status and usage')]
final class PromotionListCommand extends Command
{
    public function __construct(private readonly PromotionRepository $promotions, private readonly ClockInterface $clock)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $this->clock->now();
        $rows = array_map(static fn (Promotion $p): array => [
            $p->getId(),
            $p->getName(),
            $p->getCode() ?? '(auto)',
            match ($p->getType()) {
                PromotionType::Percentage => rtrim(rtrim(number_format($p->getValue() / 100, 2, ',', ''), '0'), ',') . ' %',
                PromotionType::FixedAmount => number_format($p->getValue() / 100, 2, ',', ' '),
                PromotionType::FreeShipping => 'free shipping',
            },
            $p->isActiveAt($now) ? 'active' : ($p->isEnabled() ? 'scheduled/ended/used up' : 'disabled'),
            $p->getUsageCount() . (null !== $p->getUsageLimit() ? ' / ' . $p->getUsageLimit() : ''),
            ($p->getStartsAt()?->format('Y-m-d H:i') ?? '…') . ' → ' . ($p->getEndsAt()?->format('Y-m-d H:i') ?? '…'),
        ], $this->promotions->findBy([], ['enabled' => 'DESC', 'priority' => 'DESC']));
        (new SymfonyStyle($input, $output))->table(['#', 'Name', 'Code', 'Discount', 'Status', 'Uses', 'Period'], $rows);

        return Command::SUCCESS;
    }
}
