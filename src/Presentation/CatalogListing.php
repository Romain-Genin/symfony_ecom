<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Presentation;

use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Entity\Category;
use StudioAstra\AstraCheckoutBundle\Repository\CategoryRepository;
use StudioAstra\AstraCheckoutBundle\Search\ProductFinder;
use StudioAstra\AstraCheckoutBundle\ShopSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds a product listing from the current request (?q, ?min, ?max, ?in_stock, ?on_sale, ?sort,
 * ?page). Usable from your controller (e.g. for HTTP caching) and by the ProductList block.
 */
final class CatalogListing
{
    public function __construct(
        private readonly ProductFinder $finder,
        private readonly CategoryRepository $categories,
        private readonly ShopSettings $settings,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function findCategory(string $slug): ?Category
    {
        return $this->categories->findOneEnabledBySlug($slug);
    }

    public function build(Category|string|null $category = null, bool $search = false, ?int $perPage = null, ?Request $request = null): ListingView
    {
        $request ??= $this->requestStack->getCurrentRequest() ?? new Request();
        if (\is_string($category)) {
            $category = $this->findCategory($category);
        }
        $categoryIds = null === $category ? [] : $this->categories->getDescendantIds($category);
        $criteria = ProductListCriteria::fromRequest($request, $categoryIds, $perPage ?? $this->settings->getPerPage());
        if (!$search && null !== $criteria->search && null !== $category) {
            // ?q on a category page searches within the category.
            $search = true;
        }
        $page = $this->finder->find($criteria);

        return new ListingView(
            $page,
            $criteria,
            $category,
            $search,
            null === $category ? [] : array_values($category->getChildren()->filter(static fn (Category $c): bool => $c->isEnabled())->toArray()),
        );
    }
}
