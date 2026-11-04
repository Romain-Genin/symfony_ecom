# AstraCheckoutBundle

Bundle Symfony qui ajoute une **boutique en ligne complète** à votre application : catalogue (PLP/PDP),
variantes, panier, tunnel de commande, compte client et commandes. Le front est en **Twig + Tailwind CSS v4 + Alpine.js**,
**accessible** (WCAG 2.2 AA), **responsive** (mobile d'abord) et **rapide** (pages catalogue cachables, requêtes SQL bornées).

- **Catalogue** : catégories imbriquées, produits à variantes (taille, couleur…) avec SKU, prix, prix barré et stock propres,
  liste filtrable (prix, disponibilité, promotions), tri, recherche, pagination, fil d'Ariane, produits associés, JSON-LD.
- **Panier** : persistant en base, mini-panier en tiroir, fusion automatique à la connexion, contrôle du stock,
  incitation à la livraison offerte.
- **Commande** en 3 étapes (coordonnées → livraison et paiement → vérification), invité ou connecté, carnet d'adresses,
  stock réservé de façon atomique (verrou SQL), e-mail de confirmation.
- **Promotions** : promotions automatiques et codes promo (pourcentage, montant fixe, livraison offerte), période, minimum d'achat,
  limites d'utilisation (globale et par client), ciblage par catégories ou produits, exclusion des articles soldés, cumul ou non-cumul
  (le client obtient toujours la meilleure offre), TVA recalculée sur les montants remisés.
- **Bannières d'annonces** programmables : bandeau défilant en haut de page (accessible, avec pause), bannières sur l'accueil,
  le catalogue et le panier, liées ou non à une promotion, masquables par le visiteur.
- **Recherche optimisée** : insensible aux accents et à la casse, pluriels, mots composés (« tshirt » = « t-shirt »), références (SKU),
  options et catégories, synonymes, tri par pertinence, **suggestions instantanées** accessibles, et module **Meilisearch** optionnel
  (tolérance aux fautes de frappe, très gros catalogues) avec repli automatique sur la base de données.
- **Compte client** : inscription, connexion, mot de passe oublié, informations, mot de passe, carnet d'adresses,
  historique et suivi des commandes, annulation, suppression du compte (RGPD).
- **Extensible** : modes de livraison et de paiement par interface (Stripe, PayPal, Mollie…), événements,
  templates surchargeables, couleurs en variables CSS.
- **S'ajoute à un site existant** : des **blocs** (`<twig:Astra:ProductList />`, `<twig:Astra:Checkout />`…) à placer dans vos
  propres pages et routes, vos comptes utilisateurs réutilisés sans modifier votre entité, votre gabarit et votre build Tailwind.
- **Sans Node.js côté application** si vous le souhaitez : le CSS et le JS compilés sont livrés dans le bundle.

> Symfony 6.4 / 7.x / 8.x, PHP 8.2+, Doctrine ORM 2.17+ / 3.x, toute base supportée par Doctrine.

## Sommaire

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Fonctionnement](#fonctionnement)
4. [Promotions et codes promo](#promotions-et-codes-promo)
5. [Bannières d'annonces](#bannières-dannonces)
6. [Recherche](#recherche)
7. [Optimisation des données et performances](#optimisation-des-données-et-performances)
8. [Accessibilité](#accessibilité)
9. [Intégrer à un site existant](#intégrer-à-un-site-existant) : blocs, vos pages, comptes, Tailwind, Alpine
10. [Personnaliser l'apparence](#personnaliser-lapparence)
11. [Étendre : livraison, paiement, événements](#étendre--livraison-paiement-événements)
12. [Back-office](#back-office)
13. [Développement du bundle](#développement-du-bundle)

## Installation

```bash
composer require romain-genin/symfony-ecom
```

> Site existant avec ses propres comptes et son gabarit (par exemple
> [`romain-genin/symfony-starter`](https://packagist.org/packages/romain-genin/symfony-starter)) :
> voir [Exemple : brancher la boutique sur un site existant](#exemple--brancher-la-boutique-sur-un-site-existant).

Avec Symfony Flex, le bundle est enregistré automatiquement. Sinon, dans `config/bundles.php` :

```php
Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],   // dépendance : les blocs <twig:Astra:… />
RomainGenin\AstraCheckoutBundle\AstraCheckoutBundle::class => ['all' => true],
```

### 1. Routes

`config/routes/astra_checkout.yaml` :

```yaml
astra_checkout:
    resource: '@AstraCheckoutBundle/config/routes.yaml'
```

Le bundle fournit des pages prêtes à l'emploi (construites avec ses blocs). Aucun préfixe n'est nécessaire : les chemins se règlent
dans `astra_checkout.routing`, et chaque page peut être remplacée par l'une des vôtres (`astra_checkout.pages`, voir
[Intégrer à un site existant](#intégrer-à-un-site-existant)).

| URL par défaut | Page | Clé `routing.paths` |
|---|---|---|
| `/` | Accueil boutique (désactivable : `routing.home: false`) | — |
| `/products`, `/c/{slug}` | Listes de produits (PLP) | `catalog`, `category` |
| `/search?q=` | Recherche | `search` |
| `/p/{slug}` | Fiche produit (PDP) | `product` |
| `/cart` | Panier | `cart` |
| `/checkout/address`, `/checkout/shipping`, `/checkout/review` | Tunnel de commande | `checkout` |
| `/account/…` | Commandes, adresses (+ connexion, inscription… avec les comptes intégrés) | `account` |

### 2. Sécurité (compte client)

**Vous avez déjà des utilisateurs ?** Gardez votre `security.yaml` tel quel et indiquez votre entité
(`astra_checkout.customer.class`, voir [Réutiliser vos comptes](#réutiliser-vos-comptes-utilisateurs)).

Sinon, le bundle fournit l'entité `Customer` et les pages de connexion, inscription et mot de passe oublié ; le pare-feu reste sous
votre contrôle. Dans `config/packages/security.yaml` :

```yaml
security:
    password_hashers:
        RomainGenin\AstraCheckoutBundle\Customer\Customer: auto
    providers:
        astra_customers:
            entity: { class: RomainGenin\AstraCheckoutBundle\Customer\Customer, property: email }
    firewalls:
        main:
            lazy: true                       # indispensable pour que les pages catalogue restent cachables
            provider: astra_customers
            form_login:
                login_path: astra_login
                check_path: astra_login
                enable_csrf: true
                default_target_path: astra_account
            logout:
                path: astra_logout
                target: astra_home
            remember_me:                      # optionnel (case « Rester connecté »)
                secret: '%kernel.secret%'
            login_throttling: null            # recommandé (composer require symfony/rate-limiter)
```

Les pages `/account/*` exigent le rôle `ROLE_USER` (`account.role`) : aucune règle `access_control` n'est nécessaire.

### 3. Base de données

Les entités sont mappées automatiquement (tables préfixées `astra_`).

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### 4. Assets et données de démo

```bash
php bin/console assets:install       # fait automatiquement par Flex après composer require
php bin/console astra:demo:load      # facultatif : 16 produits, 3 promotions (codes BIENVENUE10, LIVRAISON), 5 annonces
```

Rendez-vous sur `/products`. C'est tout.

### 5. E-mails (recommandé)

Confirmation de commande, changements de statut et mot de passe oublié :

```bash
composer require symfony/mailer
```

```yaml
# config/packages/astra_checkout.yaml
astra_checkout:
    mailer:
        from: boutique@example.com
```

Sans `symfony/mailer` ou sans expéditeur, rien n'est envoyé (un avertissement est journalisé) et la commande n'est jamais bloquée.

## Configuration

Toutes les clés sont facultatives. Valeurs par défaut :

```yaml
astra_checkout:
    shop_name: 'Astra Shop'
    currency: EUR                    # montants stockés en centimes (entiers), jamais en flottants
    prices_include_tax: true         # true : prix catalogue TTC (B2C) ; false : HT, TVA ajoutée au total (B2B)
    default_tax_rate: 2000           # en points de base : 2000 = 20 %, 550 = 5,5 %
    layout: '@AstraCheckout/base.html.twig'   # gabarit étendu par toutes les pages de la boutique (le vôtre : 'base.html.twig')

    customer:
        class: RomainGenin\AstraCheckoutBundle\Customer\Customer   # votre entité, ex. App\Entity\User
        properties: { email: email, first_name: firstName, last_name: lastName, phone: null }

    pages: {}                        # vos routes : { product: app_product, checkout: app_checkout… } (voir « Intégrer à un site existant »)

    routing:
        home: true                   # false si votre site a sa propre page d'accueil
        paths: { catalog: /products, search: /search, category: /c, product: /p, cart: /cart, checkout: /checkout, account: /account }

    assets:
        css: true                    # false quand votre build Tailwind importe astra.css
        js: bundled                  # bundled (Alpine inclus) | components (votre Alpine en <script>) | none (vous importez components.js)
        auto_inject: true            # ajoute assets, mini-panier et configuration aux pages boutique rendues dans votre gabarit

    catalog:
        per_page: 24
        low_stock_threshold: 5       # « Plus que N en stock »
        http_cache:
            enabled: true
            max_age: 0               # cache navigateur (0 = revalidation systématique, réponse 304 très légère)
            shared_max_age: 300      # cache reverse proxy / CDN (Varnish, Symfony HttpCache, Cloudflare…)

    cart:
        max_quantity_per_item: 99
        lifetime_days: 30            # paniers anonymes supprimés par astra:cart:purge

    checkout:
        guest: true                  # commander sans compte
        countries: [FR, BE, LU, CH, MC, DE, ES, IT, NL, PT]   # pays de livraison (le premier est présélectionné)
        order_number_prefix: AS      # AS-261104-7KQ4M2
        terms_url: null              # lien des CGV dans la case à cocher de la dernière étape

    shipping:
        tax_rate: 2000               # TVA appliquée aux frais de port
        methods:
            standard: { label: shipping.standard.label, description: shipping.standard.description, price: 490, free_above: 6000 }
            express:  { label: shipping.express.label,  description: shipping.express.description,  price: 990, countries: [FR, BE, LU, MC] }

    payment:
        offline_methods:
            bank_transfer:
                label: payment.bank_transfer.label
                description: payment.bank_transfer.description
                instructions: payment.bank_transfer.instructions   # placeholders {number} et {total}
            # cheque: { label: 'Chèque', instructions: "Chèque de {total} à l'ordre de…, au dos : {number}" }
            # bank_transfer: { enabled: false }

    account:
        auth: ~                      # pages connexion/inscription/profil fournies ; par défaut : seulement avec le Customer intégré
        role: ROLE_USER
        login_route: ~               # par défaut astra_login, ou app_login quand vous réutilisez vos utilisateurs
        logout_route: ~              # par défaut astra_logout, ou app_logout
        register_route: ~            # votre route d'inscription, proposée après une commande invitée
        registration: true
        password_reset_ttl: 3600     # validité du lien « mot de passe oublié » (secondes)

    search:
        engine: database             # database | meilisearch | id d'un service SearchEngineInterface
        min_length: 2                # longueur minimale pour les suggestions
        suggest_limit: 6
        synonyms: {}                 # ex. { tshirt: [tee-shirt, tee], sweat: [hoodie, pull] }
        meilisearch:
            url: 'http://127.0.0.1:7700'
            api_key: null            # clé avec droits search + documents (jamais la master key en production)
            index: astra_products
            timeout: 2.0             # au-delà, la base de données répond à la place

    announcements:
        rotation_interval: 6         # secondes entre deux messages du bandeau (0 = pas de défilement automatique)

    mailer:
        from: null
        from_name: null              # par défaut : shop_name
```

Les libellés (`label`, `description`, `instructions`) acceptent un texte brut ou une clé de traduction du domaine `AstraCheckout`.
**Pensez à remplacer l'IBAN d'exemple** en surchargeant `payment.bank_transfer.instructions` dans
`translations/AstraCheckout.fr.yaml` de votre application, ou directement dans la configuration.

## Fonctionnement

### Modèle de données

| Entité | Rôle |
|---|---|
| `Category` | Arborescence (parent/enfants, position). Une catégorie affiche aussi les produits de ses sous-catégories. |
| `Product` | Nom, slug, marque, descriptions, SEO, taux de TVA, catégories, images. |
| `ProductVariant` | SKU, options (`{"Couleur": "Bleu", "Taille": "M"}`), prix, prix barré, stock (`null` = non suivi). Un produit simple a **une** variante. |
| `ProductImage` | Chemin (relatif à `public/`) ou URL, texte alternatif, dimensions, position. |
| `Customer` | Client intégré (`Customer\Customer`), ou **votre propre entité utilisateur** ; carnet d'`Address`. |
| `Cart` / `CartItem` | Panier en base + état du tunnel (adresses, modes choisis). |
| `Order` / `OrderItem` | Commande : **copie figée** des noms, prix, TVA et adresses au moment de l'achat. |
| `PostalAddress` | Adresse (objet embarqué) partagée par le carnet, le panier et la commande. |

Les prix sont des **entiers en centimes** (aucune erreur d'arrondi). La TVA est calculée ligne par ligne, arrondie au centime,
et ventilée par taux dans le récapitulatif.

Pour créer un produit depuis votre code :

```php
$product = (new Product('Sweat à capuche', 'sweat-a-capuche'))
    ->setTaxRate(2000)
    ->addCategory($category);
$product->addVariant(new ProductVariant('SWEAT-M-NOIR', 6900, stock: 12, options: ['Taille' => 'M', 'Couleur' => 'Noir']));
$product->addImage(new ProductImage('uploads/products/sweat.jpg', 'Sweat noir porté de face', 1600, 2000));
$em->persist($product);
$em->flush();
```

### Panier

- Le panier n'est **créé qu'au premier ajout** : un simple visiteur (ou un robot) n'écrit rien en base et n'ouvre pas de session.
- Anonyme : son identifiant est en session. Connecté : il est rattaché au client et le suit sur tous ses appareils.
- À la **connexion** ou à l'**inscription**, le panier anonyme est fusionné dans celui du client (quantités additionnées, plafonnées au stock).
- À chaque affichage du panier et à chaque étape du tunnel, il est **revalidé** : produit désactivé retiré, quantité ramenée au stock disponible,
  avec un message explicite. Une commande n'est **jamais** passée avec des quantités que le client n'a pas vues.
- Les actions (`/cart/add`, `/cart/items/{id}`…) fonctionnent en formulaire HTML classique **et** en JSON (`Accept: application/json`) pour Alpine.

### Tunnel de commande

1. **Coordonnées** : e-mail (invité), adresse de livraison, facturation identique ou non, enregistrement dans le carnet.
   Un client connecté retrouve son adresse par défaut et peut choisir parmi ses adresses enregistrées.
2. **Livraison et paiement** : modes disponibles pour le pays de livraison, avec leur prix ; note de commande.
3. **Vérification** : récapitulatif modifiable section par section, acceptation des CGV,
   bouton « Commander avec obligation de paiement » (mention exigée par la directive européenne 2011/83/UE).

Une étape n'est accessible que si les précédentes sont complètes (liens profonds et bouton « Retour » toujours cohérents).
La validation est **atomique** : verrouillage des variantes (`SELECT … FOR UPDATE`), contrôle et décrément du stock,
création de la commande et suppression du panier dans une seule transaction. Deux clients ne peuvent pas acheter le dernier article en même temps.

### Statuts de commande

`pending_payment` → `paid` → `processing` → `shipped` → `delivered`, et `cancelled` / `refunded`.
Les transitions sont contrôlées par `OrderStateMachine`, qui **remet en stock** lors d'une annulation ou d'un remboursement
(avant expédition) et déclenche `OrderStatusChangedEvent` (e-mail au client pour payée, expédiée, annulée, remboursée).

```bash
php bin/console astra:order:list --status=pending_payment
php bin/console astra:order:status AS-261104-7KQ4M2          # détail et transitions possibles
php bin/console astra:order:status AS-261104-7KQ4M2 paid     # virement reçu
php bin/console astra:cart:purge                             # à lancer chaque jour (cron)
```

Le client peut annuler lui-même une commande en attente de paiement depuis son compte.

## Promotions et codes promo

Une `Promotion` est **automatique** (sans code) ou déclenchée par un **code promo** saisi dans le panier.

| Réglage | Effet |
|---|---|
| `type` | `percentage` (valeur en points de base : 1000 = 10 %), `fixed_amount` (en centimes), `free_shipping` |
| `code` | `null` = automatique ; sinon le code (insensible à la casse) |
| `label` | Texte affiché au client (« −10 % de bienvenue ») |
| `categories` / `products` | Portée (sous-catégories comprises). Vide = tout le catalogue |
| `minSubtotal` | Minimum d'achat (avant remises) |
| `maxDiscount` | Plafond d'une remise en pourcentage |
| `startsAt` / `endsAt` | Période de validité |
| `usageLimit` / `usageLimitPerCustomer` | Nombre d'utilisations total / par client (compte ou adresse e-mail) |
| `exclusive` | Non cumulable : appliquée seule si elle est plus avantageuse que le cumul des autres |
| `excludeSaleItems` | Ne s'applique pas aux articles déjà soldés (prix barré) |
| `showOnProductPage` | Promotion automatique annoncée sur les fiches produits concernées |
| `priority` | Ordre d'application des promotions cumulables |

```php
$promotion = (new Promotion('Soldes d\'hiver', PromotionType::Percentage, 2000))
    ->setLabel('−20 % sur les sweats')
    ->addCategory($sweats)
    ->setStartsAt(new \DateTimeImmutable('2027-01-08 08:00'))
    ->setEndsAt(new \DateTimeImmutable('2027-02-04 20:00'));

$coupon = (new Promotion('Bienvenue', PromotionType::FixedAmount, 1000))
    ->setCode('BIENVENUE')->setMinSubtotal(5000)->setUsageLimitPerCustomer(1);
```

Fonctionnement :

- Les promotions cumulables s'appliquent l'une après l'autre (par priorité) sur ce qui reste de chaque ligne : un article ne passe
  jamais sous 0 €. Une promotion exclusive est comparée à ce cumul et **le client obtient toujours la meilleure offre**.
- La remise est **répartie au centime près sur les lignes** concernées ; la TVA est calculée sur les montants remisés (conforme aux
  factures européennes) et le seuil de livraison offerte s'applique au montant remisé.
- Message précis en cas de refus du code : inconnu, pas encore valable, expiré, épuisé, déjà utilisé, minimum non atteint
  (« ajoutez encore 15,00 € »), aucun article concerné. Si le panier change et que le code ne s'applique plus, il est retiré avec un message.
- À la validation, les limites d'utilisation sont revérifiées **sous verrou** (un code à usage unique ne peut pas servir deux fois
  simultanément) et le compteur est incrémenté ; une commande annulée rend l'utilisation.
- La commande conserve une copie des promotions appliquées (libellé, code, montant) et la remise de chaque ligne, affichées dans le
  récapitulatif, l'e-mail de confirmation et l'espace client.

```bash
php bin/console astra:promotion:list     # statut, utilisations, période
```

## Bannières d'annonces

Une `Announcement` est un message programmé, placé à l'un des emplacements suivants :

| `placement` | Où |
|---|---|
| `top_bar` | Bandeau au-dessus de l'en-tête, sur toutes les pages ; plusieurs messages défilent |
| `home` | Page d'accueil, sous le bandeau principal |
| `catalog` | En haut des listes de produits |
| `cart` | Panier et tunnel de commande |

```php
$announcement = (new Announcement('−10 % sur votre première commande avec le code'))
    ->setPromotion($coupon)                      // affiche le code, et disparaît quand la promotion n'est plus active
    ->setTone('promo')                           // neutral | promo | info | warning
    ->setLinkUrl('/c/nouveautes')->setLinkLabel('Voir les nouveautés')
    ->setStartsAt(new \DateTimeImmutable('2027-01-08'))
    ->setDismissible(true);                      // le visiteur peut la masquer (mémorisé dans son navigateur)
```

- **Accessibilité du défilement** (WCAG 2.2.2) : bouton pause, boutons précédent/suivant, pause au survol et au focus, aucun
  défilement automatique pour les personnes ayant activé « réduire les animations », et messages non vocalisés pendant le défilement
  (ils le sont dès que le visiteur navigue lui-même). Sans JavaScript, le premier message s'affiche.
- **Cache** : les annonces sont lues depuis le cache (invalidé à chaque modification) et filtrées par date au moment de l'affichage ;
  l'ETag des pages change quand une annonce ou une promotion commence, se termine ou est modifiée. Les pages catalogue restent cachables :
  le masquage par le visiteur est géré côté navigateur.
- Une annonce peut être réservée à une langue (`locale: 'fr'`).

## Recherche

### Moteur intégré (base de données)

Activé par défaut, sans aucun service à installer. Chaque produit a un texte de recherche **normalisé**, mis à jour automatiquement
à chaque enregistrement : nom, marque, description courte, catégories, valeurs d'options (« Écru », « XL »), références (SKU).

- Insensible aux accents et à la casse (« ecru » trouve « Écru »), pluriels (« sweats »), mots composés (« tshirt », « t-shirt »
  et « t shirt » se valent), références (« ts-m »), synonymes configurables.
- Tous les mots doivent être présents ; classement par pertinence (nom qui commence par la recherche, mots trouvés dans le nom,
  puis en stock et nouveautés). Filtres, tri et pagination fonctionnent sur les résultats.
- Adapté jusqu'à quelques dizaines de milliers de produits. Après le renommage d'une catégorie : `php bin/console astra:search:reindex`.

### Suggestions instantanées

Le champ de recherche de l'en-tête propose produits (image, prix) et catégories dès 2 caractères, via `/search/suggest` (réponse JSON
publique, cachable). C'est un **combobox ARIA 1.2** : flèches pour parcourir, Entrée pour ouvrir, Échap pour fermer puis effacer,
nombre de suggestions annoncé aux lecteurs d'écran. Requêtes temporisées et annulées si elles sont dépassées.

### Module Meilisearch (gros catalogues, fautes de frappe)

[Meilisearch](https://www.meilisearch.com) apporte la tolérance aux fautes de frappe (« swaet » trouve « sweat ») et des temps de
réponse de quelques millisecondes sur des centaines de milliers de produits. Aucun SDK n'est nécessaire :

```bash
composer require symfony/http-client
docker run -d -p 7700:7700 -e MEILI_MASTER_KEY=change-me getmeili/meilisearch:v1.12
```

```yaml
astra_checkout:
    search:
        engine: meilisearch
        meilisearch:
            url: '%env(MEILISEARCH_URL)%'
            api_key: '%env(MEILISEARCH_KEY)%'
```

```bash
php bin/console astra:search:reindex     # crée l'index, applique les réglages et indexe tout le catalogue
```

- Les produits sont **réindexés automatiquement** après chaque modification (prix, stock, nom, désactivation…).
- Meilisearch ne renvoie que des identifiants : les produits sont relus en base, donc **prix et stock sont toujours exacts**.
- Si Meilisearch est injoignable ou lent (`timeout`), **le moteur base de données répond à sa place** et l'erreur est journalisée :
  la recherche ne tombe jamais en panne. Une indexation échouée ne bloque pas non plus l'enregistrement.
- Pour Elasticsearch, Algolia, Typesense… implémentez `SearchEngineInterface` et indiquez l'id du service dans `search.engine`.

## Optimisation des données et performances

- **Pages catalogue cachables par un reverse proxy.** Accueil, PLP et PDP ne contiennent aucune donnée personnelle et n'ouvrent pas
  la session : elles sont servies avec `Cache-Control: public, s-maxage=300, stale-while-revalidate=60` et un **ETag**
  (le navigateur revalide avec une réponse `304` sans corps). Le badge du panier, le mini-panier et le prénom du client sont chargés
  par une seule requête JSON privée (`/cart/summary`). Résultat : derrière Varnish, Symfony HttpCache ou un CDN, une page produit
  ne sollicite pas PHP.
- **Protection CSRF sans session** pour le panier : contrôle des en-têtes `Sec-Fetch-Site` / `Origin` (ce qui permet de garder
  le formulaire « Ajouter au panier » dans une page en cache). Les formulaires du tunnel et du compte utilisent les jetons CSRF Symfony.
- **Listes en 3 requêtes, quelle que soit la taille du catalogue** : un `COUNT`, la liste des identifiants de la page (filtres et tris sur
  des colonnes **dénormalisées et indexées** du produit : prix mini/maxi, prix barré, en stock — aucune jointure sur les variantes),
  puis l'hydratation de ces seuls produits avec leurs images, en lecture seule (`HINT_READ_ONLY`, pas de suivi des modifications).
  Pas de N+1 : les cartes produits n'accèdent jamais aux variantes.
- **Fiche produit en 2 requêtes** (produit + variantes, puis images + catégories) pour éviter le produit cartésien de plusieurs jointures.
  Les données des variantes sont injectées une fois dans la page : changer de taille ou de couleur ne fait **aucune requête**.
- **Panier en 1 requête** (panier, lignes, variantes, produits, images).
- **Menu des catégories** construit en une requête sur des tableaux (sans entités) et mis en cache (`cache.app`), invalidé automatiquement
  à chaque modification de catégorie.
- **Pagination stable** (tri toujours départagé par l'identifiant), redirection des pages hors limites, `noindex` sur les pages filtrées.
- **Front léger** : CSS 42 Ko et JS 60 Ko minifiés (≈ 8 Ko + 22 Ko compressés, Alpine compris), polices système (aucune police web),
  `defer`, URLs versionnées par empreinte (cache d'un an possible), images en `loading="lazy"` avec `width`/`height` (pas de décalage
  de mise en page), première image de la fiche produit préchargée en `fetchpriority="high"`.
- **Images** : si [`romain-genin/symfony-image-optimizer-bundle`](https://github.com/Romain-Genin/symfony_imageoptim) est installé, toutes les images
  produits passent automatiquement par `responsive_image()` (AVIF/WebP, `srcset` adapté à l'écran) sans rien changer aux templates.
- Compatible avec les runtimes persistants (FrankenPHP worker, RoadRunner) : les services avec état implémentent `ResetInterface`.

- **Promotions sans surcoût** : la liste des promotions automatiques est mise en cache ; quand aucune promotion n'est active, le calcul
  d'un panier ne fait aucune requête supplémentaire.

## Accessibilité

Objectif **WCAG 2.2 niveau AA** (RGAA 4.1). Les templates sont audités à chaque exécution des tests (voir `AccessibilityTest`)
et ont été vérifiés avec axe-core en desktop et mobile (aucune violation).

- **Structure** : langue de la page, titre unique par page, un seul `h1`, hiérarchie de titres sans saut, landmarks
  (`header`, `nav` nommées, `main`, `footer`), lien d'évitement « Aller au contenu principal », fil d'Ariane avec `aria-current="page"`.
- **Clavier** : tout est utilisable au clavier, focus visible et contrasté sur chaque élément, aucun `tabindex` positif.
  Les fenêtres (mini-panier, menu mobile, filtres mobiles) sont des `<dialog>` natifs : focus piégé, `Échap` pour fermer, retour du focus.
  Galerie produit au clavier (flèches, Début, Fin).
- **Lecteurs d'écran** : une région `aria-live` annonce l'ajout au panier, le changement de variante (« Bleu / M — 25,00 € — En stock »),
  l'image affichée. Les prix soldés sont annoncés « Prix soldé / Prix initial » (`<ins>`/`<del>`). Les boutons répétés ont un nom unique
  (« Retirer T-shirt bleu – M »). Badge du panier vocalisé (« Panier, 3 articles »). Étapes du tunnel avec `aria-current="step"`.
- **Formulaires** : chaque champ a un libellé visible ; les champs facultatifs sont indiqués « (facultatif) » ; aides et erreurs reliées
  au champ (`aria-describedby`, `aria-invalid`) ; **récapitulatif des erreurs** en tête de formulaire, qui reçoit le focus, avec un lien
  vers chaque champ ; groupes de choix en `fieldset`/`legend` ; attributs `autocomplete` partout (adresse, e-mail, mot de passe…) pour le
  remplissage automatique (critère 1.3.5) ; mot de passe affichable ; pas de confirmation de mot de passe inutile.
- **Visuel** : contrastes ≥ 4,5:1 pour le texte et ≥ 3:1 pour les bordures de champs, états jamais signalés par la seule couleur
  (icône + texte pour le stock, motif barré pour les variantes épuisées), cibles tactiles d'au moins 44 × 44 px, texte redimensionnable,
  affichage jusqu'à 320 px de large sans défilement horizontal, mode « contraste élevé » de Windows, préférence « réduire les animations » respectée.
- **Sans JavaScript**, la boutique reste utilisable : menu, filtres, tri, choix de la variante (liste déroulante), ajout au panier,
  mise à jour des quantités et commande fonctionnent en HTML pur.

Ce qui reste de votre ressort : la pertinence des textes alternatifs des images produits (à défaut, le nom du produit est utilisé),
le contraste de vos couleurs si vous les changez, et l'accessibilité des contenus HTML saisis dans les descriptions.

## Intégrer à un site existant

AstraCheckout est **un e-commerce qui s'ajoute à votre site** : vous gardez vos pages, vos routes, votre gabarit, vos comptes
et votre Tailwind, et vous placez des **blocs** (Twig Components) dans vos templates. Le bundle fournit la logique (catalogue,
panier, promotions, commande, stock, e-mails) et les actions techniques (ajout au panier, code promo, suggestions de recherche…).

### Les blocs

| Bloc | Rôle | Principales options |
|---|---|---|
| `<twig:Astra:ProductList />` | Liste de produits complète : fil d'Ariane, titre, sous-catégories, filtres, tri, grille, pagination | `category`, `search`, `perPage`, `breadcrumb`, `header`, `filters`, `sorting`, `pagination`, `subcategories` |
| `<twig:Astra:ProductGrid />` | Grille de produits pour n'importe quelle page (accueil, landing, article…) | `category`, `onSale`, `sort`, `limit`, `products`, `eager` |
| `<twig:Astra:ProductCard />` | Carte produit (image, badges, nom, prix) | `product`, `headingLevel` |
| `<twig:Astra:ProductDetail />` | Fiche produit complète : galerie, achat, description, produits associés, JSON-LD | `product` (objet ou slug), `breadcrumb`, `description`, `related` |
| `<twig:Astra:ProductGallery />` · `<twig:Astra:BuyBox />` · `<twig:Astra:RelatedProducts />` | Les parties de la fiche, pour composer la vôtre | `product`, `headingLevel` |
| `<twig:Astra:AddToCart />` · `<twig:Astra:ProductPrice />` | Bouton d'ajout au panier / prix accessible, n'importe où | `product`, `variant`, `label` / `price`, `compareAt` |
| `<twig:Astra:Cart />` | Panier complet : lignes, quantités, code promo, livraison offerte, totaux | `heading`, `announcements` |
| `<twig:Astra:Checkout />` | Tout le tunnel de commande (étape dans `?step=`) | `step`, `summary` |
| `<twig:Astra:OrderConfirmation />` | Page « merci » (paramètre de route `{token}`) | `token` |
| `<twig:Astra:OrderHistory />` · `<twig:Astra:OrderDetail />` | Commandes du client, détail d'une commande (`{number}`) | `perPage`, `pagination` |
| `<twig:Astra:AddressBook />` · `<twig:Astra:AddressForm />` | Carnet d'adresses, ajout / modification (`{id}`) | — |
| `<twig:Astra:CartButton />` · `<twig:Astra:SearchBox />` · `<twig:Astra:AccountLink />` · `<twig:Astra:CategoryMenu />` | Widgets pour votre en-tête | `class`, `id`, `icon`, `depth`… |
| `<twig:Astra:Announcements />` | Annonces programmées (`placement="top_bar"` : bandeau défilant) | `placement` |
| `<twig:Astra:Breadcrumb />` · `<twig:Astra:MiniCart />` | Fil d'Ariane (+ JSON-LD), mini-panier (ajouté automatiquement) | `items` |

Tout attribut HTML passé à un bloc (`class`, `id`, `data-*`…) est reporté sur son élément racine. Certaines parties se remplacent
sans copier le template : `<twig:block name="card">` dans `ProductList` / `ProductGrid` (la variable `product` est disponible),
`after_buy_box` dans `ProductDetail`, `empty` dans `OrderHistory`. Les templates se surchargent aussi à la manière Symfony
(`templates/bundles/AstraCheckoutBundle/components/Astra/ProductCard.html.twig`).

### Vos pages

Déclarez vos routes dans `astra_checkout.pages` : tous les liens de la boutique (cartes produits, fil d'Ariane, panier, tunnel,
e-mails…) pointent vers elles, et les pages équivalentes du bundle ne sont plus enregistrées.

```yaml
# config/packages/astra_checkout.yaml
astra_checkout:
    pages:
        catalog: app_shop              # /boutique ou ce que vous voulez
        category: app_category         # paramètre {slug}
        product: app_product           # paramètre {slug}
        search: app_search             # ?q=
        cart: app_cart
        checkout: app_checkout         # accepte POST ; étape dans ?step=
        confirmation: app_order_thanks # paramètre {token}
        account: app_my_orders
        orders: app_my_orders
        order: app_my_order            # paramètre {number}
        addresses: app_my_addresses
        address_new: app_address_new   # accepte POST
        address_edit: app_address_edit # accepte POST, paramètre {id}
    routing:
        home: false                    # votre "/" reste votre page d'accueil
```

```php
// src/Controller/ShopController.php — des contrôleurs ordinaires
#[Route('/produit/{slug}', name: 'app_product')]
public function product(string $slug, ProductPresenter $products): Response
{
    $product = $products->get($slug);   // 404 si introuvable ou désactivé

    return $this->render('shop/product.html.twig', ['product' => $product]);
}

#[Route('/commande', name: 'app_checkout', methods: ['GET', 'POST'])]
public function checkout(): Response
{
    return $this->render('shop/checkout.html.twig');
}
```

```twig
{# templates/shop/product.html.twig #}
{% extends 'base.html.twig' %}
{% block title %}{{ product.name }}{% endblock %}
{% block body %}
    <div class="container mx-auto">
        <twig:Astra:ProductDetail :product="product" />
    </div>
{% endblock %}

{# templates/shop/checkout.html.twig #}
{% extends 'base.html.twig' %}
{% block body %}<twig:Astra:Checkout class="container mx-auto py-8" />{% endblock %}

{# n'importe quelle page : accueil, landing… #}
<h2>Nouveautés</h2>
<twig:Astra:ProductGrid limit="4" />
<twig:Astra:ProductGrid category="sweats" onSale sort="price_asc" limit="8" />
```

- **Formulaires** (tunnel, adresses) : ils sont envoyés à votre page elle-même, qui doit accepter POST. Les redirections (303),
  les erreurs de validation (422) et les 404 (commande ou adresse introuvable) sont appliquées automatiquement à votre réponse :
  votre contrôleur reste un simple `render()`.
- **Pages de compte** : les blocs `OrderHistory`, `OrderDetail`, `AddressBook` et `AddressForm` exigent un client connecté ;
  un visiteur anonyme est envoyé vers votre page de connexion par le pare-feu, comme avec `denyAccessUnlessGranted()`.
- **Cache HTTP** de vos pages catalogue : `CatalogListing::build()` et `ProductPresenter::fingerprint()` fournissent de quoi poser un ETag
  (voir `CatalogController` du bundle, avec `CatalogCacheHelper`).
- Vous n'êtes pas obligé de tout remplacer : chaque page non déclarée dans `pages` reste celle du bundle (affichée dans votre gabarit
  avec `astra_checkout.layout`). Les pages du bundle sont d'ailleurs construites avec ces mêmes blocs.

### Votre en-tête

```twig
{# templates/base.html.twig #}
<header class="…">
    <a href="/">Mon site</a>
    <twig:Astra:CategoryMenu class="hidden lg:flex" depth="2" />
    <twig:Astra:SearchBox id="site-search" class="w-72" />
    <twig:Astra:AccountLink class="nav-link" :icon="false" />
    <twig:Astra:CartButton class="nav-link" />
</header>
```

Dès qu'une page contient un bloc de la boutique, les assets sont ajoutés avant `</head>`, la configuration et le mini-panier avant
`</body>` : votre gabarit n'a rien d'autre à inclure (`assets.auto_inject: false` pour désactiver ; `{{ astra_head() }}` et
`{{ astra_body_end() }}` pour les placer vous-même).

### Réutiliser vos comptes utilisateurs

Indiquez votre entité dans `customer.class` : **elle n'a pas besoin d'être modifiée** (aucune interface, aucune relation à ajouter).

```yaml
astra_checkout:
    customer:
        class: App\Entity\User
        properties: { email: email, first_name: firstName, last_name: lastName, phone: ~ }
    account:
        login_route: app_login
        logout_route: app_logout
        register_route: app_register
```

- Commandes, paniers et adresses sont reliés à votre entité (Doctrine `resolve_target_entities`) ; la table `astra_customer` n'est pas créée.
- La boutique lit l'e-mail, le prénom, le nom et le téléphone via `customer.properties` (propriétés ou getters, `null` si absent ;
  le formulaire de commande est prérempli avec ce qui existe). Pour tout contrôler, votre entité peut implémenter
  `RomainGenin\AstraCheckoutBundle\Customer\CustomerInterface`.
- Connexion, inscription et déconnexion restent celles de votre site ; les pages équivalentes du bundle ne sont pas enregistrées.
  Après la connexion, le client revient au tunnel de commande et son panier anonyme est fusionné dans le sien.

### Exemple : brancher la boutique sur un site existant

Cas d'un site qui a déjà ses utilisateurs (`App\Entity\User`, connexion `app_login`), son gabarit Tailwind + Alpine
et sa page d'accueil, comme [`romain-genin/symfony-starter`](https://packagist.org/packages/romain-genin/symfony-starter) :

```yaml
# config/packages/astra_checkout.yaml
astra_checkout:
    shop_name: 'Mon site'
    layout: base.html.twig                 # pages de la boutique dans le gabarit du site
    customer:
        class: App\Entity\User             # mêmes comptes, même connexion, panier fusionné à la connexion
        properties: { email: email, first_name: firstName, last_name: lastName, phone: ~ }
    account:
        login_route: app_login
        logout_route: app_logout
        register_route: app_register
    routing:
        home: false                        # l'accueil reste celui du site
        paths:
            catalog: /boutique
            search: /boutique/recherche
            category: /boutique/categorie
            product: /boutique/produit
            cart: /panier
            checkout: /commande
            account: /mon-compte/boutique  # commandes et adresses, à côté de l'espace compte du site
    mailer:
        from: no-reply@example.com
```

```yaml
# config/routes/astra_checkout.yaml (avec un site multilingue : ajoutez le même prefix /{_locale} que vos pages)
astra_checkout:
    resource: '@AstraCheckoutBundle/config/routes.yaml'
```

Dans le gabarit :

- `<twig:Astra:CartButton />` (et un lien vers `path('astra_catalog')`) dans l'en-tête ;
- `{% block meta %}{% endblock %}` dans `<head>` si votre gabarit ne l'a pas : les fiches produits y placent
  description, canonical et Open Graph (retirez alors les vôtres pour ces pages) ;
- Alpine importé dans votre build (`assets/app.js`, cas du starter) : `assets: { js: none }` et enregistrez
  les composants avant `Alpine.start()` (voir [Alpine.js](#alpinejs)).

Puis `php bin/console make:migration && php bin/console doctrine:migrations:migrate` (tables `astra_*`).

### Tailwind : votre thème, une seule feuille de style

```yaml
astra_checkout:
    assets: { css: false, js: none }   # votre build Tailwind + votre Alpine
```

```css
/* assets/styles/app.css (Tailwind v4) */
@import "tailwindcss";
@import "../../vendor/romain-genin/symfony-ecom/assets/styles/astra.css";

@theme {
  /* couleurs de la boutique = les vôtres */
  --color-accent-50: var(--color-primary-50);
  --color-accent-700: var(--color-primary-700);
  --color-accent-800: var(--color-primary-800);
}
```

`astra.css` déclare les sources à scanner (`@source` : templates et blocs de la boutique), ses couleurs et ses composants
(`.astra-btn-primary`, `.astra-input`…). Il n'ajoute **aucun style global** (ni reset, ni règle sur `html`/`body`).
Les classes que vous passez aux blocs (`class="…"`) sont générées par votre build comme les autres.

### Alpine.js

| `assets.js` | Quand |
|---|---|
| `bundled` | Votre site n'utilise pas Alpine : la boutique charge le sien |
| `components` | Votre site charge Alpine avec un `<script>` : la boutique ajoute ses composants (chargés avant Alpine) |
| `none` | Votre site importe Alpine dans son build (AssetMapper, Vite…) : enregistrez les composants de la boutique |

```js
// assets/app.js (AssetMapper : le chemin @romain-genin/symfony-ecom est déclaré automatiquement)
import Alpine from 'alpinejs';
import { registerAstraCheckout } from '@romain-genin/symfony-ecom/js/components.js';

registerAstraCheckout(Alpine);
Alpine.start();
```

Avec Vite ou Webpack, importez `vendor/romain-genin/symfony-ecom/assets/js/components.js`.

### Actions techniques

Ces routes restent fournies par le bundle (formulaires et appels JSON des blocs), sous les chemins `routing.paths`
(par défaut `/cart/…`, `/search/suggest`, `/account/…`) : ajout / modification / suppression au panier, code promo, résumé du
panier, suggestions de recherche, annulation d'une commande, suppression / adresse par défaut.

## Personnaliser l'apparence

### Couleurs

Les couleurs sont des variables CSS : changez-les **sans recompiler**, dans une feuille chargée après celle du bundle.

```css
:root {
  --color-accent-50: #f0fdfa;
  --color-accent-600: #0d9488;
  --color-accent-700: #0f766e;   /* boutons, liens : contraste ≥ 4,5:1 avec le blanc */
  --color-accent-800: #115e59;
}
```

### Gabarit et templates

Pour construire vos propres pages avec les blocs de la boutique, voir [Intégrer à un site existant](#intégrer-à-un-site-existant).

Tout template se surcharge à la manière Symfony : copiez par exemple `templates/catalog/show.html.twig` du bundle vers
`templates/bundles/AstraCheckoutBundle/catalog/show.html.twig` dans votre application.

Si vous ajoutez des classes Tailwind dans des templates surchargés, utilisez votre propre build Tailwind (voir ci-dessus) :
elles seront générées automatiquement.

### Textes

Tous les textes sont dans le domaine de traduction `AstraCheckout` (français et anglais fournis). Surchargez n'importe quelle clé dans
`translations/AstraCheckout.fr.yaml` de votre application.

### Fonctions Twig

| Fonction / filtre | Usage |
|---|---|
| `1990\|astra_price` | `19,90 €` selon la langue et la devise |
| `astra_image(path, {alt, sizes, class, width, height})` | Image produit optimisée (responsive si ImageOptimizer est installé) |
| `astra_categories()` | Arborescence des catégories (en cache) |
| `astra_asset('astra-checkout.css')` | URL versionnée d'un asset du bundle |
| `'FR'\|astra_country` | Nom du pays traduit |
| `astra_path('product', {slug: …})`, `astra_url(…)` | Lien vers une page de la boutique (la vôtre si déclarée dans `pages`) |
| `astra_head()`, `astra_body_end()` | Assets, configuration et mini-panier (injectés automatiquement dès qu'une page contient un bloc) |
| `astra_home_path()` | Accueil de la boutique, ou `/` si elle est désactivée |
| `astra.settings` | Configuration de la boutique (`astra.settings.shopName`…) |

## Étendre : livraison, paiement, événements

### Mode de livraison sur mesure

Implémentez `ShippingMethodInterface` : le service est détecté automatiquement.

```php
use RomainGenin\AstraCheckoutBundle\Entity\Cart;
use RomainGenin\AstraCheckoutBundle\Shipping\ShippingMethodInterface;

final class ColissimoRelayShipping implements ShippingMethodInterface
{
    public function getCode(): string { return 'colissimo_relay'; }
    public function getLabel(): string { return 'Point relais Colissimo'; }
    public function getDescription(): ?string { return 'Livré en 3 à 5 jours dans le point relais de votre choix.'; }
    public function getFreeAbove(): ?int { return 4900; }
    public function supports(Cart $cart): bool { return 'FR' === $cart->getShippingAddress()->getCountryCode(); }
    public function calculate(Cart $cart, int $subtotal): int { return $subtotal >= 4900 ? 0 : 390; }
}
```

### Paiement en ligne (exemple Stripe Checkout)

Implémentez `PaymentMethodInterface`. `process()` est appelé juste après la création de la commande (statut `pending_payment`,
stock réservé) : renvoyez une redirection vers la page de paiement du prestataire.

```php
use RomainGenin\AstraCheckoutBundle\Entity\{Cart, Order};
use RomainGenin\AstraCheckoutBundle\Payment\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StripePayment implements PaymentMethodInterface
{
    public function __construct(private \Stripe\StripeClient $stripe, private UrlGeneratorInterface $urls) {}

    public function getCode(): string { return 'stripe'; }
    public function getLabel(): string { return 'Carte bancaire'; }
    public function getDescription(): ?string { return 'Visa, Mastercard, CB, Apple Pay, Google Pay'; }
    public function supports(Cart $cart): bool { return true; }
    public function getInstructions(Order $order): ?string { return null; }

    public function process(Order $order, Request $request): ?Response
    {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->getEmail(),
            'client_reference_id' => $order->getNumber(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => strtolower($order->getCurrency()), 'unit_amount' => $order->getTotal(), 'product_data' => ['name' => 'Commande ' . $order->getNumber()]],
            ]],
            'success_url' => $this->urls->generate('astra_checkout_confirmation', ['token' => $order->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->urls->generate('astra_cart', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return new RedirectResponse($session->url, 303);
    }
}
```

Dans le contrôleur de votre webhook Stripe (`checkout.session.completed`), après vérification de la signature :

```php
$order = $orders->findOneBy(['number' => $session->client_reference_id]);
$stateMachine->apply($order, OrderStatus::Paid);   // e-mail « paiement reçu » envoyé automatiquement
```

### Événements

| Événement | Quand |
|---|---|
| `CartChangedEvent` | Ajout, modification ou suppression d'une ligne du panier |
| `OrderPlacedEvent` | Commande enregistrée (après la transaction) — export ERP, analytics, notification à l'équipe… |
| `OrderStatusChangedEvent` | Changement de statut (`from`, `to`) |

```php
#[AsEventListener]
public function onOrderPlaced(OrderPlacedEvent $event): void
{
    $this->slack->notify(sprintf('Nouvelle commande %s : %d €', $event->order->getNumber(), $event->order->getTotal() / 100));
}
```

## Back-office

Le bundle se concentre sur le front. Les entités sont des entités Doctrine standard : un back-office
[EasyAdmin](https://symfony.com/bundles/EasyAdminBundle) se branche directement.

```bash
composer require easycorp/easyadmin-bundle
```

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, CollectionField, MoneyField, SlugField, TextEditorField, TextField};
use RomainGenin\AstraCheckoutBundle\Entity\Product;

final class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return Product::class; }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name');
        yield SlugField::new('slug')->setTargetFieldName('name');
        yield AssociationField::new('categories');
        yield TextEditorField::new('description')->hideOnIndex();
        yield MoneyField::new('minPrice')->setCurrency('EUR')->setStoredAsCents()->onlyOnIndex();
        yield BooleanField::new('inStock')->onlyOnIndex()->renderAsSwitch(false);
        yield BooleanField::new('enabled');
        yield CollectionField::new('variants')->useEntryCrudForm(ProductVariantCrudController::class)->hideOnIndex();
    }
}
```

Pour les promotions et les annonces, des `CrudController` sur `Promotion` et `Announcement` suffisent (champs `ChoiceField` pour
`type`/`placement`, `AssociationField` pour les catégories, produits et la promotion liée, `DateTimeField` pour la période).
Le cache des promotions et des annonces est invalidé automatiquement à chaque enregistrement.

Points d'attention :

- Modifiez les prix et stocks via `ProductVariant::setPrice()` / `setStock()` (ce que fait EasyAdmin) : ils mettent à jour les champs
  dénormalisés du produit utilisés par les listes.
- Changez le statut des commandes via `OrderStateMachine::apply()` (action personnalisée EasyAdmin) plutôt que par un champ libre,
  pour bénéficier de la remise en stock et des e-mails.
- Pour les images, couplez avec `romain-genin/symfony-image-optimizer-bundle` : chaque fichier envoyé est optimisé sans perte et son texte
  alternatif IPTC peut pré-remplir `ProductImage::alt`.

## Développement du bundle

```bash
composer install
vendor/bin/phpunit                 # 75 tests : catalogue, panier, tunnel, compte, promotions, annonces, recherche, site existant, accessibilité
```

Prévisualiser la boutique dans un navigateur :

```bash
php tests/app/bin/console doctrine:schema:create
php tests/app/bin/console astra:demo:load
ln -sfn ../../../../public tests/app/public/bundles/astracheckout
php -S 127.0.0.1:8000 -t tests/app/public tests/app/public/index.php
```

Front (Tailwind CSS v4 + Alpine.js + esbuild) : les sources sont dans `assets/`, les fichiers compilés dans `public/`
(versionnés, pour que les applications n'aient pas besoin de Node).

```bash
npm ci
npm run build        # ou npm run watch:css / npm run watch:js
```

## Licence

MIT
