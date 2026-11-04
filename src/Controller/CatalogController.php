<?php

declare(strict_types=1);

namespace RomainGenin\AstraCheckoutBundle\Controller;

use RomainGenin\AstraCheckoutBundle\Http\CatalogCacheHelper;
use RomainGenin\AstraCheckoutBundle\Presentation\CatalogListing;
use RomainGenin\AstraCheckoutBundle\Presentation\ProductPresenter;
use RomainGenin\AstraCheckoutBundle\Presentation\ListingView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Default product listing (PLP) and product (PDP) pages, built with the ProductList and
 * ProductDetail blocks. Public and HTTP-cacheable. Replace them with your own pages through
 * astra_checkout.pages (see README).
 */
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly CatalogListing $catalog,
        private readonly ProductPresenter $products,
        private readonly CatalogCacheHelper $cache,
    ) {
    }

    #[Route('/products', name: 'astra_catalog', methods: ['GET', 'HEAD'])]
    public function index(Request $request): Response
    {
        return $this->listing($request, $this->catalog->build());
    }

    #[Route('/search', name: 'astra_search', methods: ['GET', 'HEAD'])]
    public function search(Request $request): Response
    {
        return $this->listing($request, $this->catalog->build(search: true));
    }

    #[Route('/c/{slug}', name: 'astra_category', requirements: ['slug' => '[a-z0-9][a-z0-9\-]*'], methods: ['GET', 'HEAD'])]
    public function category(Request $request, string $slug): Response
    {
        $category = $this->catalog->findCategory($slug) ?? throw $this->createNotFoundException();

        return $this->listing($request, $this->catalog->build($category));
    }

    #[Route('/p/{slug}', name: 'astra_product', requirements: ['slug' => '[a-z0-9][a-z0-9\-]*'], methods: ['GET', 'HEAD'])]
    public function show(Request $request, string $slug): Response
    {
        $product = $this->products->find($slug) ?? throw $this->createNotFoundException();
        $fingerprint = $this->products->fingerprint($product) . $request->getLocale();
        if ($notModified = $this->cache->notModified($request, $fingerprint, $product->getUpdatedAt())) {
            return $notModified;
        }

        return $this->cache->prepare($this->render('@AstraCheckout/catalog/show.html.twig', ['product' => $product]), $fingerprint, $product->getUpdatedAt());
    }

    private function listing(Request $request, ListingView $listing): Response
    {
        $criteria = $listing->criteria;
        if ($criteria->page > 1 && $criteria->page > $listing->page->pageCount()) {
            $query = $request->query->all();
            unset($query['page']);
            if ($listing->page->pageCount() > 1) {
                $query['page'] = $listing->page->pageCount();
            }

            return $this->redirect($request->getPathInfo() . ($query ? '?' . http_build_query($query) : ''));
        }

        $fingerprint = $listing->fingerprint() . $request->getLocale();
        if ($notModified = $this->cache->notModified($request, $fingerprint)) {
            return $notModified;
        }
        $response = $this->render('@AstraCheckout/catalog/list.html.twig', ['listing' => $listing]);
        // Filtered / searched variations are not worth indexing (duplicate content).
        if ($criteria->hasFilters() || $listing->isSearch) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $this->cache->prepare($response, $fingerprint);
    }
}
