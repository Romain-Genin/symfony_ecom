<?php

declare(strict_types=1);

namespace StudioAstra\AstraCheckoutBundle;

/**
 * Resolved "astra_checkout" configuration, injectable anywhere (and exposed to Twig as "astra.settings").
 */
final class ShopSettings
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function getShopName(): string { return $this->config['shop_name']; }
    public function getCurrency(): string { return $this->config['currency']; }
    public function pricesIncludeTax(): bool { return $this->config['prices_include_tax']; }
    public function getDefaultTaxRate(): int { return $this->config['default_tax_rate']; }
    public function getLayout(): string { return $this->config['layout']; }
    public function getPerPage(): int { return $this->config['catalog']['per_page']; }
    public function getLowStockThreshold(): int { return $this->config['catalog']['low_stock_threshold']; }
    public function isHttpCacheEnabled(): bool { return $this->config['catalog']['http_cache']['enabled']; }
    public function getHttpCacheMaxAge(): int { return $this->config['catalog']['http_cache']['max_age']; }
    public function getHttpCacheSharedMaxAge(): int { return $this->config['catalog']['http_cache']['shared_max_age']; }
    public function getMaxQuantityPerItem(): int { return $this->config['cart']['max_quantity_per_item']; }
    public function getCartLifetimeDays(): int { return $this->config['cart']['lifetime_days']; }
    public function isGuestCheckoutAllowed(): bool { return $this->config['checkout']['guest']; }
    /** @return list<string> */
    public function getCountries(): array { return $this->config['checkout']['countries']; }
    public function getOrderNumberPrefix(): string { return $this->config['checkout']['order_number_prefix']; }
    public function getTermsUrl(): ?string { return $this->config['checkout']['terms_url']; }
    public function getShippingTaxRate(): int { return $this->config['shipping']['tax_rate']; }
    public function getMailerFrom(): ?string { return $this->config['mailer']['from']; }
    public function getMailerFromName(): ?string { return $this->config['mailer']['from_name'] ?? $this->getShopName(); }
    public function getPasswordResetTtl(): int { return $this->config['account']['password_reset_ttl']; }
    public function isRegistrationEnabled(): bool { return null !== $this->config['account']['register_route']; }
    /** Whether the shop provides login, registration, password reset, profile and deletion pages. */
    public function hasBuiltInAuth(): bool { return $this->config['account']['auth']; }
    public function getCustomerRole(): string { return $this->config['account']['role']; }
    public function getLoginRoute(): string { return $this->config['account']['login_route']; }
    public function getLogoutRoute(): string { return $this->config['account']['logout_route']; }
    public function getRegisterRoute(): ?string { return $this->config['account']['register_route']; }
    public function isHomeEnabled(): bool { return $this->config['routing']['home']; }
    public function getAssetsCss(): bool { return $this->config['assets']['css']; }
    /** "bundled" (Alpine included), "components" (your Alpine, <script> tag) or "none" (you import components.js) */
    public function getAssetsJs(): string { return $this->config['assets']['js']; }
    public function isAutoInjectEnabled(): bool { return $this->config['assets']['auto_inject']; }
    public function getSearchMinLength(): int { return $this->config['search']['min_length'] ?? 2; }
    public function getSuggestLimit(): int { return $this->config['search']['suggest_limit'] ?? 6; }
    public function getAnnouncementRotationInterval(): int { return $this->config['announcements']['rotation_interval'] ?? 6; }
}
