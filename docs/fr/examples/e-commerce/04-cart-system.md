# Système de Panier - E-commerce

Cette section présente l'implémentation complète du système de panier d'achat, démontrant la gestion des sessions, persistance, optimisations et intégration avec MulerTech Database ORM.

## Table des matières

- [Architecture du panier](#architecture-du-panier)
- [Repository et entités](#repository-et-entités)
- [Service de gestion du panier](#service-de-gestion-du-panier)
- [Gestion des sessions](#gestion-des-sessions)
- [Merge des paniers](#merge-des-paniers)
- [Calculs et promotions](#calculs-et-promotions)
- [Système de coupons](#système-de-coupons)
- [Validation et vérifications](#validation-et-vérifications)
- [Cache et performance](#cache-et-performance)
- [API du panier](#api-du-panier)

## Architecture du panier

### Entités du panier (complément)

#### CartItem - Articles du panier

```php
<?php

namespace App\Entity\Cart;

use App\Entity\BaseEntity;
use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Type\MoneyType;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'cart_items')]
class CartItem extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'cart_id', type: 'int')]
    private int $cartId;
    
    #[MtColumn(name: 'product_id', type: 'int')]
    private int $productId;
    
    #[MtColumn(name: 'variant_id', type: 'int', nullable: true)]
    private ?int $variantId = null;
    
    #[MtColumn(type: 'int')]
    private int $quantity;
    
    #[MtColumn(name: 'unit_price', type: MoneyType::class)]
    private int $unitPrice;
    
    #[MtColumn(name: 'total_price', type: MoneyType::class)]
    private int $totalPrice;
    
    #[MtColumn(name: 'custom_attributes', type: 'json', nullable: true)]
    private ?array $customAttributes = null; // Personnalisation, gravure, etc.
    
    #[MtColumn(name: 'added_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    private \DateTimeInterface $addedAt;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Cart::class, inversedBy: 'items')]
    private ?Cart $cart = null;
    
    #[MtRelation('ManyToOne', targetEntity: Product::class)]
    private ?Product $product = null;
    
    #[MtRelation('ManyToOne', targetEntity: ProductVariant::class)]
    private ?ProductVariant $variant = null;
    
    public function __construct()
    {
        $this->addedAt = new \DateTimeImmutable();
    }
    
    public function calculateTotalPrice(): self
    {
        $this->totalPrice = $this->unitPrice * $this->quantity;
        return $this;
    }
    
    public function getDisplayName(): string
    {
        $name = $this->product?->getName() ?? 'Produit supprimé';
        
        if ($this->variant) {
            $attributes = [];
            foreach ($this->variant->getAttributes() as $key => $value) {
                $attributes[] = ucfirst($key) . ': ' . $value;
            }
            $name .= ' (' . implode(', ', $attributes) . ')';
        }
        
        return $name;
    }
    
    public function canIncrease(int $maxQuantity = null): bool
    {
        if ($maxQuantity && $this->quantity >= $maxQuantity) {
            return false;
        }
        
        // Vérifier le stock disponible
        $availableStock = $this->getAvailableStock();
        
        return $availableStock === null || $availableStock > $this->quantity;
    }
    
    public function getAvailableStock(): ?int
    {
        if ($this->variant) {
            return $this->variant->getAvailableStock();
        }
        
        if ($this->product && $this->product->getTrackInventory()) {
            return $this->product->getAvailableStock();
        }
        
        return null; // Stock illimité
    }
    
    public function isValid(): bool
    {
        // Vérifier si le produit est encore actif et disponible
        if (!$this->product || !$this->product->getIsActive()) {
            return false;
        }
        
        // Vérifier si le variant est encore actif
        if ($this->variant && !$this->variant->getIsActive()) {
            return false;
        }
        
        // Vérifier le stock
        $availableStock = $this->getAvailableStock();
        if ($availableStock !== null && $availableStock < $this->quantity) {
            return false;
        }
        
        return true;
    }
    
    // Getters et setters...
}
```

## Repository et entités

### CartRepository

```php
<?php

namespace App\Repository\Cart;

use App\Entity\Cart\Cart;
use App\Entity\Customer\Customer;
use MulerTech\Database\ORM\Repository\EntityRepository;

class CartRepository extends EntityRepository
{
    protected function getEntityClass(): string
    {
        return Cart::class;
    }
    
    public function findBySessionId(string $sessionId): ?Cart
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('session_id = ? AND expires_at > ?')
            ->setParameter(0, $sessionId)
            ->setParameter(1, new \DateTimeImmutable())
            ->getQuery()
            ->getSingleResult();
    }
    
    public function findByCustomer(Customer $customer): ?Cart
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('customer_id = ? AND expires_at > ?')
            ->setParameter(0, $customer->getId())
            ->setParameter(1, new \DateTimeImmutable())
            ->getQuery()
            ->getSingleResult();
    }
    
    public function findActiveBySessionOrCustomer(string $sessionId, ?int $customerId = null): ?Cart
    {
        $qb = $this->createQueryBuilder()
                  ->select('*')
                  ->where('expires_at > ?')
                  ->setParameter(0, new \DateTimeImmutable());
        
        if ($customerId) {
            $qb->andWhere('(session_id = ? OR customer_id = ?)')
               ->setParameter(1, $sessionId)
               ->setParameter(2, $customerId)
               ->orderBy('customer_id', 'DESC'); // Priorité au panier client
        } else {
            $qb->andWhere('session_id = ?')
               ->setParameter(1, $sessionId);
        }
        
        return $qb->getQuery()->getSingleResult();
    }
    
    public function findExpiredCarts(\DateTimeInterface $before = null): array
    {
        $before = $before ?? new \DateTimeImmutable('-7 days');
        
        return $this->createQueryBuilder()
            ->select('*')
            ->where('expires_at < ?')
            ->setParameter(0, $before)
            ->getQuery()
            ->getResult();
    }
    
    public function cleanupExpiredCarts(int $batchSize = 100): int
    {
        $expiredCarts = $this->findExpiredCarts();
        $batches = array_chunk($expiredCarts, $batchSize);
        $deletedCount = 0;
        
        foreach ($batches as $batch) {
            foreach ($batch as $cart) {
                $this->em->remove($cart);
                $deletedCount++;
            }
            $this->em->flush();
            $this->em->clear(); // Libérer la mémoire
        }
        
        return $deletedCount;
    }
    
    public function getCartStatistics(): array
    {
        return $this->em->createQueryBuilder()
            ->select('
                COUNT(*) as total_carts,
                COUNT(CASE WHEN customer_id IS NOT NULL THEN 1 END) as customer_carts,
                COUNT(CASE WHEN customer_id IS NULL THEN 1 END) as guest_carts,
                AVG(
                    (SELECT SUM(ci.total_price) 
                     FROM cart_items ci 
                     WHERE ci.cart_id = c.id)
                ) as avg_cart_value,
                SUM(
                    (SELECT SUM(ci.quantity) 
                     FROM cart_items ci 
                     WHERE ci.cart_id = c.id)
                ) as total_items
            ')
            ->from('carts', 'c')
            ->where('c.expires_at > ?')
            ->setParameter(0, new \DateTimeImmutable())
            ->getQuery()
            ->getSingleResult();
    }
}
```

### CartItemRepository

```php
<?php

namespace App\Repository\Cart;

use App\Entity\Cart\CartItem;
use MulerTech\Database\ORM\Repository\EntityRepository;

class CartItemRepository extends EntityRepository
{
    protected function getEntityClass(): string
    {
        return CartItem::class;
    }
    
    public function findByCartWithProducts(int $cartId): array
    {
        return $this->em->createQueryBuilder()
            ->select('
                ci.*,
                p.name as product_name,
                p.slug as product_slug,
                p.is_active as product_active,
                pv.name as variant_name,
                pv.attributes as variant_attributes,
                pv.is_active as variant_active
            ')
            ->from('cart_items', 'ci')
            ->innerJoin('products', 'p', 'p.id = ci.product_id')
            ->leftJoin('product_variants', 'pv', 'pv.id = ci.variant_id')
            ->where('ci.cart_id = ?')
            ->orderBy('ci.added_at', 'ASC')
            ->setParameter(0, $cartId)
            ->getQuery()
            ->getResult();
    }
    
    public function findExistingItem(int $cartId, int $productId, ?int $variantId = null): ?CartItem
    {
        $qb = $this->createQueryBuilder()
                  ->select('*')
                  ->where('cart_id = ? AND product_id = ?')
                  ->setParameter(0, $cartId)
                  ->setParameter(1, $productId);
        
        if ($variantId) {
            $qb->andWhere('variant_id = ?')
               ->setParameter(2, $variantId);
        } else {
            $qb->andWhere('variant_id IS NULL');
        }
        
        return $qb->getQuery()->getSingleResult();
    }
    
    public function removeInvalidItems(int $cartId): int
    {
        // Supprimer les items dont le produit n'est plus actif
        $deletedCount = $this->em->createQueryBuilder()
            ->delete('cart_items', 'ci')
            ->where('ci.cart_id = ?')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM products p 
                WHERE p.id = ci.product_id AND p.is_active = 1
            )')
            ->setParameter(0, $cartId)
            ->getQuery()
            ->execute();
        
        // Supprimer les items dont le variant n'est plus actif
        $deletedCount += $this->em->createQueryBuilder()
            ->delete('cart_items', 'ci')
            ->where('ci.cart_id = ? AND ci.variant_id IS NOT NULL')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM product_variants pv 
                WHERE pv.id = ci.variant_id AND pv.is_active = 1
            )')
            ->setParameter(0, $cartId)
            ->getQuery()
            ->execute();
        
        return $deletedCount;
    }
}
```

## Service de gestion du panier

### CartService

```php
<?php

namespace App\Service\Cart;

use App\Entity\Cart\Cart;
use App\Entity\Cart\CartItem;
use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Entity\Customer\Customer;
use App\Repository\Cart\CartRepository;
use App\Repository\Cart\CartItemRepository;
use App\Service\Catalog\PricingService;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    private EntityManager $em;
    private CartRepository $cartRepository;
    private CartItemRepository $cartItemRepository;
    private PricingService $pricingService;
    private SessionInterface $session;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManager $em,
        CartRepository $cartRepository,
        CartItemRepository $cartItemRepository,
        PricingService $pricingService,
        SessionInterface $session,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->cartRepository = $cartRepository;
        $this->cartItemRepository = $cartItemRepository;
        $this->pricingService = $pricingService;
        $this->session = $session;
        $this->logger = $logger;
    }
    
    public function getCurrentCart(?Customer $customer = null): Cart
    {
        $sessionId = $this->session->getId();
        
        // Chercher un panier existant
        $cart = $this->cartRepository->findActiveBySessionOrCustomer(
            $sessionId, 
            $customer?->getId()
        );
        
        if ($cart) {
            // Étendre l'expiration et associer le client si nécessaire
            $cart->extendExpiry();
            
            if ($customer && !$cart->getCustomerId()) {
                $cart->setCustomerId($customer->getId());
                $cart->setCustomer($customer);
            }
            
            $this->em->flush();
            return $cart;
        }
        
        // Créer un nouveau panier
        return $this->createCart($sessionId, $customer);
    }
    
    public function addToCart(
        Product $product, 
        int $quantity = 1, 
        ?ProductVariant $variant = null,
        ?array $customAttributes = null,
        ?Customer $customer = null
    ): CartItem {
        // Validations
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        
        if (!$product->getIsActive()) {
            throw new \InvalidArgumentException('Product is not available');
        }
        
        if ($variant && !$variant->getIsActive()) {
            throw new \InvalidArgumentException('Product variant is not available');
        }
        
        // Vérifier le stock
        $this->validateStock($product, $variant, $quantity);
        
        $cart = $this->getCurrentCart($customer);
        
        // Chercher un item existant avec les mêmes caractéristiques
        $existingItem = $this->cartItemRepository->findExistingItem(
            $cart->getId(),
            $product->getId(),
            $variant?->getId()
        );
        
        if ($existingItem) {
            // Augmenter la quantité
            $newQuantity = $existingItem->getQuantity() + $quantity;
            $this->validateStock($product, $variant, $newQuantity);
            
            $existingItem->setQuantity($newQuantity);
            $this->updateItemPrice($existingItem, $customer);
            
            $this->em->flush();
            
            $this->logger->info('Cart item quantity updated', [
                'cart_id' => $cart->getId(),
                'product_id' => $product->getId(),
                'new_quantity' => $newQuantity
            ]);
            
            return $existingItem;
        }
        
        // Créer un nouvel item
        $cartItem = new CartItem();
        $cartItem->setCartId($cart->getId())
                 ->setCart($cart)
                 ->setProductId($product->getId())
                 ->setProduct($product)
                 ->setVariantId($variant?->getId())
                 ->setVariant($variant)
                 ->setQuantity($quantity)
                 ->setCustomAttributes($customAttributes);
        
        $this->updateItemPrice($cartItem, $customer);
        
        $this->em->persist($cartItem);
        $cart->addItem($cartItem);
        
        $this->em->flush();
        
        $this->logger->info('Product added to cart', [
            'cart_id' => $cart->getId(),
            'product_id' => $product->getId(),
            'variant_id' => $variant?->getId(),
            'quantity' => $quantity
        ]);
        
        return $cartItem;
    }
    
    public function updateQuantity(CartItem $cartItem, int $newQuantity, ?Customer $customer = null): void
    {
        if ($newQuantity <= 0) {
            $this->removeItem($cartItem);
            return;
        }
        
        // Valider le stock pour la nouvelle quantité
        $this->validateStock($cartItem->getProduct(), $cartItem->getVariant(), $newQuantity);
        
        $oldQuantity = $cartItem->getQuantity();
        $cartItem->setQuantity($newQuantity);
        
        $this->updateItemPrice($cartItem, $customer);
        
        $this->em->flush();
        
        $this->logger->info('Cart item quantity updated', [
            'cart_item_id' => $cartItem->getId(),
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity
        ]);
    }
    
    public function removeItem(CartItem $cartItem): void
    {
        $cart = $cartItem->getCart();
        $cart->removeItem($cartItem);
        
        $this->em->remove($cartItem);
        $this->em->flush();
        
        $this->logger->info('Item removed from cart', [
            'cart_id' => $cart->getId(),
            'cart_item_id' => $cartItem->getId()
        ]);
    }
    
    public function clearCart(Cart $cart): void
    {
        $itemCount = count($cart->getItems());
        
        foreach ($cart->getItems() as $item) {
            $this->em->remove($item);
        }
        
        $cart->clear();
        $this->em->flush();
        
        $this->logger->info('Cart cleared', [
            'cart_id' => $cart->getId(),
            'items_removed' => $itemCount
        ]);
    }
    
    public function mergeCarts(Cart $sourceCart, Cart $targetCart, ?Customer $customer = null): Cart
    {
        $this->logger->info('Starting cart merge', [
            'source_cart_id' => $sourceCart->getId(),
            'target_cart_id' => $targetCart->getId()
        ]);
        
        foreach ($sourceCart->getItems() as $sourceItem) {
            // Chercher un item équivalent dans le panier cible
            $existingItem = $this->cartItemRepository->findExistingItem(
                $targetCart->getId(),
                $sourceItem->getProductId(),
                $sourceItem->getVariantId()
            );
            
            if ($existingItem) {
                // Fusionner les quantités
                $newQuantity = $existingItem->getQuantity() + $sourceItem->getQuantity();
                
                try {
                    $this->validateStock($sourceItem->getProduct(), $sourceItem->getVariant(), $newQuantity);
                    $existingItem->setQuantity($newQuantity);
                    $this->updateItemPrice($existingItem, $customer);
                } catch (\InvalidArgumentException $e) {
                    // Si pas assez de stock, prendre le maximum possible
                    $maxQuantity = $this->getMaxAvailableQuantity($sourceItem->getProduct(), $sourceItem->getVariant());
                    if ($maxQuantity > $existingItem->getQuantity()) {
                        $existingItem->setQuantity($maxQuantity);
                        $this->updateItemPrice($existingItem, $customer);
                    }
                }
            } else {
                // Déplacer l'item vers le panier cible
                $sourceItem->setCartId($targetCart->getId());
                $sourceItem->setCart($targetCart);
                $this->updateItemPrice($sourceItem, $customer);
                $targetCart->addItem($sourceItem);
            }
        }
        
        // Supprimer le panier source
        $this->em->remove($sourceCart);
        $this->em->flush();
        
        $this->logger->info('Cart merge completed', [
            'target_cart_id' => $targetCart->getId(),
            'final_items_count' => count($targetCart->getItems())
        ]);
        
        return $targetCart;
    }
    
    public function validateCart(Cart $cart): array
    {
        $issues = [];
        
        // Nettoyer les items invalides
        $removedCount = $this->cartItemRepository->removeInvalidItems($cart->getId());
        if ($removedCount > 0) {
            $issues[] = [
                'type' => 'items_removed',
                'message' => "{$removedCount} produit(s) ne sont plus disponibles et ont été supprimés",
                'count' => $removedCount
            ];
        }
        
        // Vérifier et ajuster les quantités
        $items = $this->cartItemRepository->findByCartWithProducts($cart->getId());
        foreach ($items as $item) {
            if (!$item->isValid()) {
                continue; // Sera supprimé par removeInvalidItems
            }
            
            $maxQuantity = $this->getMaxAvailableQuantity($item->getProduct(), $item->getVariant());
            if ($maxQuantity !== null && $item->getQuantity() > $maxQuantity) {
                if ($maxQuantity > 0) {
                    $item->setQuantity($maxQuantity);
                    $issues[] = [
                        'type' => 'quantity_reduced',
                        'message' => "Quantité de '{$item->getDisplayName()}' réduite à {$maxQuantity} (stock limité)",
                        'item_id' => $item->getId(),
                        'new_quantity' => $maxQuantity
                    ];
                } else {
                    $this->removeItem($item);
                    $issues[] = [
                        'type' => 'item_removed',
                        'message' => "'{$item->getDisplayName()}' supprimé (rupture de stock)",
                        'item_id' => $item->getId()
                    ];
                }
            }
        }
        
        $this->em->flush();
        
        return $issues;
    }
    
    public function getCartSummary(Cart $cart, ?Customer $customer = null): array
    {
        $items = $cart->getItems();
        
        $summary = [
            'items' => [],
            'totals' => [
                'items_count' => 0,
                'subtotal' => 0,
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'discount_amount' => $cart->getDiscountAmount(),
                'total' => 0
            ],
            'currency' => $cart->getCurrencyCode()
        ];
        
        foreach ($items as $item) {
            $itemData = [
                'id' => $item->getId(),
                'product_name' => $item->getDisplayName(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'total_price' => $item->getTotalPrice(),
                'formatted_unit_price' => $this->formatPrice($item->getUnitPrice()),
                'formatted_total_price' => $this->formatPrice($item->getTotalPrice())
            ];
            
            $summary['items'][] = $itemData;
            $summary['totals']['items_count'] += $item->getQuantity();
            $summary['totals']['subtotal'] += $item->getTotalPrice();
        }
        
        // Calculer les taxes (exemple simple)
        $summary['totals']['tax_amount'] = $this->calculateTax($summary['totals']['subtotal'], $customer);
        
        // Calculer les frais de port (sera implémenté dans un autre service)
        $summary['totals']['shipping_amount'] = $this->calculateShipping($cart, $customer);
        
        // Total final
        $summary['totals']['total'] = 
            $summary['totals']['subtotal'] + 
            $summary['totals']['tax_amount'] + 
            $summary['totals']['shipping_amount'] - 
            $summary['totals']['discount_amount'];
        
        // Formatage
        foreach (['subtotal', 'tax_amount', 'shipping_amount', 'discount_amount', 'total'] as $field) {
            $summary['totals']['formatted_' . $field] = $this->formatPrice($summary['totals'][$field]);
        }
        
        return $summary;
    }
    
    public function cleanupExpiredCarts(): int
    {
        return $this->cartRepository->cleanupExpiredCarts();
    }
    
    private function createCart(string $sessionId, ?Customer $customer = null): Cart
    {
        $cart = new Cart();
        $cart->setSessionId($sessionId);
        
        if ($customer) {
            $cart->setCustomerId($customer->getId());
            $cart->setCustomer($customer);
        }
        
        $this->em->persist($cart);
        $this->em->flush();
        
        $this->logger->info('New cart created', [
            'cart_id' => $cart->getId(),
            'session_id' => $sessionId,
            'customer_id' => $customer?->getId()
        ]);
        
        return $cart;
    }
    
    private function validateStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (!$product->getTrackInventory()) {
            return; // Pas de gestion de stock
        }
        
        $availableStock = $variant ? 
            $variant->getAvailableStock() : 
            $product->getAvailableStock();
        
        if ($availableStock < $quantity) {
            if (!$product->getAllowBackorder()) {
                throw new \InvalidArgumentException(
                    "Quantité demandée ({$quantity}) supérieure au stock disponible ({$availableStock})"
                );
            }
        }
    }
    
    private function getMaxAvailableQuantity(Product $product, ?ProductVariant $variant): ?int
    {
        if (!$product->getTrackInventory()) {
            return null; // Stock illimité
        }
        
        $availableStock = $variant ? 
            $variant->getAvailableStock() : 
            $product->getAvailableStock();
        
        return max(0, $availableStock);
    }
    
    private function updateItemPrice(CartItem $cartItem, ?Customer $customer = null): void
    {
        $unitPrice = $this->pricingService->getPrice(
            $cartItem->getProduct(),
            $cartItem->getVariant(),
            $customer,
            $cartItem->getQuantity()
        );
        
        $cartItem->setUnitPrice($unitPrice);
        $cartItem->calculateTotalPrice();
    }
    
    private function formatPrice(int $priceInCents): string
    {
        return number_format($priceInCents / 100, 2, ',', ' ') . ' €';
    }
    
    private function calculateTax(int $subtotal, ?Customer $customer = null): int
    {
        // Implémentation simple - en production, utiliser un service dédié
        return (int) ($subtotal * 0.20); // TVA 20%
    }
    
    private function calculateShipping(Cart $cart, ?Customer $customer = null): int
    {
        // Implémentation simple - en production, utiliser un service dédié
        $subtotal = $cart->getSubtotal();
        
        if ($subtotal >= 5000) { // 50€
            return 0; // Livraison gratuite
        }
        
        return 590; // 5.90€
    }
}
```

## Gestion des sessions

### CartSessionHandler

```php
<?php

namespace App\Service\Cart;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartSessionHandler
{
    private SessionInterface $session;
    
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }
    
    public function getCartId(): ?int
    {
        return $this->session->get('cart_id');
    }
    
    public function setCartId(int $cartId): void
    {
        $this->session->set('cart_id', $cartId);
    }
    
    public function clearCartId(): void
    {
        $this->session->remove('cart_id');
    }
    
    public function getSessionId(): string
    {
        return $this->session->getId();
    }
    
    public function storeCartSnapshot(array $cartData): void
    {
        $this->session->set('cart_snapshot', [
            'data' => $cartData,
            'timestamp' => time()
        ]);
    }
    
    public function getCartSnapshot(): ?array
    {
        $snapshot = $this->session->get('cart_snapshot');
        
        if ($snapshot && (time() - $snapshot['timestamp']) < 3600) {
            return $snapshot['data'];
        }
        
        return null;
    }
    
    public function clearCartSnapshot(): void
    {
        $this->session->remove('cart_snapshot');
    }
}
```

## Système de coupons

### CouponService

```php
<?php

namespace App\Service\Cart;

use App\Entity\Cart\Cart;
use App\Entity\Promotion\Coupon;
use App\Repository\Promotion\CouponRepository;
use MulerTech\Database\ORM\EntityManager;

class CouponService
{
    private EntityManager $em;
    private CouponRepository $couponRepository;
    
    public function __construct(EntityManager $em, CouponRepository $couponRepository)
    {
        $this->em = $em;
        $this->couponRepository = $couponRepository;
    }
    
    public function applyCoupon(Cart $cart, string $couponCode): array
    {
        $coupon = $this->couponRepository->findValidCoupon($couponCode);
        
        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Code de réduction invalide ou expiré'
            ];
        }
        
        // Vérifications
        $validation = $this->validateCoupon($coupon, $cart);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        // Calculer la réduction
        $discountAmount = $this->calculateDiscount($coupon, $cart);
        
        // Appliquer le coupon
        $cart->setCouponCode($couponCode);
        $cart->setDiscountAmount($discountAmount);
        
        $this->em->flush();
        
        return [
            'success' => true,
            'message' => 'Code de réduction appliqué avec succès',
            'discount_amount' => $discountAmount,
            'formatted_discount' => number_format($discountAmount / 100, 2, ',', ' ') . ' €'
        ];
    }
    
    public function removeCoupon(Cart $cart): void
    {
        $cart->setCouponCode(null);
        $cart->setDiscountAmount(0);
        
        $this->em->flush();
    }
    
    private function validateCoupon(Coupon $coupon, Cart $cart): array
    {
        // Vérifier la date d'expiration
        if ($coupon->getExpiresAt() && $coupon->getExpiresAt() < new \DateTimeImmutable()) {
            return ['valid' => false, 'message' => 'Ce code de réduction a expiré'];
        }
        
        // Vérifier le montant minimum
        if ($coupon->getMinAmount() && $cart->getSubtotal() < $coupon->getMinAmount()) {
            $minAmount = number_format($coupon->getMinAmount() / 100, 2, ',', ' ');
            return ['valid' => false, 'message' => "Montant minimum de {$minAmount} € requis"];
        }
        
        // Vérifier la limite d'utilisation
        if ($coupon->getUsageLimit() && $coupon->getUsedCount() >= $coupon->getUsageLimit()) {
            return ['valid' => false, 'message' => 'Ce code de réduction a atteint sa limite d\'utilisation'];
        }
        
        return ['valid' => true];
    }
    
    private function calculateDiscount(Coupon $coupon, Cart $cart): int
    {
        $subtotal = $cart->getSubtotal();
        
        switch ($coupon->getType()) {
            case 'fixed':
                return min($coupon->getValue(), $subtotal);
                
            case 'percentage':
                $discount = ($subtotal * $coupon->getValue()) / 100;
                return (int) $discount;
                
            default:
                return 0;
        }
    }
}
```

## Cache et performance

### CartCacheService

```php
<?php

namespace App\Service\Cart;

use App\Entity\Cart\Cart;
use Psr\Cache\CacheItemPoolInterface;

class CartCacheService
{
    private CacheItemPoolInterface $cache;
    
    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function cacheCartSummary(Cart $cart, array $summary): void
    {
        $cacheKey = $this->getCartSummaryKey($cart);
        
        $item = $this->cache->getItem($cacheKey);
        $item->set($summary);
        $item->expiresAfter(300); // 5 minutes
        
        $this->cache->save($item);
    }
    
    public function getCachedCartSummary(Cart $cart): ?array
    {
        $cacheKey = $this->getCartSummaryKey($cart);
        
        $item = $this->cache->getItem($cacheKey);
        
        return $item->isHit() ? $item->get() : null;
    }
    
    public function invalidateCartCache(Cart $cart): void
    {
        $keys = [
            $this->getCartSummaryKey($cart),
            $this->getCartItemsKey($cart),
            $this->getCartTotalsKey($cart)
        ];
        
        $this->cache->deleteItems($keys);
    }
    
    public function warmupCartCache(Cart $cart, CartService $cartService): void
    {
        // Précalculer et mettre en cache le résumé du panier
        $summary = $cartService->getCartSummary($cart);
        $this->cacheCartSummary($cart, $summary);
    }
    
    private function getCartSummaryKey(Cart $cart): string
    {
        $hash = md5(serialize([
            $cart->getId(),
            $cart->getUpdatedAt()->getTimestamp(),
            count($cart->getItems())
        ]));
        
        return "cart_summary_{$cart->getId()}_{$hash}";
    }
    
    private function getCartItemsKey(Cart $cart): string
    {
        return "cart_items_{$cart->getId()}";
    }
    
    private function getCartTotalsKey(Cart $cart): string
    {
        return "cart_totals_{$cart->getId()}";
    }
}
```

## API du panier

### CartController

```php
<?php

namespace App\Controller\Api;

use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Service\Cart\CartService;
use App\Service\Cart\CouponService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/cart')]
class CartController extends AbstractApiController
{
    private CartService $cartService;
    private CouponService $couponService;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CartService $cartService,
        CouponService $couponService
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->cartService = $cartService;
        $this->couponService = $couponService;
    }
    
    #[Route('', methods: ['GET'])]
    public function getCart(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        $cart = $this->cartService->getCurrentCart($customer);
        
        // Valider et nettoyer le panier
        $issues = $this->cartService->validateCart($cart);
        
        $summary = $this->cartService->getCartSummary($cart, $customer);
        
        $response = [
            'cart' => $summary,
            'issues' => $issues
        ];
        
        return $this->jsonResponse($response);
    }
    
    #[Route('/items', methods: ['POST'])]
    public function addItem(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $productId = $data['product_id'] ?? null;
        $variantId = $data['variant_id'] ?? null;
        $quantity = max(1, $data['quantity'] ?? 1);
        $customAttributes = $data['custom_attributes'] ?? null;
        
        if (!$productId) {
            return $this->createErrorResponse('Product ID is required', 400);
        }
        
        $product = $this->em->getRepository(Product::class)->find($productId);
        if (!$product) {
            return $this->createErrorResponse('Product not found', 404);
        }
        
        $variant = null;
        if ($variantId) {
            $variant = $this->em->getRepository(ProductVariant::class)->find($variantId);
            if (!$variant || $variant->getProductId() !== $productId) {
                return $this->createErrorResponse('Invalid product variant', 400);
            }
        }
        
        try {
            $customer = $this->getCurrentCustomer($request);
            $cartItem = $this->cartService->addToCart(
                $product,
                $quantity,
                $variant,
                $customAttributes,
                $customer
            );
            
            $cart = $cartItem->getCart();
            $summary = $this->cartService->getCartSummary($cart, $customer);
            
            return $this->jsonResponse([
                'message' => 'Produit ajouté au panier',
                'cart_item' => [
                    'id' => $cartItem->getId(),
                    'product_name' => $cartItem->getDisplayName(),
                    'quantity' => $cartItem->getQuantity(),
                    'unit_price' => $cartItem->getUnitPrice(),
                    'total_price' => $cartItem->getTotalPrice()
                ],
                'cart' => $summary
            ], 201);
            
        } catch (\InvalidArgumentException $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
    
    #[Route('/items/{itemId}', methods: ['PUT'])]
    public function updateItem(int $itemId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newQuantity = max(0, $data['quantity'] ?? 1);
        
        $cartItem = $this->em->getRepository(CartItem::class)->find($itemId);
        if (!$cartItem) {
            return $this->createErrorResponse('Cart item not found', 404);
        }
        
        try {
            $customer = $this->getCurrentCustomer($request);
            $this->cartService->updateQuantity($cartItem, $newQuantity, $customer);
            
            if ($newQuantity === 0) {
                return $this->jsonResponse(['message' => 'Article supprimé du panier']);
            }
            
            $cart = $cartItem->getCart();
            $summary = $this->cartService->getCartSummary($cart, $customer);
            
            return $this->jsonResponse([
                'message' => 'Quantité mise à jour',
                'cart' => $summary
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
    
    #[Route('/items/{itemId}', methods: ['DELETE'])]
    public function removeItem(int $itemId, Request $request): JsonResponse
    {
        $cartItem = $this->em->getRepository(CartItem::class)->find($itemId);
        if (!$cartItem) {
            return $this->createErrorResponse('Cart item not found', 404);
        }
        
        $this->cartService->removeItem($cartItem);
        
        $customer = $this->getCurrentCustomer($request);
        $cart = $this->cartService->getCurrentCart($customer);
        $summary = $this->cartService->getCartSummary($cart, $customer);
        
        return $this->jsonResponse([
            'message' => 'Article supprimé du panier',
            'cart' => $summary
        ]);
    }
    
    #[Route('/clear', methods: ['POST'])]
    public function clearCart(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        $cart = $this->cartService->getCurrentCart($customer);
        
        $this->cartService->clearCart($cart);
        
        return $this->jsonResponse(['message' => 'Panier vidé']);
    }
    
    #[Route('/coupon', methods: ['POST'])]
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $couponCode = $data['code'] ?? '';
        
        if (empty($couponCode)) {
            return $this->createErrorResponse('Coupon code is required', 400);
        }
        
        $customer = $this->getCurrentCustomer($request);
        $cart = $this->cartService->getCurrentCart($customer);
        
        $result = $this->couponService->applyCoupon($cart, $couponCode);
        
        if (!$result['success']) {
            return $this->createErrorResponse($result['message'], 400);
        }
        
        $summary = $this->cartService->getCartSummary($cart, $customer);
        
        return $this->jsonResponse([
            'message' => $result['message'],
            'discount' => [
                'amount' => $result['discount_amount'],
                'formatted' => $result['formatted_discount']
            ],
            'cart' => $summary
        ]);
    }
    
    #[Route('/coupon', methods: ['DELETE'])]
    public function removeCoupon(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        $cart = $this->cartService->getCurrentCart($customer);
        
        $this->couponService->removeCoupon($cart);
        
        $summary = $this->cartService->getCartSummary($cart, $customer);
        
        return $this->jsonResponse([
            'message' => 'Code de réduction retiré',
            'cart' => $summary
        ]);
    }
    
    private function getCurrentCustomer(Request $request): ?Customer
    {
        // Implémentation de récupération du client authentifié
        // Dépend du système d'authentification utilisé
        return null;
    }
}
```

---

Ce système de panier complet démontre une architecture robuste avec gestion des sessions, persistance cross-device, validation temps réel, cache pour la performance et API REST complète pour les applications front-end modernes.