<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Controller;

use RomainGenin\AstraCheckoutBundle\Pricing\MoneyFormatter;
use RomainGenin\AstraCheckoutBundle\Search\SearchEngineInterface;
use RomainGenin\AstraCheckoutBundle\Search\TextNormalizer;
use RomainGenin\AstraCheckoutBundle\ShopSettings;
use RomainGenin\AstraCheckoutBundle\Twig\AstraCheckoutExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Search-as-you-type suggestions (products + categories). Public and cacheable. */
final class SearchController extends AbstractController
{
    #[Route('/search/suggest', name: 'astra_search_suggest', methods: ['GET'], priority: 1)]
    public function suggest(Request $request, \RomainGenin\AstraCheckoutBundle\Routing\ShopUrls $urls, SearchEngineInterface $engine, AstraCheckoutExtension $twig, MoneyFormatter $money, Packages $packages, ShopSettings $settings, TranslatorInterface $translator): JsonResponse
    {
        $query = trim(mb_substr((string) $request->query->get('q', ''), 0, 100));
        $data = ['query' => $query, 'products' => [], 'categories' => [], 'searchUrl' => $urls->path('search', ['q' => $query])];

        if (mb_strlen($query) >= $settings->getSearchMinLength()) {
            foreach ($engine->suggest($query, $settings->getSuggestLimit()) as $product) {
                $image = $product->getMainImage();
                $data['products'][] = [
                    'name' => $product->getName(),
                    'url' => $urls->path('product', ['slug' => $product->getSlug()]),
                    'price' => ($product->hasPriceRange() ? $translator->trans('price.from', [], 'AstraCheckout') . ' ' : '') . $money->format($product->getMinPrice()),
                    'image' => null === $image ? null : ($image->isExternal() ? $image->getPath() : $packages->getUrl($image->getPath())),
                ];
            }
            $needle = TextNormalizer::normalize($query);
            $walk = function (array $nodes) use (&$walk, &$data, $needle, $urls): void {
                foreach ($nodes as $node) {
                    if (\count($data['categories']) < 3 && str_contains(TextNormalizer::normalize($node['name']), $needle)) {
                        $data['categories'][] = ['name' => $node['name'], 'url' => $urls->path('category', ['slug' => $node['slug']])];
                    }
                    $walk($node['children']);
                }
            };
            $walk($twig->categoryTree());
        }

        $response = $this->json($data);
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(300);
        $response->setVary(['Accept-Language'], false);

        return $response;
    }
}
