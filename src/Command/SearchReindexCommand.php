<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use RomainGenin\AstraCheckoutBundle\Entity\Product;
use RomainGenin\AstraCheckoutBundle\Search\Meilisearch\MeilisearchIndex;
use RomainGenin\AstraCheckoutBundle\Search\ProductDocumentFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'astra:search:reindex', description: 'Rebuilds the search index (database text index, and Meilisearch when enabled)')]
final class SearchReindexCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductDocumentFactory $documents,
        private readonly ?MeilisearchIndex $meilisearch = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (null !== $this->meilisearch) {
            $io->text(sprintf('Meilisearch index "%s": applying settings…', $this->meilisearch->getName()));
            $this->meilisearch->setup();
            $this->meilisearch->clear();
        }

        $count = 0;
        $lastId = 0;
        $io->progressStart();
        do {
            $ids = array_map('intval', array_column($this->em->createQueryBuilder()
                ->select('p.id')->from(Product::class, 'p')
                ->where('p.id > :last')->setParameter('last', $lastId)
                ->orderBy('p.id', 'ASC')->setMaxResults(200)
                ->getQuery()->getScalarResult(), 'id'));
            /** @var list<Product> $batch */
            $batch = [] === $ids ? [] : $this->em->createQueryBuilder()
                ->select('p', 'v', 'c')->from(Product::class, 'p')
                ->leftJoin('p.variants', 'v')->leftJoin('p.categories', 'c')
                ->where('p.id IN (:ids)')->setParameter('ids', $ids)
                ->orderBy('p.id', 'ASC')
                ->getQuery()->getResult();
            foreach ($batch as $product) {
                [$name, $text] = $this->documents->databaseIndex($product);
                $product->setSearchIndex($name, $text);
                $lastId = (int) $product->getId();
            }
            // The Doctrine listener of Meilisearch pushes the flushed products; push explicitly too
            // in case the products did not change (first indexing).
            $this->em->flush();
            $this->meilisearch?->save($batch);
            $count += \count($batch);
            $io->progressAdvance(\count($batch));
            $this->em->clear();
        } while (200 === \count($batch));
        $io->progressFinish();
        $io->success(sprintf('%d product(s) indexed.', $count));

        return Command::SUCCESS;
    }
}
