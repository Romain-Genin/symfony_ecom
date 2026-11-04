<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Controller;

use RomainGenin\AstraCheckoutBundle\Catalog\ProductListCriteria;
use RomainGenin\AstraCheckoutBundle\Http\CatalogCacheHelper;
use RomainGenin\AstraCheckoutBundle\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Minimal shop home page (new arrivals + sales). Override the template or the route freely. */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'astra_home', methods: ['GET', 'HEAD'])]
    public function __invoke(Request $request, ProductRepository $products, CatalogCacheHelper $cache): Response
    {
        $newest = $products->findForListing(new ProductListCriteria(sort: 'newest', perPage: 8, inStockOnly: true), false);
        $sales = $products->findForListing(new ProductListCriteria(sort: 'newest', perPage: 4, onSaleOnly: true, inStockOnly: true), false);

        $fingerprint = hash('xxh128', $newest->fingerprint() . $sales->fingerprint() . $request->getLocale());
        if ($notModified = $cache->notModified($request, $fingerprint)) {
            return $notModified;
        }

        return $cache->prepare($this->render('@AstraCheckout/home.html.twig', ['newest' => $newest, 'sales' => $sales]), $fingerprint);
    }
}
