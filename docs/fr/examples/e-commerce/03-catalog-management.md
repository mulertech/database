# Gestion du Catalogue - E-commerce

Cette section détaille l'implémentation complète du système de gestion de catalogue produits, démontrant les fonctionnalités avancées de MulerTech Database ORM pour l'e-commerce.

## Table des matières

- [Architecture du catalogue](#architecture-du-catalogue)
- [Repository des produits](#repository-des-produits)
- [Service de gestion des produits](#service-de-gestion-des-produits)
- [Gestion des variants](#gestion-des-variants)
- [Système de catégories](#système-de-catégories)
- [Gestion des prix](#gestion-des-prix)
- [Inventaire et stock](#inventaire-et-stock)
- [Images et médias](#images-et-médias)
- [Recherche et filtrage](#recherche-et-filtrage)
- [Import/Export](#importexport)

## Architecture du catalogue

### Repository ProductRepository

```php
<?php

namespace App\Repository\Catalog;

use App\Entity\Catalog\Product;
use MulerTech\Database\ORM\Repository\EntityRepository;
use MulerTech\Database\Query\QueryBuilder;

class ProductRepository extends EntityRepository
{
    protected function getEntityClass(): string
    {
        return Product::class;
    }
    
    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('is_active = ?')
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('created_at', 'DESC')
            ->setParameter(0, true)
            ->getQuery()
            ->getResult();
    }
    
    public function findByCategory(int $categoryId, bool $includeChildren = true): array
    {
        $qb = $this->createQueryBuilder()
            ->select('p.*')
            ->from('products', 'p')
            ->where('p.is_active = ?')
            ->setParameter(0, true);
        
        if ($includeChildren) {
            // Inclure les produits des sous-catégories
            $qb->innerJoin('categories', 'c', 'c.id = p.category_id')
               ->leftJoin('categories', 'parent', 'parent.id = c.parent_id')
               ->andWhere('(p.category_id = ? OR parent.id = ?)')
               ->setParameter(1, $categoryId)
               ->setParameter(2, $categoryId);
        } else {
            $qb->andWhere('p.category_id = ?')
               ->setParameter(1, $categoryId);
        }
        
        return $qb->orderBy('p.sort_order', 'ASC')
                 ->getQuery()
                 ->getResult();
    }
    
    public function findFeaturedProducts(int $limit = 12): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('is_active = ? AND is_featured = ?')
            ->orderBy('sort_order', 'ASC')
            ->limit($limit)
            ->setParameter(0, true)
            ->setParameter(1, true)
            ->getQuery()
            ->getResult();
    }
    
    public function findNewProducts(int $days = 30, int $limit = 12): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('is_active = ? AND created_at >= ?')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->setParameter(0, true)
            ->setParameter(1, new \DateTimeImmutable("-{$days} days"))
            ->getQuery()
            ->getResult();
    }
    
    public function findLowStockProducts(int $threshold = null): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('is_active = ? AND track_inventory = ? AND stock_quantity <= COALESCE(?, min_stock_level)')
            ->orderBy('stock_quantity', 'ASC')
            ->setParameter(0, true)
            ->setParameter(1, true)
            ->setParameter(2, $threshold)
            ->getQuery()
            ->getResult();
    }
    
    public function searchProducts(string $query, array $filters = []): array
    {
        $qb = $this->createQueryBuilder()
            ->select('
                p.*,
                MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
            ')
            ->from('products', 'p')
            ->where('p.is_active = ?')
            ->andWhere('MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)')
            ->orderBy('relevance', 'DESC')
            ->addOrderBy('p.sort_order', 'ASC')
            ->setParameter(0, $query)
            ->setParameter(1, true)
            ->setParameter(2, $query);
        
        // Filtres par catégorie
        if (isset($filters['category_id'])) {
            $qb->andWhere('p.category_id = ?')
               ->setParameter(3, $filters['category_id']);
        }
        
        // Filtres par marque
        if (isset($filters['brand_id'])) {
            $qb->andWhere('p.brand_id = ?')
               ->setParameter(4, $filters['brand_id']);
        }
        
        // Filtres par prix
        if (isset($filters['min_price'])) {
            $qb->andWhere('p.price >= ?')
               ->setParameter(5, $filters['min_price'] * 100);
        }
        
        if (isset($filters['max_price'])) {
            $qb->andWhere('p.price <= ?')
               ->setParameter(6, $filters['max_price'] * 100);
        }
        
        // Filtre en stock seulement
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $qb->andWhere('(p.track_inventory = 0 OR p.stock_quantity > p.reserved_quantity)');
        }
        
        return $qb->getQuery()->getResult();
    }
    
    public function findBestSellers(int $days = 90, int $limit = 12): array
    {
        return $this->em->createQueryBuilder()
            ->select('
                p.*,
                SUM(oi.quantity) as total_sold
            ')
            ->from('products', 'p')
            ->innerJoin('order_items', 'oi', 'oi.product_id = p.id')
            ->innerJoin('orders', 'o', 'o.id = oi.order_id')
            ->where('p.is_active = ?')
            ->andWhere('o.status IN (?, ?)')
            ->andWhere('o.created_at >= ?')
            ->groupBy('p.id')
            ->orderBy('total_sold', 'DESC')
            ->limit($limit)
            ->setParameter(0, true)
            ->setParameter(1, 'completed')
            ->setParameter(2, 'delivered')
            ->setParameter(3, new \DateTimeImmutable("-{$days} days"))
            ->getQuery()
            ->getResult();
    }
    
    public function findRelatedProducts(Product $product, int $limit = 8): array
    {
        // Produits de la même catégorie, excluant le produit actuel
        return $this->createQueryBuilder()
            ->select('*')
            ->where('category_id = ? AND id != ? AND is_active = ?')
            ->orderBy('RAND()')
            ->limit($limit)
            ->setParameter(0, $product->getCategoryId())
            ->setParameter(1, $product->getId())
            ->setParameter(2, true)
            ->getQuery()
            ->getResult();
    }
    
    public function findBySlug(string $slug): ?Product
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('slug = ? AND is_active = ?')
            ->setParameter(0, $slug)
            ->setParameter(1, true)
            ->getQuery()
            ->getSingleResult();
    }
    
    public function getStockStatistics(): array
    {
        return $this->em->createQueryBuilder()
            ->select('
                COUNT(*) as total_products,
                SUM(stock_quantity) as total_stock,
                SUM(reserved_quantity) as total_reserved,
                COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count
            ')
            ->from('products')
            ->where('is_active = ? AND track_inventory = ?')
            ->setParameter(0, true)
            ->setParameter(1, true)
            ->getQuery()
            ->getSingleResult();
    }
}
```

## Service de gestion des produits

### ProductService

```php
<?php

namespace App\Service\Catalog;

use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Repository\Catalog\ProductRepository;
use App\Service\ImageService;
use App\Service\SlugService;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class ProductService
{
    private EntityManager $em;
    private ProductRepository $productRepository;
    private ImageService $imageService;
    private SlugService $slugService;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManager $em,
        ProductRepository $productRepository,
        ImageService $imageService,
        SlugService $slugService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->productRepository = $productRepository;
        $this->imageService = $imageService;
        $this->slugService = $slugService;
        $this->logger = $logger;
    }
    
    public function createProduct(array $data): Product
    {
        $product = new Product();
        
        $product->setName($data['name'])
                ->setDescription($data['description'] ?? null)
                ->setShortDescription($data['short_description'] ?? null)
                ->setSku($data['sku'])
                ->setPrice($data['price'] * 100) // Convertir en centimes
                ->setCategoryId($data['category_id'])
                ->setBrandId($data['brand_id'] ?? null)
                ->setStockQuantity($data['stock_quantity'] ?? 0)
                ->setWeight($data['weight'] ?? null)
                ->setIsActive($data['is_active'] ?? true)
                ->setIsFeatured($data['is_featured'] ?? false)
                ->setIsDigital($data['is_digital'] ?? false)
                ->setRequiresShipping(!($data['is_digital'] ?? false));
        
        // Générer le slug automatiquement
        $slug = $this->slugService->generate($data['name'], Product::class);
        $product->setSlug($slug);
        
        // Données SEO
        if (isset($data['meta_title'])) {
            $product->setMetaTitle($data['meta_title']);
        }
        if (isset($data['meta_description'])) {
            $product->setMetaDescription($data['meta_description']);
        }
        
        // Dimensions si spécifiées
        if (isset($data['dimensions'])) {
            $product->setDimensions($data['dimensions']);
        }
        
        $this->em->persist($product);
        $this->em->flush();
        
        // Traitement des images
        if (isset($data['images']) && !empty($data['images'])) {
            $this->processProductImages($product, $data['images']);
        }
        
        $this->logger->info('Product created', [
            'product_id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku()
        ]);
        
        return $product;
    }
    
    public function updateProduct(Product $product, array $data): Product
    {
        $oldData = [
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'stock' => $product->getStockQuantity()
        ];
        
        // Mise à jour des champs
        if (isset($data['name'])) {
            $product->setName($data['name']);
            
            // Régénérer le slug si le nom change
            if ($oldData['name'] !== $data['name']) {
                $slug = $this->slugService->generate($data['name'], Product::class, $product->getId());
                $product->setSlug($slug);
            }
        }
        
        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }
        
        if (isset($data['price'])) {
            $product->setPrice($data['price'] * 100);
        }
        
        if (isset($data['stock_quantity'])) {
            $this->updateStock($product, $data['stock_quantity'], 'manual_adjustment');
        }
        
        if (isset($data['category_id'])) {
            $product->setCategoryId($data['category_id']);
        }
        
        if (isset($data['is_active'])) {
            $product->setIsActive($data['is_active']);
        }
        
        if (isset($data['is_featured'])) {
            $product->setIsFeatured($data['is_featured']);
        }
        
        $product->updateTimestamp();
        
        $this->em->flush();
        
        $this->logger->info('Product updated', [
            'product_id' => $product->getId(),
            'changes' => array_diff_assoc($data, $oldData)
        ]);
        
        return $product;
    }
    
    public function updateStock(Product $product, int $newQuantity, string $reason = 'adjustment'): void
    {
        $oldQuantity = $product->getStockQuantity();
        $product->setStockQuantity($newQuantity);
        
        // Créer un log de mouvement de stock
        $this->createStockMovement($product, $oldQuantity, $newQuantity, $reason);
        
        // Vérifier si le produit nécessite un réapprovisionnement
        if ($product->needsRestock()) {
            $this->triggerRestockAlert($product);
        }
        
        $this->em->flush();
    }
    
    public function reserveStock(Product $product, int $quantity): bool
    {
        if (!$product->getTrackInventory()) {
            return true; // Pas de gestion de stock
        }
        
        if ($product->getAvailableStock() < $quantity) {
            if (!$product->getAllowBackorder()) {
                return false; // Stock insuffisant
            }
        }
        
        $product->setReservedQuantity($product->getReservedQuantity() + $quantity);
        $this->em->flush();
        
        return true;
    }
    
    public function releaseStock(Product $product, int $quantity): void
    {
        $currentReserved = $product->getReservedQuantity();
        $newReserved = max(0, $currentReserved - $quantity);
        
        $product->setReservedQuantity($newReserved);
        $this->em->flush();
    }
    
    public function confirmStockReservation(Product $product, int $quantity): void
    {
        // Retirer de la réservation ET du stock disponible
        $product->setReservedQuantity($product->getReservedQuantity() - $quantity);
        $product->setStockQuantity($product->getStockQuantity() - $quantity);
        
        $this->createStockMovement($product, $product->getStockQuantity() + $quantity, $product->getStockQuantity(), 'sale');
        
        $this->em->flush();
    }
    
    public function duplicateProduct(Product $original, array $overrides = []): Product
    {
        $data = [
            'name' => $overrides['name'] ?? $original->getName() . ' (Copie)',
            'description' => $original->getDescription(),
            'short_description' => $original->getShortDescription(),
            'sku' => $overrides['sku'] ?? $this->generateUniqueSku($original->getSku()),
            'price' => $original->getPrice() / 100,
            'category_id' => $original->getCategoryId(),
            'brand_id' => $original->getBrandId(),
            'stock_quantity' => 0, // Nouveau produit sans stock
            'weight' => $original->getWeight(),
            'dimensions' => $original->getDimensions(),
            'is_active' => false, // Inactif par défaut
            'is_featured' => false,
            'is_digital' => $original->getIsDigital(),
            'meta_title' => $original->getMetaTitle(),
            'meta_description' => $original->getMetaDescription()
        ];
        
        // Appliquer les surcharges
        $data = array_merge($data, $overrides);
        
        return $this->createProduct($data);
    }
    
    public function deleteProduct(Product $product): void
    {
        // Vérifier s'il y a des commandes en cours
        $activeOrders = $this->em->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('order_items', 'oi')
            ->innerJoin('orders', 'o', 'o.id = oi.order_id')
            ->where('oi.product_id = ? AND o.status NOT IN (?, ?)')
            ->setParameter(0, $product->getId())
            ->setParameter(1, 'completed')
            ->setParameter(2, 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($activeOrders > 0) {
            throw new \RuntimeException('Cannot delete product with active orders');
        }
        
        // Suppression en cascade des relations
        $this->em->remove($product);
        $this->em->flush();
        
        $this->logger->info('Product deleted', [
            'product_id' => $product->getId(),
            'name' => $product->getName()
        ]);
    }
    
    public function bulkUpdatePrices(array $productIds, float $adjustment, string $type = 'percentage'): int
    {
        $updated = 0;
        
        $products = $this->productRepository->findByIds($productIds);
        
        foreach ($products as $product) {
            $currentPrice = $product->getPrice();
            
            if ($type === 'percentage') {
                $newPrice = $currentPrice * (1 + $adjustment / 100);
            } else {
                $newPrice = $currentPrice + ($adjustment * 100); // Conversion en centimes
            }
            
            $product->setPrice(round($newPrice));
            $updated++;
        }
        
        $this->em->flush();
        
        $this->logger->info('Bulk price update', [
            'products_updated' => $updated,
            'adjustment' => $adjustment,
            'type' => $type
        ]);
        
        return $updated;
    }
    
    private function processProductImages(Product $product, array $images): void
    {
        foreach ($images as $index => $imageData) {
            $imagePath = $this->imageService->processProductImage($imageData);
            
            $productImage = new ProductImage();
            $productImage->setProduct($product)
                         ->setImageUrl($imagePath)
                         ->setSortOrder($index)
                         ->setIsMain($index === 0);
            
            $this->em->persist($productImage);
        }
        
        $this->em->flush();
    }
    
    private function createStockMovement(Product $product, int $oldQuantity, int $newQuantity, string $reason): void
    {
        $movement = new StockMovement();
        $movement->setProductId($product->getId())
                 ->setOldQuantity($oldQuantity)
                 ->setNewQuantity($newQuantity)
                 ->setQuantityChange($newQuantity - $oldQuantity)
                 ->setReason($reason)
                 ->setCreatedAt(new \DateTimeImmutable());
        
        $this->em->persist($movement);
    }
    
    private function triggerRestockAlert(Product $product): void
    {
        // Implémentation d'alerte de réapprovisionnement
        $this->logger->warning('Product needs restock', [
            'product_id' => $product->getId(),
            'name' => $product->getName(),
            'current_stock' => $product->getStockQuantity(),
            'min_level' => $product->getMinStockLevel()
        ]);
        
        // Ici on pourrait déclencher un email, une notification, etc.
    }
    
    private function generateUniqueSku(string $baseSku): string
    {
        $counter = 1;
        
        do {
            $newSku = $baseSku . '-' . $counter;
            $exists = $this->productRepository->findBySku($newSku);
            $counter++;
        } while ($exists);
        
        return $newSku;
    }
}
```

## Gestion des variants

### ProductVariantService

```php
<?php

namespace App\Service\Catalog;

use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Repository\Catalog\ProductVariantRepository;
use MulerTech\Database\ORM\EntityManager;

class ProductVariantService
{
    private EntityManager $em;
    private ProductVariantRepository $variantRepository;
    
    public function __construct(EntityManager $em, ProductVariantRepository $variantRepository)
    {
        $this->em = $em;
        $this->variantRepository = $variantRepository;
    }
    
    public function createVariant(Product $product, array $data): ProductVariant
    {
        $variant = new ProductVariant();
        
        $variant->setProduct($product)
                ->setName($data['name'])
                ->setSku($data['sku'])
                ->setPriceAdjustment(($data['price_adjustment'] ?? 0) * 100)
                ->setCostAdjustment(($data['cost_adjustment'] ?? 0) * 100)
                ->setStockQuantity($data['stock_quantity'] ?? 0)
                ->setWeight($data['weight'] ?? null)
                ->setDimensions($data['dimensions'] ?? null)
                ->setAttributes($data['attributes'] ?? [])
                ->setIsActive($data['is_active'] ?? true)
                ->setSortOrder($data['sort_order'] ?? 0);
        
        $this->em->persist($variant);
        $this->em->flush();
        
        return $variant;
    }
    
    public function createVariantsFromMatrix(Product $product, array $attributes, array $combinations): array
    {
        $variants = [];
        
        foreach ($combinations as $combination) {
            $variantName = $this->buildVariantName($combination);
            $variantSku = $this->buildVariantSku($product->getSku(), $combination);
            
            $variantData = [
                'name' => $variantName,
                'sku' => $variantSku,
                'attributes' => $combination,
                'price_adjustment' => $combination['price_adjustment'] ?? 0,
                'stock_quantity' => $combination['stock_quantity'] ?? 0
            ];
            
            $variants[] = $this->createVariant($product, $variantData);
        }
        
        return $variants;
    }
    
    public function generateAllCombinations(array $attributes): array
    {
        $combinations = [[]];
        
        foreach ($attributes as $attributeName => $values) {
            $newCombinations = [];
            
            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $newCombination = $combination;
                    $newCombination[$attributeName] = $value;
                    $newCombinations[] = $newCombination;
                }
            }
            
            $combinations = $newCombinations;
        }
        
        return $combinations;
    }
    
    public function findVariantByAttributes(Product $product, array $attributes): ?ProductVariant
    {
        $variants = $this->variantRepository->findByProduct($product);
        
        foreach ($variants as $variant) {
            if ($this->attributesMatch($variant->getAttributes(), $attributes)) {
                return $variant;
            }
        }
        
        return null;
    }
    
    public function getAttributeOptions(Product $product): array
    {
        $variants = $this->variantRepository->findActiveByProduct($product);
        $options = [];
        
        foreach ($variants as $variant) {
            foreach ($variant->getAttributes() as $name => $value) {
                if (!isset($options[$name])) {
                    $options[$name] = [];
                }
                
                if (!in_array($value, $options[$name])) {
                    $options[$name][] = $value;
                }
            }
        }
        
        // Trier les options
        foreach ($options as $name => &$values) {
            sort($values);
        }
        
        return $options;
    }
    
    public function updateVariantStock(ProductVariant $variant, int $newQuantity, string $reason = 'adjustment'): void
    {
        $oldQuantity = $variant->getStockQuantity();
        $variant->setStockQuantity($newQuantity);
        
        // Mettre à jour le stock total du produit parent
        $this->updateProductStockFromVariants($variant->getProduct());
        
        $this->em->flush();
    }
    
    public function updateProductStockFromVariants(Product $product): void
    {
        $variants = $this->variantRepository->findByProduct($product);
        
        $totalStock = array_sum(array_map(fn($v) => $v->getStockQuantity(), $variants));
        $totalReserved = array_sum(array_map(fn($v) => $v->getReservedQuantity(), $variants));
        
        $product->setStockQuantity($totalStock);
        $product->setReservedQuantity($totalReserved);
    }
    
    private function buildVariantName(array $attributes): string
    {
        $parts = [];
        
        foreach ($attributes as $name => $value) {
            if (!in_array($name, ['price_adjustment', 'stock_quantity'])) {
                $parts[] = ucfirst($name) . ': ' . $value;
            }
        }
        
        return implode(', ', $parts);
    }
    
    private function buildVariantSku(string $baseSku, array $attributes): string
    {
        $suffixes = [];
        
        foreach ($attributes as $name => $value) {
            if (!in_array($name, ['price_adjustment', 'stock_quantity'])) {
                $suffixes[] = strtoupper(substr($name, 0, 1)) . strtoupper(substr($value, 0, 2));
            }
        }
        
        return $baseSku . '-' . implode('-', $suffixes);
    }
    
    private function attributesMatch(array $variantAttributes, array $targetAttributes): bool
    {
        foreach ($targetAttributes as $name => $value) {
            if (!isset($variantAttributes[$name]) || $variantAttributes[$name] !== $value) {
                return false;
            }
        }
        
        return true;
    }
}
```

## Système de catégories

### CategoryService

```php
<?php

namespace App\Service\Catalog;

use App\Entity\Catalog\Category;
use App\Repository\Catalog\CategoryRepository;
use App\Service\SlugService;
use MulerTech\Database\ORM\EntityManager;

class CategoryService
{
    private EntityManager $em;
    private CategoryRepository $categoryRepository;
    private SlugService $slugService;
    
    public function __construct(EntityManager $em, CategoryRepository $categoryRepository, SlugService $slugService)
    {
        $this->em = $em;
        $this->categoryRepository = $categoryRepository;
        $this->slugService = $slugService;
    }
    
    public function getCategoryTree(): array
    {
        $allCategories = $this->categoryRepository->findAll();
        
        return $this->buildTree($allCategories);
    }
    
    public function getCategoryBreadcrumb(Category $category): array
    {
        return $category->getPath();
    }
    
    public function moveCategory(Category $category, ?Category $newParent): void
    {
        $category->setParent($newParent);
        $category->setParentId($newParent?->getId());
        
        $this->em->flush();
    }
    
    public function reorderCategories(array $categoryIds): void
    {
        foreach ($categoryIds as $index => $categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            if ($category) {
                $category->setSortOrder($index + 1);
            }
        }
        
        $this->em->flush();
    }
    
    public function deleteCategory(Category $category, bool $moveProductsToParent = true): void
    {
        if ($moveProductsToParent && $category->getParent()) {
            // Déplacer les produits vers la catégorie parent
            $this->em->createQueryBuilder()
                     ->update('products')
                     ->set('category_id', '?')
                     ->where('category_id = ?')
                     ->setParameter(0, $category->getParent()->getId())
                     ->setParameter(1, $category->getId())
                     ->getQuery()
                     ->execute();
        }
        
        // Supprimer les sous-catégories ou les déplacer
        $children = $this->categoryRepository->findByParent($category);
        foreach ($children as $child) {
            if ($moveProductsToParent) {
                $child->setParent($category->getParent());
                $child->setParentId($category->getParent()?->getId());
            } else {
                $this->deleteCategory($child, false);
            }
        }
        
        $this->em->remove($category);
        $this->em->flush();
    }
    
    private function buildTree(array $categories, ?int $parentId = null): array
    {
        $tree = [];
        
        foreach ($categories as $category) {
            if ($category->getParentId() === $parentId) {
                $categoryData = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'slug' => $category->getSlug(),
                    'level' => $category->getLevel(),
                    'children' => $this->buildTree($categories, $category->getId())
                ];
                
                $tree[] = $categoryData;
            }
        }
        
        // Trier par sort_order
        usort($tree, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        
        return $tree;
    }
}
```

## Gestion des prix

### PricingService

```php
<?php

namespace App\Service\Catalog;

use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Entity\Customer\Customer;
use App\Entity\Pricing\PriceRule;
use MulerTech\Database\ORM\EntityManager;

class PricingService
{
    private EntityManager $em;
    
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }
    
    public function getPrice(Product $product, ?ProductVariant $variant = null, ?Customer $customer = null, int $quantity = 1): int
    {
        $basePrice = $variant ? $variant->getActualPrice() : $product->getPrice();
        
        // Appliquer les règles de prix
        $price = $this->applyPriceRules($basePrice, $product, $customer, $quantity);
        
        // Appliquer les remises de quantité
        $price = $this->applyQuantityDiscounts($price, $product, $quantity);
        
        return max($price, 0); // Prix minimum de 0
    }
    
    public function getDisplayPrice(Product $product, ?ProductVariant $variant = null): array
    {
        $regularPrice = $variant ? $variant->getActualPrice() : $product->getPrice();
        $comparePrice = $product->getComparePrice();
        
        $result = [
            'regular' => $regularPrice,
            'formatted_regular' => $this->formatPrice($regularPrice),
            'has_discount' => false,
            'discount_percentage' => 0
        ];
        
        if ($comparePrice && $comparePrice > $regularPrice) {
            $result['compare'] = $comparePrice;
            $result['formatted_compare'] = $this->formatPrice($comparePrice);
            $result['has_discount'] = true;
            $result['discount_percentage'] = round((($comparePrice - $regularPrice) / $comparePrice) * 100);
            $result['savings'] = $comparePrice - $regularPrice;
            $result['formatted_savings'] = $this->formatPrice($result['savings']);
        }
        
        return $result;
    }
    
    public function applyBulkPricing(array $items): array
    {
        $pricedItems = [];
        
        foreach ($items as $item) {
            $product = $item['product'];
            $variant = $item['variant'] ?? null;
            $quantity = $item['quantity'];
            $customer = $item['customer'] ?? null;
            
            $unitPrice = $this->getPrice($product, $variant, $customer, $quantity);
            $totalPrice = $unitPrice * $quantity;
            
            $pricedItems[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'formatted_unit_price' => $this->formatPrice($unitPrice),
                'formatted_total_price' => $this->formatPrice($totalPrice)
            ];
        }
        
        return $pricedItems;
    }
    
    public function calculateTierPricing(Product $product, ?ProductVariant $variant = null): array
    {
        $tiers = [
            ['min_qty' => 1, 'discount' => 0],
            ['min_qty' => 5, 'discount' => 5],
            ['min_qty' => 10, 'discount' => 10],
            ['min_qty' => 25, 'discount' => 15],
        ];
        
        $basePrice = $variant ? $variant->getActualPrice() : $product->getPrice();
        $tierPricing = [];
        
        foreach ($tiers as $tier) {
            $discountedPrice = $basePrice * (1 - $tier['discount'] / 100);
            
            $tierPricing[] = [
                'min_quantity' => $tier['min_qty'],
                'price' => $discountedPrice,
                'formatted_price' => $this->formatPrice($discountedPrice),
                'discount_percentage' => $tier['discount'],
                'savings_per_unit' => $basePrice - $discountedPrice,
                'formatted_savings' => $this->formatPrice($basePrice - $discountedPrice)
            ];
        }
        
        return $tierPricing;
    }
    
    private function applyPriceRules(int $basePrice, Product $product, ?Customer $customer, int $quantity): int
    {
        $rules = $this->getPriceRules($product, $customer);
        
        foreach ($rules as $rule) {
            if ($this->ruleApplies($rule, $product, $customer, $quantity)) {
                $basePrice = $this->applyRule($basePrice, $rule);
            }
        }
        
        return $basePrice;
    }
    
    private function applyQuantityDiscounts(int $price, Product $product, int $quantity): int
    {
        // Remises par paliers de quantité
        if ($quantity >= 25) {
            return $price * 0.85; // 15% de remise
        } elseif ($quantity >= 10) {
            return $price * 0.90; // 10% de remise
        } elseif ($quantity >= 5) {
            return $price * 0.95; // 5% de remise
        }
        
        return $price;
    }
    
    private function getPriceRules(Product $product, ?Customer $customer): array
    {
        // Récupérer les règles de prix applicables
        $qb = $this->em->createQueryBuilder()
                      ->select('*')
                      ->from('price_rules')
                      ->where('is_active = ?')
                      ->andWhere('(starts_at IS NULL OR starts_at <= ?)')
                      ->andWhere('(ends_at IS NULL OR ends_at >= ?)')
                      ->orderBy('priority', 'DESC')
                      ->setParameter(0, true)
                      ->setParameter(1, new \DateTimeImmutable())
                      ->setParameter(2, new \DateTimeImmutable());
        
        // Filtrer par produit si applicable
        $qb->andWhere('(applies_to = "all" OR (applies_to = "product" AND target_id = ?))')
           ->setParameter(3, $product->getId());
        
        // Filtrer par groupe de clients si applicable
        if ($customer && $customer->getCustomerGroupId()) {
            $qb->andWhere('(customer_group_id IS NULL OR customer_group_id = ?)')
               ->setParameter(4, $customer->getCustomerGroupId());
        }
        
        return $qb->getQuery()->getResult();
    }
    
    private function formatPrice(int $priceInCents): string
    {
        return number_format($priceInCents / 100, 2, ',', ' ') . ' €';
    }
}
```

## Recherche et filtrage

### ProductSearchService

```php
<?php

namespace App\Service\Catalog;

use App\Repository\Catalog\ProductRepository;
use MulerTech\Database\ORM\EntityManager;

class ProductSearchService
{
    private EntityManager $em;
    private ProductRepository $productRepository;
    
    public function __construct(EntityManager $em, ProductRepository $productRepository)
    {
        $this->em = $em;
        $this->productRepository = $productRepository;
    }
    
    public function search(array $params): array
    {
        $query = $params['q'] ?? '';
        $categoryId = $params['category_id'] ?? null;
        $brandId = $params['brand_id'] ?? null;
        $minPrice = $params['min_price'] ?? null;
        $maxPrice = $params['max_price'] ?? null;
        $inStock = $params['in_stock'] ?? false;
        $page = max(1, $params['page'] ?? 1);
        $limit = min(48, max(12, $params['limit'] ?? 24));
        $sortBy = $params['sort'] ?? 'relevance';
        
        $qb = $this->em->createQueryBuilder()
                      ->select('p.*, b.name as brand_name, c.name as category_name');
        
        if (!empty($query)) {
            $qb->addSelect('MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance');
            $qb->setParameter('query', $query);
            $qb->setParameter('query2', $query);
        }
        
        $qb->from('products', 'p')
           ->leftJoin('brands', 'b', 'b.id = p.brand_id')
           ->leftJoin('categories', 'c', 'c.id = p.category_id')
           ->where('p.is_active = ?')
           ->setParameter('active', true);
        
        if (!empty($query)) {
            $qb->andWhere('MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)')
               ->setParameter('search_query', $query);
        }
        
        if ($categoryId) {
            $qb->andWhere('p.category_id = ?')
               ->setParameter('category', $categoryId);
        }
        
        if ($brandId) {
            $qb->andWhere('p.brand_id = ?')
               ->setParameter('brand', $brandId);
        }
        
        if ($minPrice) {
            $qb->andWhere('p.price >= ?')
               ->setParameter('min_price', $minPrice * 100);
        }
        
        if ($maxPrice) {
            $qb->andWhere('p.price <= ?')
               ->setParameter('max_price', $maxPrice * 100);
        }
        
        if ($inStock) {
            $qb->andWhere('(p.track_inventory = 0 OR p.stock_quantity > p.reserved_quantity)');
        }
        
        // Tri
        switch ($sortBy) {
            case 'price_asc':
                $qb->orderBy('p.price', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('p.price', 'DESC');
                break;
            case 'name':
                $qb->orderBy('p.name', 'ASC');
                break;
            case 'newest':
                $qb->orderBy('p.created_at', 'DESC');
                break;
            case 'featured':
                $qb->orderBy('p.is_featured', 'DESC')
                   ->addOrderBy('p.sort_order', 'ASC');
                break;
            default: // relevance
                if (!empty($query)) {
                    $qb->orderBy('relevance', 'DESC');
                } else {
                    $qb->orderBy('p.sort_order', 'ASC');
                }
        }
        
        // Pagination
        $offset = ($page - 1) * $limit;
        $qb->limit($limit)->offset($offset);
        
        $products = $qb->getQuery()->getResult();
        
        // Compter le total pour la pagination
        $totalQuery = $this->em->createQueryBuilder()
                              ->select('COUNT(p.id)')
                              ->from('products', 'p')
                              ->where('p.is_active = ?')
                              ->setParameter('active', true);
        
        // Appliquer les mêmes filtres pour le count
        if (!empty($query)) {
            $totalQuery->andWhere('MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)')
                      ->setParameter('search_query', $query);
        }
        
        if ($categoryId) {
            $totalQuery->andWhere('p.category_id = ?')
                      ->setParameter('category', $categoryId);
        }
        
        if ($brandId) {
            $totalQuery->andWhere('p.brand_id = ?')
                      ->setParameter('brand', $brandId);
        }
        
        if ($minPrice) {
            $totalQuery->andWhere('p.price >= ?')
                      ->setParameter('min_price', $minPrice * 100);
        }
        
        if ($maxPrice) {
            $totalQuery->andWhere('p.price <= ?')
                      ->setParameter('max_price', $maxPrice * 100);
        }
        
        if ($inStock) {
            $totalQuery->andWhere('(p.track_inventory = 0 OR p.stock_quantity > p.reserved_quantity)');
        }
        
        $total = $totalQuery->getQuery()->getSingleScalarResult();
        
        return [
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int) $total,
                'total_pages' => ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_prev' => $page > 1
            ],
            'filters' => $this->getSearchFilters($params),
            'applied_filters' => $this->getAppliedFilters($params)
        ];
    }
    
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        return $this->em->createQueryBuilder()
                       ->select('p.name, p.slug')
                       ->from('products', 'p')
                       ->where('p.is_active = ? AND p.name LIKE ?')
                       ->orderBy('p.name', 'ASC')
                       ->limit($limit)
                       ->setParameter(0, true)
                       ->setParameter(1, $query . '%')
                       ->getQuery()
                       ->getResult();
    }
    
    private function getSearchFilters(array $params): array
    {
        // Récupérer les filtres disponibles basés sur les résultats
        return [
            'categories' => $this->getAvailableCategories($params),
            'brands' => $this->getAvailableBrands($params),
            'price_range' => $this->getPriceRange($params)
        ];
    }
    
    private function getAppliedFilters(array $params): array
    {
        $applied = [];
        
        if (!empty($params['category_id'])) {
            $applied['category'] = $params['category_id'];
        }
        
        if (!empty($params['brand_id'])) {
            $applied['brand'] = $params['brand_id'];
        }
        
        if (!empty($params['min_price']) || !empty($params['max_price'])) {
            $applied['price'] = [
                'min' => $params['min_price'] ?? null,
                'max' => $params['max_price'] ?? null
            ];
        }
        
        return $applied;
    }
}
```

---

Cette implémentation complète du système de gestion de catalogue démontre l'utilisation avancée de MulerTech Database ORM pour construire une solution e-commerce robuste et performante.