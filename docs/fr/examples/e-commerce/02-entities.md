# Entités E-commerce - Définition des Modèles

Cette section présente l'ensemble des entités nécessaires pour une application e-commerce complète, démontrant les bonnes pratiques de modélisation avec MulerTech Database ORM.

## Table des matières

- [Entités principales](#entités-principales)
- [Entités de catalogue](#entités-de-catalogue)
- [Entités de commande](#entités-de-commande)
- [Entités de client](#entités-de-client)
- [Entités de paiement](#entités-de-paiement)
- [Entités de logistique](#entités-de-logistique)
- [Types personnalisés](#types-personnalisés)
- [Traits réutilisables](#traits-réutilisables)

## Entités principales

### Entity de base

```php
<?php

namespace App\Entity;

use MulerTech\Database\ORM\Attribute\MtColumn;

abstract class BaseEntity
{
    #[MtColumn(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    protected \DateTimeInterface $createdAt;
    
    #[MtColumn(name: 'updated_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    protected \DateTimeInterface $updatedAt;
    
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
    
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    
    public function updateTimestamp(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }
}
```

## Entités de catalogue

### Product - Entité principale des produits

```php
<?php

namespace App\Entity\Catalog;

use App\Entity\BaseEntity;
use App\Type\MoneyType;
use App\Type\SlugType;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'products')]
class Product extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(type: 'varchar', length: 255)]
    private string $name;
    
    #[MtColumn(type: SlugType::class, unique: true)]
    private string $slug;
    
    #[MtColumn(type: 'text', nullable: true)]
    private ?string $description = null;
    
    #[MtColumn(name: 'short_description', type: 'varchar', length: 500, nullable: true)]
    private ?string $shortDescription = null;
    
    #[MtColumn(type: 'varchar', length: 100, unique: true)]
    private string $sku;
    
    #[MtColumn(type: MoneyType::class)]
    private int $price; // Prix en centimes
    
    #[MtColumn(name: 'compare_price', type: MoneyType::class, nullable: true)]
    private ?int $comparePrice = null; // Prix barré
    
    #[MtColumn(name: 'cost_price', type: MoneyType::class, nullable: true)]
    private ?int $costPrice = null; // Prix d'achat
    
    #[MtColumn(name: 'category_id', type: 'int')]
    private int $categoryId;
    
    #[MtColumn(name: 'brand_id', type: 'int', nullable: true)]
    private ?int $brandId = null;
    
    #[MtColumn(name: 'stock_quantity', type: 'int', default: 0)]
    private int $stockQuantity = 0;
    
    #[MtColumn(name: 'reserved_quantity', type: 'int', default: 0)]
    private int $reservedQuantity = 0;
    
    #[MtColumn(name: 'min_stock_level', type: 'int', default: 5)]
    private int $minStockLevel = 5;
    
    #[MtColumn(name: 'track_inventory', type: 'boolean', default: true)]
    private bool $trackInventory = true;
    
    #[MtColumn(name: 'allow_backorder', type: 'boolean', default: false)]
    private bool $allowBackorder = false;
    
    #[MtColumn(type: 'decimal', precision: 8, scale: 3, nullable: true)]
    private ?float $weight = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $dimensions = null; // {width, height, depth}
    
    #[MtColumn(name: 'tax_class_id', type: 'int', nullable: true)]
    private ?int $taxClassId = null;
    
    #[MtColumn(name: 'is_active', type: 'boolean', default: true)]
    private bool $isActive = true;
    
    #[MtColumn(name: 'is_featured', type: 'boolean', default: false)]
    private bool $isFeatured = false;
    
    #[MtColumn(name: 'is_digital', type: 'boolean', default: false)]
    private bool $isDigital = false;
    
    #[MtColumn(name: 'requires_shipping', type: 'boolean', default: true)]
    private bool $requiresShipping = true;
    
    #[MtColumn(name: 'meta_title', type: 'varchar', length: 255, nullable: true)]
    private ?string $metaTitle = null;
    
    #[MtColumn(name: 'meta_description', type: 'varchar', length: 500, nullable: true)]
    private ?string $metaDescription = null;
    
    #[MtColumn(name: 'sort_order', type: 'int', default: 0)]
    private int $sortOrder = 0;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Category::class)]
    private ?Category $category = null;
    
    #[MtRelation('ManyToOne', targetEntity: Brand::class)]
    private ?Brand $brand = null;
    
    #[MtRelation('OneToMany', targetEntity: ProductVariant::class, mappedBy: 'product')]
    private array $variants = [];
    
    #[MtRelation('OneToMany', targetEntity: ProductImage::class, mappedBy: 'product')]
    private array $images = [];
    
    #[MtRelation('ManyToMany', targetEntity: Tag::class)]
    private array $tags = [];
    
    #[MtRelation('OneToMany', targetEntity: ProductAttribute::class, mappedBy: 'product')]
    private array $attributes = [];
    
    #[MtRelation('OneToMany', targetEntity: ProductReview::class, mappedBy: 'product')]
    private array $reviews = [];
    
    // Getters et setters
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function getSlug(): string
    {
        return $this->slug;
    }
    
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    
    public function getSku(): string
    {
        return $this->sku;
    }
    
    public function setSku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }
    
    public function getPrice(): int
    {
        return $this->price;
    }
    
    public function setPrice(int $price): self
    {
        $this->price = $price;
        return $this;
    }
    
    public function getPriceFormatted(): string
    {
        return number_format($this->price / 100, 2);
    }
    
    public function getAvailableStock(): int
    {
        return $this->stockQuantity - $this->reservedQuantity;
    }
    
    public function isInStock(): bool
    {
        return $this->trackInventory ? $this->getAvailableStock() > 0 : true;
    }
    
    public function needsRestock(): bool
    {
        return $this->stockQuantity <= $this->minStockLevel;
    }
    
    // ... autres getters et setters
}
```

### Category - Catégories de produits

```php
<?php

namespace App\Entity\Catalog;

use App\Entity\BaseEntity;
use App\Type\SlugType;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'categories')]
class Category extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(type: 'varchar', length: 255)]
    private string $name;
    
    #[MtColumn(type: SlugType::class, unique: true)]
    private string $slug;
    
    #[MtColumn(type: 'text', nullable: true)]
    private ?string $description = null;
    
    #[MtColumn(name: 'parent_id', type: 'int', nullable: true)]
    private ?int $parentId = null;
    
    #[MtColumn(name: 'image_url', type: 'varchar', length: 500, nullable: true)]
    private ?string $imageUrl = null;
    
    #[MtColumn(name: 'icon_class', type: 'varchar', length: 100, nullable: true)]
    private ?string $iconClass = null;
    
    #[MtColumn(name: 'is_active', type: 'boolean', default: true)]
    private bool $isActive = true;
    
    #[MtColumn(name: 'sort_order', type: 'int', default: 0)]
    private int $sortOrder = 0;
    
    #[MtColumn(name: 'meta_title', type: 'varchar', length: 255, nullable: true)]
    private ?string $metaTitle = null;
    
    #[MtColumn(name: 'meta_description', type: 'varchar', length: 500, nullable: true)]
    private ?string $metaDescription = null;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: self::class, inversedBy: 'children')]
    private ?Category $parent = null;
    
    #[MtRelation('OneToMany', targetEntity: self::class, mappedBy: 'parent')]
    private array $children = [];
    
    #[MtRelation('OneToMany', targetEntity: Product::class, mappedBy: 'category')]
    private array $products = [];
    
    // Méthodes utilitaires
    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->parent;
        
        while ($parent !== null) {
            $level++;
            $parent = $parent->getParent();
        }
        
        return $level;
    }
    
    public function getPath(): array
    {
        $path = [];
        $current = $this;
        
        while ($current !== null) {
            array_unshift($path, $current);
            $current = $current->getParent();
        }
        
        return $path;
    }
    
    public function getBreadcrumb(): string
    {
        return implode(' > ', array_map(fn($cat) => $cat->getName(), $this->getPath()));
    }
    
    // Getters et setters...
}
```

### ProductVariant - Variants de produits

```php
<?php

namespace App\Entity\Catalog;

use App\Entity\BaseEntity;
use App\Type\MoneyType;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'product_variants')]
class ProductVariant extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'product_id', type: 'int')]
    private int $productId;
    
    #[MtColumn(type: 'varchar', length: 255)]
    private string $name;
    
    #[MtColumn(type: 'varchar', length: 100, unique: true)]
    private string $sku;
    
    #[MtColumn(name: 'price_adjustment', type: MoneyType::class, default: 0)]
    private int $priceAdjustment = 0; // Ajustement par rapport au prix du produit
    
    #[MtColumn(name: 'cost_adjustment', type: MoneyType::class, default: 0)]
    private int $costAdjustment = 0;
    
    #[MtColumn(name: 'stock_quantity', type: 'int', default: 0)]
    private int $stockQuantity = 0;
    
    #[MtColumn(name: 'reserved_quantity', type: 'int', default: 0)]
    private int $reservedQuantity = 0;
    
    #[MtColumn(type: 'decimal', precision: 8, scale: 3, nullable: true)]
    private ?float $weight = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $dimensions = null;
    
    #[MtColumn(type: 'json')]
    private array $attributes = []; // {color: 'red', size: 'L', material: 'cotton'}
    
    #[MtColumn(name: 'is_active', type: 'boolean', default: true)]
    private bool $isActive = true;
    
    #[MtColumn(name: 'sort_order', type: 'int', default: 0)]
    private int $sortOrder = 0;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Product::class, inversedBy: 'variants')]
    private ?Product $product = null;
    
    #[MtRelation('OneToMany', targetEntity: VariantImage::class, mappedBy: 'variant')]
    private array $images = [];
    
    public function getActualPrice(): int
    {
        return $this->product->getPrice() + $this->priceAdjustment;
    }
    
    public function getAvailableStock(): int
    {
        return $this->stockQuantity - $this->reservedQuantity;
    }
    
    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
    
    public function setAttribute(string $name, mixed $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }
    
    public function getDisplayName(): string
    {
        $attributeStrings = [];
        foreach ($this->attributes as $key => $value) {
            $attributeStrings[] = ucfirst($key) . ': ' . $value;
        }
        
        return $this->product->getName() . ' (' . implode(', ', $attributeStrings) . ')';
    }
    
    // Getters et setters...
}
```

## Entités de commande

### Order - Commandes

```php
<?php

namespace App\Entity\Order;

use App\Entity\BaseEntity;
use App\Type\MoneyType;
use App\Type\OrderNumberType;
use App\Enum\OrderStatus;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'orders')]
class Order extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(type: OrderNumberType::class, unique: true)]
    private string $number;
    
    #[MtColumn(name: 'customer_id', type: 'int', nullable: true)]
    private ?int $customerId = null;
    
    #[MtColumn(name: 'guest_email', type: 'varchar', length: 255, nullable: true)]
    private ?string $guestEmail = null;
    
    #[MtColumn(type: 'enum', values: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING;
    
    #[MtColumn(name: 'currency_code', type: 'varchar', length: 3, default: 'EUR')]
    private string $currencyCode = 'EUR';
    
    #[MtColumn(name: 'subtotal', type: MoneyType::class)]
    private int $subtotal = 0;
    
    #[MtColumn(name: 'tax_amount', type: MoneyType::class)]
    private int $taxAmount = 0;
    
    #[MtColumn(name: 'shipping_amount', type: MoneyType::class)]
    private int $shippingAmount = 0;
    
    #[MtColumn(name: 'discount_amount', type: MoneyType::class)]
    private int $discountAmount = 0;
    
    #[MtColumn(name: 'total', type: MoneyType::class)]
    private int $total = 0;
    
    #[MtColumn(name: 'coupon_code', type: 'varchar', length: 50, nullable: true)]
    private ?string $couponCode = null;
    
    #[MtColumn(name: 'notes', type: 'text', nullable: true)]
    private ?string $notes = null;
    
    #[MtColumn(name: 'internal_notes', type: 'text', nullable: true)]
    private ?string $internalNotes = null;
    
    #[MtColumn(name: 'shipped_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $shippedAt = null;
    
    #[MtColumn(name: 'completed_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;
    
    #[MtColumn(name: 'cancelled_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $cancelledAt = null;
    
    // Adresses (dénormalisées pour historique)
    #[MtColumn(name: 'billing_address', type: 'json')]
    private array $billingAddress = [];
    
    #[MtColumn(name: 'shipping_address', type: 'json')]
    private array $shippingAddress = [];
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Customer::class)]
    private ?Customer $customer = null;
    
    #[MtRelation('OneToMany', targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'])]
    private array $items = [];
    
    #[MtRelation('OneToOne', targetEntity: Payment::class, mappedBy: 'order')]
    private ?Payment $payment = null;
    
    #[MtRelation('OneToMany', targetEntity: Shipment::class, mappedBy: 'order')]
    private array $shipments = [];
    
    #[MtRelation('OneToMany', targetEntity: OrderStatusHistory::class, mappedBy: 'order')]
    private array $statusHistory = [];
    
    // Méthodes métier
    public function getTotalItemsCount(): int
    {
        return array_sum(array_map(fn($item) => $item->getQuantity(), $this->items));
    }
    
    public function addItem(OrderItem $item): self
    {
        $item->setOrder($this);
        $this->items[] = $item;
        $this->recalculateTotal();
        return $this;
    }
    
    public function removeItem(OrderItem $item): self
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
            $this->recalculateTotal();
        }
        return $this;
    }
    
    public function recalculateTotal(): self
    {
        $this->subtotal = array_sum(array_map(fn($item) => $item->getTotalPrice(), $this->items));
        $this->total = $this->subtotal + $this->taxAmount + $this->shippingAmount - $this->discountAmount;
        return $this;
    }
    
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [OrderStatus::PENDING, OrderStatus::CONFIRMED]);
    }
    
    public function canBeShipped(): bool
    {
        return $this->status === OrderStatus::CONFIRMED && $this->payment?->isCompleted();
    }
    
    public function isGuest(): bool
    {
        return $this->customerId === null;
    }
    
    public function getCustomerEmail(): string
    {
        return $this->customer?->getEmail() ?? $this->guestEmail ?? '';
    }
    
    // Getters et setters...
}
```

### OrderItem - Articles de commande

```php
<?php

namespace App\Entity\Order;

use App\Entity\BaseEntity;
use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Type\MoneyType;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'order_items')]
class OrderItem extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'order_id', type: 'int')]
    private int $orderId;
    
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
    
    // Données dénormalisées pour historique
    #[MtColumn(name: 'product_name', type: 'varchar', length: 255)]
    private string $productName;
    
    #[MtColumn(name: 'product_sku', type: 'varchar', length: 100)]
    private string $productSku;
    
    #[MtColumn(name: 'variant_name', type: 'varchar', length: 255, nullable: true)]
    private ?string $variantName = null;
    
    #[MtColumn(name: 'variant_attributes', type: 'json', nullable: true)]
    private ?array $variantAttributes = null;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Order::class, inversedBy: 'items')]
    private ?Order $order = null;
    
    #[MtRelation('ManyToOne', targetEntity: Product::class)]
    private ?Product $product = null;
    
    #[MtRelation('ManyToOne', targetEntity: ProductVariant::class)]
    private ?ProductVariant $variant = null;
    
    public function getDisplayName(): string
    {
        return $this->variantName ? 
            $this->productName . ' - ' . $this->variantName : 
            $this->productName;
    }
    
    public function updateFromProduct(Product $product, ?ProductVariant $variant = null): self
    {
        $this->productName = $product->getName();
        $this->productSku = $variant?->getSku() ?? $product->getSku();
        
        if ($variant) {
            $this->variantName = $variant->getName();
            $this->variantAttributes = $variant->getAttributes();
            $this->unitPrice = $variant->getActualPrice();
        } else {
            $this->unitPrice = $product->getPrice();
        }
        
        $this->calculateTotal();
        return $this;
    }
    
    public function calculateTotal(): self
    {
        $this->totalPrice = $this->unitPrice * $this->quantity;
        return $this;
    }
    
    // Getters et setters...
}
```

## Entités de client

### Customer - Clients

```php
<?php

namespace App\Entity\Customer;

use App\Entity\BaseEntity;
use App\Trait\SoftDeletableTrait;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'customers')]
class Customer extends BaseEntity
{
    use SoftDeletableTrait;
    
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(type: 'varchar', length: 255, unique: true)]
    private string $email;
    
    #[MtColumn(name: 'first_name', type: 'varchar', length: 100)]
    private string $firstName;
    
    #[MtColumn(name: 'last_name', type: 'varchar', length: 100)]
    private string $lastName;
    
    #[MtColumn(type: 'varchar', length: 20, nullable: true)]
    private ?string $phone = null;
    
    #[MtColumn(name: 'date_of_birth', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;
    
    #[MtColumn(type: 'enum', values: ['male', 'female', 'other'], nullable: true)]
    private ?string $gender = null;
    
    #[MtColumn(name: 'password_hash', type: 'varchar', length: 255)]
    private string $passwordHash;
    
    #[MtColumn(name: 'email_verified_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $emailVerifiedAt = null;
    
    #[MtColumn(name: 'last_login_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;
    
    #[MtColumn(name: 'is_active', type: 'boolean', default: true)]
    private bool $isActive = true;
    
    #[MtColumn(name: 'accepts_marketing', type: 'boolean', default: false)]
    private bool $acceptsMarketing = false;
    
    #[MtColumn(type: 'varchar', length: 10, default: 'fr')]
    private string $locale = 'fr';
    
    #[MtColumn(name: 'customer_group_id', type: 'int', nullable: true)]
    private ?int $customerGroupId = null;
    
    // Relations
    #[MtRelation('OneToMany', targetEntity: Order::class, mappedBy: 'customer')]
    private array $orders = [];
    
    #[MtRelation('OneToMany', targetEntity: Address::class, mappedBy: 'customer')]
    private array $addresses = [];
    
    #[MtRelation('OneToOne', targetEntity: Cart::class, mappedBy: 'customer')]
    private ?Cart $cart = null;
    
    #[MtRelation('OneToMany', targetEntity: CustomerWishlist::class, mappedBy: 'customer')]
    private array $wishlistItems = [];
    
    #[MtRelation('ManyToOne', targetEntity: CustomerGroup::class)]
    private ?CustomerGroup $customerGroup = null;
    
    // Méthodes utilitaires
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function getDisplayName(): string
    {
        return trim($this->firstName . ' ' . substr($this->lastName, 0, 1) . '.');
    }
    
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
    
    public function getTotalSpent(): int
    {
        return array_sum(array_map(
            fn($order) => $order->getTotal(),
            array_filter($this->orders, fn($order) => $order->getStatus()->isCompleted())
        ));
    }
    
    public function getOrdersCount(): int
    {
        return count(array_filter(
            $this->orders, 
            fn($order) => $order->getStatus()->isCompleted()
        ));
    }
    
    public function getDefaultBillingAddress(): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->getType() === 'billing' && $address->isDefault()) {
                return $address;
            }
        }
        return null;
    }
    
    public function getDefaultShippingAddress(): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->getType() === 'shipping' && $address->isDefault()) {
                return $address;
            }
        }
        return null;
    }
    
    // Getters et setters...
}
```

### Address - Adresses

```php
<?php

namespace App\Entity\Customer;

use App\Entity\BaseEntity;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'addresses')]
class Address extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'customer_id', type: 'int')]
    private int $customerId;
    
    #[MtColumn(type: 'enum', values: ['billing', 'shipping', 'both'])]
    private string $type;
    
    #[MtColumn(name: 'first_name', type: 'varchar', length: 100)]
    private string $firstName;
    
    #[MtColumn(name: 'last_name', type: 'varchar', length: 100)]
    private string $lastName;
    
    #[MtColumn(type: 'varchar', length: 100, nullable: true)]
    private ?string $company = null;
    
    #[MtColumn(name: 'address_line_1', type: 'varchar', length: 255)]
    private string $addressLine1;
    
    #[MtColumn(name: 'address_line_2', type: 'varchar', length: 255, nullable: true)]
    private ?string $addressLine2 = null;
    
    #[MtColumn(type: 'varchar', length: 100)]
    private string $city;
    
    #[MtColumn(type: 'varchar', length: 100, nullable: true)]
    private ?string $state = null;
    
    #[MtColumn(name: 'postal_code', type: 'varchar', length: 20)]
    private string $postalCode;
    
    #[MtColumn(name: 'country_code', type: 'varchar', length: 2)]
    private string $countryCode;
    
    #[MtColumn(type: 'varchar', length: 20, nullable: true)]
    private ?string $phone = null;
    
    #[MtColumn(name: 'is_default', type: 'boolean', default: false)]
    private bool $isDefault = false;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Customer::class, inversedBy: 'addresses')]
    private ?Customer $customer = null;
    
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function getFormattedAddress(): string
    {
        $parts = [
            $this->company,
            $this->firstName . ' ' . $this->lastName,
            $this->addressLine1,
            $this->addressLine2,
            $this->postalCode . ' ' . $this->city,
            $this->state,
            $this->getCountryName()
        ];
        
        return implode("\n", array_filter($parts));
    }
    
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company' => $this->company,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'phone' => $this->phone
        ];
    }
    
    private function getCountryName(): string
    {
        // Implémentation simple - en production, utiliser une bibliothèque de pays
        $countries = [
            'FR' => 'France',
            'BE' => 'Belgique',
            'CH' => 'Suisse',
            'CA' => 'Canada',
            'US' => 'États-Unis'
        ];
        
        return $countries[$this->countryCode] ?? $this->countryCode;
    }
    
    // Getters et setters...
}
```

## Entités de panier

### Cart - Panier

```php
<?php

namespace App\Entity\Cart;

use App\Entity\BaseEntity;
use App\Entity\Customer\Customer;
use App\Type\MoneyType;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'carts')]
class Cart extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'session_id', type: 'varchar', length: 255, nullable: true)]
    private ?string $sessionId = null;
    
    #[MtColumn(name: 'customer_id', type: 'int', nullable: true)]
    private ?int $customerId = null;
    
    #[MtColumn(name: 'currency_code', type: 'varchar', length: 3, default: 'EUR')]
    private string $currencyCode = 'EUR';
    
    #[MtColumn(name: 'coupon_code', type: 'varchar', length: 50, nullable: true)]
    private ?string $couponCode = null;
    
    #[MtColumn(name: 'discount_amount', type: MoneyType::class, default: 0)]
    private int $discountAmount = 0;
    
    #[MtColumn(name: 'expires_at', type: 'timestamp')]
    private \DateTimeInterface $expiresAt;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Customer::class, inversedBy: 'cart')]
    private ?Customer $customer = null;
    
    #[MtRelation('OneToMany', targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist', 'remove'])]
    private array $items = [];
    
    public function __construct()
    {
        $this->expiresAt = new \DateTimeImmutable('+30 days');
    }
    
    public function getTotalItems(): int
    {
        return array_sum(array_map(fn($item) => $item->getQuantity(), $this->items));
    }
    
    public function getSubtotal(): int
    {
        return array_sum(array_map(fn($item) => $item->getTotalPrice(), $this->items));
    }
    
    public function getTotal(): int
    {
        return $this->getSubtotal() - $this->discountAmount;
    }
    
    public function addItem(CartItem $item): self
    {
        // Vérifier si le produit/variant existe déjà
        foreach ($this->items as $existingItem) {
            if ($existingItem->getProductId() === $item->getProductId() 
                && $existingItem->getVariantId() === $item->getVariantId()) {
                $existingItem->setQuantity($existingItem->getQuantity() + $item->getQuantity());
                return $this;
            }
        }
        
        $item->setCart($this);
        $this->items[] = $item;
        return $this;
    }
    
    public function removeItem(CartItem $item): self
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
        }
        return $this;
    }
    
    public function clear(): self
    {
        $this->items = [];
        $this->couponCode = null;
        $this->discountAmount = 0;
        return $this;
    }
    
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
    
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
    
    public function extendExpiry(int $days = 30): self
    {
        $this->expiresAt = new \DateTimeImmutable("+{$days} days");
        return $this;
    }
    
    // Getters et setters...
}
```

## Types personnalisés

### MoneyType - Type monétaire

```php
<?php

namespace App\Type;

use MulerTech\Database\ORM\Type\AbstractType;

class MoneyType extends AbstractType
{
    public function convertToDatabaseValue($value): ?int
    {
        if ($value === null) {
            return null;
        }
        
        if (is_float($value)) {
            return (int) round($value * 100);
        }
        
        if (is_string($value)) {
            return (int) round((float) $value * 100);
        }
        
        return (int) $value;
    }
    
    public function convertToPHPValue($value): ?int
    {
        return $value === null ? null : (int) $value;
    }
    
    public function getSQLDeclaration(): string
    {
        return 'INT';
    }
    
    public function getName(): string
    {
        return 'money';
    }
}
```

### SlugType - Type pour les slugs

```php
<?php

namespace App\Type;

use MulerTech\Database\ORM\Type\AbstractType;

class SlugType extends AbstractType
{
    public function convertToDatabaseValue($value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return $this->generateSlug($value);
    }
    
    public function convertToPHPValue($value): ?string
    {
        return $value;
    }
    
    public function getSQLDeclaration(): string
    {
        return 'VARCHAR(255)';
    }
    
    public function getName(): string
    {
        return 'slug';
    }
    
    private function generateSlug(string $text): string
    {
        // Convertir en minuscules
        $slug = strtolower($text);
        
        // Remplacer les caractères accentués
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        
        // Remplacer les caractères non alphanumériques par des tirets
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Supprimer les tirets en début et fin
        $slug = trim($slug, '-');
        
        return $slug;
    }
}
```

## Énumérations

### OrderStatus - Statuts de commande

```php
<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    
    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::PROCESSING => 'En cours de traitement',
            self::SHIPPED => 'Expédiée',
            self::DELIVERED => 'Livrée',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::REFUNDED => 'Remboursée',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::PROCESSING => 'purple',
            self::SHIPPED => 'indigo',
            self::DELIVERED => 'green',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::REFUNDED => 'gray',
        };
    }
    
    public function isCompleted(): bool
    {
        return in_array($this, [self::DELIVERED, self::COMPLETED]);
    }
    
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED]);
    }
    
    public function allowsShipping(): bool
    {
        return $this === self::CONFIRMED;
    }
}
```

---

Cette architecture d'entités e-commerce complète démontre l'utilisation avancée de MulerTech Database ORM avec des relations complexes, des types personnalisés, et des patterns métier éprouvés pour une application de vente en ligne.