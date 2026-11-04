<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use StudioAstra\AstraCheckoutBundle\Entity\Announcement;
use StudioAstra\AstraCheckoutBundle\Entity\Category;
use StudioAstra\AstraCheckoutBundle\Entity\Promotion;
use StudioAstra\AstraCheckoutBundle\Enum\AnnouncementPlacement;
use StudioAstra\AstraCheckoutBundle\Enum\PromotionType;
use StudioAstra\AstraCheckoutBundle\Entity\Product;
use StudioAstra\AstraCheckoutBundle\Entity\ProductImage;
use StudioAstra\AstraCheckoutBundle\Entity\ProductVariant;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** Fills an empty shop with a small demo catalog (categories, variants, stock, sales, images). */
#[AsCommand(name: 'astra:demo:load', description: 'Loads a demo catalog (for development only)')]
final class LoadDemoCommand extends Command
{
    private const COLORS = ['Noir' => '#1f2937', 'Blanc' => '#f3f4f6', 'Bleu' => '#1d4ed8', 'Vert' => '#15803d', 'Terracotta' => '#c2410c', 'Sable' => '#d6c7a1'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Load even if products already exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->getOption('force') && $this->em->getRepository(Product::class)->count([]) > 0) {
            $io->warning('The catalog is not empty. Use --force to add the demo products anyway.');

            return Command::FAILURE;
        }

        $fs = new Filesystem();
        $dir = $this->publicDir . '/uploads/astra-demo';
        $fs->mkdir($dir);

        $root = fn (string $name, string $slug, int $pos, ?Category $parent = null, ?string $description = null): Category => $this->category($name, $slug, $pos, $parent, $description);
        $clothes = $root('Vêtements', 'vetements', 1, null, 'Des basiques durables en coton biologique, fabriqués en Europe.');
        $tshirts = $root('T-shirts', 't-shirts', 1, $clothes);
        $sweats = $root('Sweats', 'sweats', 2, $clothes);
        $accessories = $root('Accessoires', 'accessoires', 2, null, 'Les petits plus du quotidien.');
        $bags = $root('Sacs', 'sacs', 1, $accessories);
        $caps = $root('Casquettes', 'casquettes', 2, $accessories);
        $home = $root('Maison', 'maison', 3, null, 'Céramique artisanale et linge de maison.');

        $catalog = [
            ['T-shirt essentiel', $tshirts, 2500, null, ['Noir', 'Blanc', 'Bleu'], ['S', 'M', 'L', 'XL'], 'Coton bio 180 g/m², coupe droite.'],
            ['T-shirt col V', $tshirts, 2700, 3500, ['Blanc', 'Vert'], ['S', 'M', 'L'], 'Jersey léger, idéal en été.'],
            ['T-shirt manches longues', $tshirts, 3200, null, ['Noir', 'Sable'], ['M', 'L', 'XL'], 'Maille côtelée, poignets resserrés.'],
            ['T-shirt poche', $tshirts, 2900, null, ['Terracotta', 'Bleu'], ['S', 'M', 'L'], 'Petite poche poitrine brodée.'],
            ['Sweat à capuche', $sweats, 6900, 8900, ['Noir', 'Vert', 'Sable'], ['S', 'M', 'L', 'XL'], 'Molleton gratté 350 g/m².'],
            ['Sweat col rond', $sweats, 5900, null, ['Bleu', 'Blanc'], ['S', 'M', 'L'], 'Le classique, en coton recyclé.'],
            ['Sweat zippé', $sweats, 7500, null, ['Noir'], ['M', 'L'], 'Zip YKK, poches kangourou.'],
            ['Tote bag', $bags, 1900, null, ['Sable', 'Noir'], [], 'Toile épaisse 340 g/m², anses longues.'],
            ['Sac à dos urbain', $bags, 8900, 10900, ['Noir', 'Vert'], [], 'Compartiment ordinateur 15", déperlant.'],
            ['Banane', $bags, 3500, null, ['Terracotta', 'Bleu', 'Noir'], [], 'Sangle réglable, poche anti-vol.'],
            ['Casquette brodée', $caps, 2900, null, ['Bleu', 'Sable', 'Vert'], [], 'Visière courbée, fermoir laiton.'],
            ['Bob réversible', $caps, 3200, 3900, ['Noir', 'Blanc'], [], 'Deux looks en un.'],
            ['Mug émaillé', $home, 1800, null, ['Blanc', 'Bleu', 'Terracotta'], [], 'Grès émaillé, 35 cl, va au lave-vaisselle.'],
            ['Plaid en laine', $home, 8900, null, ['Sable', 'Vert'], [], 'Laine mérinos, 130 × 170 cm.'],
            ['Bougie parfumée', $home, 2400, null, [], [], 'Cire végétale, 40 h de combustion. Senteur figuier.'],
            ['Housse de coussin', $home, 2200, 2900, ['Terracotta', 'Sable', 'Bleu'], [], 'Lin lavé, 45 × 45 cm.'],
        ];

        $i = 0;
        foreach ($catalog as [$name, $category, $price, $compareAt, $colors, $sizes, $short]) {
            $slug = $this->slug($name);
            $product = new Product($name, $slug);
            $product->setBrand('Astra');
            $product->setShortDescription($short);
            $product->setDescription('<p>' . htmlspecialchars($short) . '</p><ul><li>Fabriqué au Portugal</li><li>Livraison offerte dès 60 €</li><li>Retours gratuits sous 30 jours</li></ul>');
            $product->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', 40 - $i * 2)));
            $product->addCategory($category);
            if ($category->getParent()) {
                $product->addCategory($category->getParent());
            }

            $combos = [];
            foreach ($colors ?: [null] as $color) {
                foreach ($sizes ?: [null] as $size) {
                    $combos[] = array_filter(['Couleur' => $color, 'Taille' => $size]);
                }
            }
            foreach ($combos as $n => $options) {
                // A few sold-out and low-stock variants to show the UI states.
                $stock = match (($i + $n) % 7) { 0 => 0, 1 => 3, default => 20 + ($n * 3) % 17 };
                $variant = new ProductVariant(strtoupper(substr(str_replace('-', '', $slug), 0, 8)) . '-' . ($n + 1), $price + (isset($options['Taille']) && 'XL' === $options['Taille'] ? 300 : 0), $stock, $options);
                $variant->setCompareAtPrice($compareAt);
                $variant->setPosition($n);
                $product->addVariant($variant);
            }

            foreach (array_slice($colors ?: ['Sable'], 0, 3) as $k => $color) {
                $file = sprintf('%s-%d.svg', $slug, $k + 1);
                $fs->dumpFile("$dir/$file", $this->svg($name, self::COLORS[$color], $k));
                $product->addImage(new ProductImage('uploads/astra-demo/' . $file, sprintf('%s, coloris %s', $name, mb_strtolower($color)), 800, 1000));
            }

            $this->em->persist($product);
            ++$i;
        }

        // Promotions: one automatic (category), one welcome code, one free-shipping code.
        $sweatsPromo = (new Promotion('Sweats -15 %', PromotionType::Percentage, 1500))
            ->setLabel('−15 % sur tous les sweats, appliqué au panier')
            ->addCategory($sweats)->setExcludeSaleItems(true)->setPriority(10);
        $welcome = (new Promotion('Bienvenue', PromotionType::Percentage, 1000))
            ->setCode('BIENVENUE10')->setLabel('−10 % de bienvenue')
            ->setMinSubtotal(3000)->setUsageLimitPerCustomer(1);
        $shipping = (new Promotion('Livraison offerte', PromotionType::FreeShipping, 0))
            ->setCode('LIVRAISON')->setLabel('Livraison offerte')->setEndsAt(new \DateTimeImmutable('+30 days'));
        foreach ([$sweatsPromo, $welcome, $shipping] as $promotion) {
            $this->em->persist($promotion);
        }

        $announcements = [
            (new Announcement('Livraison offerte dès 60 € · Retours gratuits sous 30 jours'))->setPosition(1),
            (new Announcement('−10 % sur votre première commande avec le code'))->setPromotion($welcome)->setTone('promo')->setPosition(2)->setDismissible(true),
            (new Announcement('Nouveau : la collection maison est arrivée.'))->setLinkUrl('/c/maison')->setLinkLabel('Découvrir la collection maison')->setPosition(3),
            (new Announcement('Les sweats sont à −15 % jusqu’à dimanche, sans code.', AnnouncementPlacement::Home))->setTone('promo')->setPromotion($sweatsPromo)->setLinkUrl('/c/sweats')->setLinkLabel('Voir les sweats'),
            (new Announcement('Commandez avant 13 h : expédition le jour même.', AnnouncementPlacement::Cart))->setTone('info'),
        ];
        foreach ($announcements as $announcement) {
            $this->em->persist($announcement);
        }

        $this->em->flush();
        $io->success(sprintf('%d demo products, 3 promotions (codes BIENVENUE10 and LIVRAISON) and %d announcements loaded. Visit /products.', \count($catalog), \count($announcements)));

        return Command::SUCCESS;
    }

    private function category(string $name, string $slug, int $position, ?Category $parent, ?string $description): Category
    {
        $category = new Category($name, $slug);
        $category->setPosition($position)->setParent($parent)->setDescription($description);
        $this->em->persist($category);

        return $category;
    }

    private function slug(string $text): string
    {
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }

    private function svg(string $name, string $color, int $variant): string
    {
        $light = \in_array($color, ['#f3f4f6', '#d6c7a1'], true);
        $fg = $light ? '#1f2937' : '#ffffff';
        $shape = match ($variant) {
            0 => '<circle cx="400" cy="460" r="210" fill="' . $fg . '" fill-opacity=".14"/>',
            1 => '<rect x="210" y="270" width="380" height="380" rx="40" fill="' . $fg . '" fill-opacity=".14" transform="rotate(12 400 460)"/>',
            default => '<path d="M400 230 L620 640 L180 640 Z" fill="' . $fg . '" fill-opacity=".14"/>',
        };

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" width="800" height="1000"><rect width="800" height="1000" fill="$color"/>$shape<text x="400" y="860" font-family="system-ui, sans-serif" font-size="44" font-weight="600" text-anchor="middle" fill="$fg">{$this->escape($name)}</text></svg>
            SVG;
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, \ENT_XML1);
    }
}
