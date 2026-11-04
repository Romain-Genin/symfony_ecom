# Changelog

Le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [2.0.0] - 2026-11-04

### Modifié (rupture)

- Namespace PHP renommé `StudioAstra\AstraCheckoutBundle` → `RomainGenin\AstraCheckoutBundle` : mettez à jour `config/bundles.php`
  et vos `use`.
- Toutes les références au paquet utilisent son nom Composer `romain-genin/symfony-ecom` (chemins `vendor/…` compris).
- Alias AssetMapper `@studioastra/astra-checkout` → `@romain-genin/symfony-ecom`
  (`import { registerAstraCheckout } from '@romain-genin/symfony-ecom/js/components.js'`).

### Ajouté

- Compatibilité Symfony UX 3 (`symfony/ux-twig-component` ^3.0), donc avec le starter Symfony 8.


## [1.0.0] - 2026-11-04

- Première version.
