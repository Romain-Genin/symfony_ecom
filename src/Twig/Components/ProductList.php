<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle\Twig\Components;

use StudioAstra\AstraCheckoutBundle\Catalog\ProductListCriteria;
use StudioAstra\AstraCheckoutBundle\Entity\Category;
use StudioAstra\AstraCheckoutBundle\Http\PageResponse;
use StudioAstra\AstraCheckoutBundle\Presentation\CatalogListing;
use StudioAstra\AstraCheckoutBundle\Presentation\ListingView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Complete product listing (PLP / search results), driven by the query string of YOUR page:
 *   <twig:Astra:ProductList />                                {# all products #}
 *   <twig:Astra:ProductList category="{{ slug }}" />          {# a category and its sub-categories #}
 *   <twig:Astra:ProductList search />                         {# results for ?q= #}
 * Every part can be switched off: breadcrumb, header, filters, sorting, pagination, subcategories.
 */
#[AsTwigComponent('Astra:ProductList', template: '@AstraCheckout/components/Astra/ProductList.html.twig')]
final class ProductList
{
    public Category|string|null $category = null;
    public bool $search = false;
    public ?int $perPage = null;
    /** Pre-computed by your controller (e.g. to set an ETag); otherwise built from the request. */
    public ?ListingView $listing = null;
    public bool $breadcrumb = true;
    public bool $header = true;
    public bool $filters = true;
    public bool $sorting = true;
    public bool $pagination = true;
    public bool $subcategories = true;
    public bool $announcements = true;
    public bool $notFound = false;

    public function __construct(private readonly CatalogListing $catalog, private readonly PageResponse $page)
    {
    }

    #[PostMount]
    public function load(): void
    {
        if (null !== $this->listing) {
            return;
        }
        if (\is_string($this->category) && null === ($this->category = $this->catalog->findCategory($this->category))) {
            $this->notFound = true;
            $this->page->setStatus(404);
            $this->category = null;
        }
        $this->listing = $this->catalog->build($this->category, $this->search, $this->perPage);
    }

    /** @return list<string> */
    public function getSorts(): array
    {
        return ProductListCriteria::SORTS;
    }
}
