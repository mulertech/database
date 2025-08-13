# Gestion d'Inventaire - E-commerce

Cette section présente l'implémentation complète du système de gestion d'inventaire temps réel, démontrant la gestion des stocks, réservations, mouvements et alertes avec MulerTech Database ORM.

## Table des matières

- [Architecture d'inventaire](#architecture-dinventaire)
- [Entités d'inventaire](#entités-dinventaire)
- [Service d'inventaire](#service-dinventaire)
- [Gestion des réservations](#gestion-des-réservations)
- [Mouvements de stock](#mouvements-de-stock)
- [Multi-entrepôts](#multi-entrepôts)
- [Alertes et notifications](#alertes-et-notifications)
- [Synchronisation temps réel](#synchronisation-temps-réel)
- [Rapports et analytics](#rapports-et-analytics)
- [API d'inventaire](#api-dinventaire)

## Architecture d'inventaire

### Schéma des entités d'inventaire

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   warehouses    │    │ stock_locations │    │ inventory_items │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ id (PK)         │◄──┐│ id (PK)         │◄──┐│ id (PK)         │
│ name            │   ││ warehouse_id    │   ││ location_id     │
│ code            │   ││ zone            │   ││ product_id      │
│ address         │   ││ aisle           │   ││ variant_id      │
│ is_active       │   ││ shelf           │   ││ quantity        │
│ priority        │   ││ position        │   ││ reserved_qty    │
└─────────────────┘   │└─────────────────┘   ││ allocated_qty   │
                      │                      ││ last_counted_at │
                      │                      │└─────────────────┘
                      │                      │
┌─────────────────┐   │ ┌─────────────────┐ │
│stock_movements  │   │ │ stock_reservations│ │
├─────────────────┤   │ ├─────────────────┤ │
│ id (PK)         │   │ │ id (PK)         │ │
│ inventory_id    ├───┘ │ inventory_id    ├─┘
│ type            │     │ order_id        │
│ quantity        │     │ quantity        │
│ reason          │     │ expires_at      │
│ reference       │     │ status          │
│ created_at      │     │ created_at      │
└─────────────────┘     └─────────────────┘
```

## Entités d'inventaire

### Warehouse - Entrepôt

```php
<?php

namespace App\Entity\Inventory;

use App\Entity\BaseEntity;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'warehouses')]
class Warehouse extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(type: 'varchar', length: 100)]
    private string $name;
    
    #[MtColumn(type: 'varchar', length: 20, unique: true)]
    private string $code;
    
    #[MtColumn(type: 'json')]
    private array $address = [];
    
    #[MtColumn(name: 'contact_info', type: 'json', nullable: true)]
    private ?array $contactInfo = null;
    
    #[MtColumn(name: 'is_active', type: 'boolean', default: true)]
    private bool $isActive = true;
    
    #[MtColumn(type: 'int', default: 0)]
    private int $priority = 0; // Pour l'ordre de préférence
    
    #[MtColumn(name: 'operating_hours', type: 'json', nullable: true)]
    private ?array $operatingHours = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $capabilities = null; // ['shipping', 'receiving', 'returns']
    
    // Relations
    #[MtRelation('OneToMany', targetEntity: StockLocation::class, mappedBy: 'warehouse')]
    private array $locations = [];
    
    #[MtRelation('OneToMany', targetEntity: InventoryItem::class, mappedBy: 'warehouse')]
    private array $inventoryItems = [];
    
    public function getTotalCapacity(): int
    {
        return array_sum(array_map(fn($location) => $location->getCapacity(), $this->locations));
    }
    
    public function getOccupiedCapacity(): int
    {
        return array_sum(array_map(fn($location) => $location->getOccupiedCapacity(), $this->locations));
    }
    
    public function getUtilizationRate(): float
    {
        $total = $this->getTotalCapacity();
        return $total > 0 ? ($this->getOccupiedCapacity() / $total) * 100 : 0;
    }
    
    public function canHandleCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? []);
    }
    
    // Getters et setters...
}
```

### InventoryItem - Article d'inventaire

```php
<?php

namespace App\Entity\Inventory;

use App\Entity\BaseEntity;
use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'inventory_items')]
class InventoryItem extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'warehouse_id', type: 'int')]
    private int $warehouseId;
    
    #[MtColumn(name: 'location_id', type: 'int', nullable: true)]
    private ?int $locationId = null;
    
    #[MtColumn(name: 'product_id', type: 'int')]
    private int $productId;
    
    #[MtColumn(name: 'variant_id', type: 'int', nullable: true)]
    private ?int $variantId = null;
    
    #[MtColumn(name: 'quantity_on_hand', type: 'int', default: 0)]
    private int $quantityOnHand = 0;
    
    #[MtColumn(name: 'quantity_reserved', type: 'int', default: 0)]
    private int $quantityReserved = 0;
    
    #[MtColumn(name: 'quantity_allocated', type: 'int', default: 0)]
    private int $quantityAllocated = 0;
    
    #[MtColumn(name: 'quantity_incoming', type: 'int', default: 0)]
    private int $quantityIncoming = 0; // Commandes fournisseur
    
    #[MtColumn(name: 'quantity_damaged', type: 'int', default: 0)]
    private int $quantityDamaged = 0;
    
    #[MtColumn(name: 'min_quantity', type: 'int', default: 0)]
    private int $minQuantity = 0;
    
    #[MtColumn(name: 'max_quantity', type: 'int', nullable: true)]
    private ?int $maxQuantity = null;
    
    #[MtColumn(name: 'reorder_point', type: 'int', default: 0)]
    private int $reorderPoint = 0;
    
    #[MtColumn(name: 'reorder_quantity', type: 'int', default: 0)]
    private int $reorderQuantity = 0;
    
    #[MtColumn(name: 'last_counted_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $lastCountedAt = null;
    
    #[MtColumn(name: 'last_movement_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $lastMovementAt = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $metadata = null; // Infos spécifiques (lot, expiration, etc.)
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Warehouse::class, inversedBy: 'inventoryItems')]
    private ?Warehouse $warehouse = null;
    
    #[MtRelation('ManyToOne', targetEntity: StockLocation::class)]
    private ?StockLocation $location = null;
    
    #[MtRelation('ManyToOne', targetEntity: Product::class)]
    private ?Product $product = null;
    
    #[MtRelation('ManyToOne', targetEntity: ProductVariant::class)]
    private ?ProductVariant $variant = null;
    
    #[MtRelation('OneToMany', targetEntity: StockMovement::class, mappedBy: 'inventoryItem')]
    private array $movements = [];
    
    #[MtRelation('OneToMany', targetEntity: StockReservation::class, mappedBy: 'inventoryItem')]
    private array $reservations = [];
    
    public function getAvailableQuantity(): int
    {
        return $this->quantityOnHand - $this->quantityReserved - $this->quantityAllocated - $this->quantityDamaged;
    }
    
    public function getTotalQuantity(): int
    {
        return $this->quantityOnHand + $this->quantityIncoming;
    }
    
    public function needsReorder(): bool
    {
        return $this->getAvailableQuantity() <= $this->reorderPoint;
    }
    
    public function isOverstocked(): bool
    {
        return $this->maxQuantity && $this->quantityOnHand > $this->maxQuantity;
    }
    
    public function getStockStatus(): string
    {
        if ($this->getAvailableQuantity() <= 0) {
            return 'out_of_stock';
        } elseif ($this->needsReorder()) {
            return 'low_stock';
        } elseif ($this->isOverstocked()) {
            return 'overstocked';
        }
        return 'in_stock';
    }
    
    public function adjustQuantity(int $adjustment, string $reason = 'manual_adjustment'): StockMovement
    {
        $oldQuantity = $this->quantityOnHand;
        $this->quantityOnHand += $adjustment;
        $this->lastMovementAt = new \DateTimeImmutable();
        
        // Créer le mouvement de stock
        $movement = new StockMovement();
        $movement->setInventoryItem($this)
                 ->setType($adjustment > 0 ? 'in' : 'out')
                 ->setQuantity(abs($adjustment))
                 ->setQuantityBefore($oldQuantity)
                 ->setQuantityAfter($this->quantityOnHand)
                 ->setReason($reason);
        
        return $movement;
    }
    
    public function reserveQuantity(int $quantity, string $reference = ''): bool
    {
        if ($this->getAvailableQuantity() < $quantity) {
            return false;
        }
        
        $this->quantityReserved += $quantity;
        return true;
    }
    
    public function releaseReservation(int $quantity): void
    {
        $this->quantityReserved = max(0, $this->quantityReserved - $quantity);
    }
    
    // Getters et setters...
}
```

### StockMovement - Mouvement de stock

```php
<?php

namespace App\Entity\Inventory;

use App\Entity\BaseEntity;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'stock_movements')]
class StockMovement extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'inventory_item_id', type: 'int')]
    private int $inventoryItemId;
    
    #[MtColumn(type: 'enum', values: ['in', 'out', 'transfer', 'adjustment'])]
    private string $type;
    
    #[MtColumn(type: 'int')]
    private int $quantity;
    
    #[MtColumn(name: 'quantity_before', type: 'int')]
    private int $quantityBefore;
    
    #[MtColumn(name: 'quantity_after', type: 'int')]
    private int $quantityAfter;
    
    #[MtColumn(type: 'varchar', length: 100)]
    private string $reason;
    
    #[MtColumn(type: 'varchar', length: 255, nullable: true)]
    private ?string $reference = null; // Order ID, PO number, etc.
    
    #[MtColumn(name: 'unit_cost', type: 'int', nullable: true)]
    private ?int $unitCost = null; // Prix d'achat en centimes
    
    #[MtColumn(name: 'created_by', type: 'int', nullable: true)]
    private ?int $createdBy = null; // User ID
    
    #[MtColumn(type: 'text', nullable: true)]
    private ?string $notes = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $metadata = null; // Données supplémentaires
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: InventoryItem::class, inversedBy: 'movements')]
    private ?InventoryItem $inventoryItem = null;
    
    public function getValueImpact(): int
    {
        if (!$this->unitCost) {
            return 0;
        }
        
        $multiplier = $this->type === 'in' ? 1 : -1;
        return $this->quantity * $this->unitCost * $multiplier;
    }
    
    public function getDisplayReason(): string
    {
        return match($this->reason) {
            'sale' => 'Vente',
            'return' => 'Retour client',
            'damage' => 'Produit endommagé',
            'theft' => 'Vol/Perte',
            'restock' => 'Réapprovisionnement',
            'adjustment' => 'Ajustement manuel',
            'transfer_in' => 'Transfert entrant',
            'transfer_out' => 'Transfert sortant',
            'sample' => 'Échantillon',
            'promotion' => 'Promotion/Don',
            default => ucfirst(str_replace('_', ' ', $this->reason))
        };
    }
    
    // Getters et setters...
}
```

## Service d'inventaire

### InventoryService

```php
<?php

namespace App\Service\Inventory;

use App\Entity\Inventory\InventoryItem;
use App\Entity\Inventory\StockMovement;
use App\Entity\Inventory\StockReservation;
use App\Entity\Catalog\Product;
use App\Entity\Catalog\ProductVariant;
use App\Repository\Inventory\InventoryRepository;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class InventoryService
{
    private EntityManager $em;
    private InventoryRepository $inventoryRepository;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManager $em,
        InventoryRepository $inventoryRepository,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->inventoryRepository = $inventoryRepository;
        $this->logger = $logger;
    }
    
    public function getInventoryItem(
        Product $product,
        ?ProductVariant $variant = null,
        int $warehouseId = 1
    ): ?InventoryItem {
        return $this->inventoryRepository->findInventoryItem(
            $product->getId(),
            $variant?->getId(),
            $warehouseId
        );
    }
    
    public function getAvailableQuantity(
        Product $product,
        ?ProductVariant $variant = null,
        int $warehouseId = null
    ): int {
        if ($warehouseId) {
            $item = $this->getInventoryItem($product, $variant, $warehouseId);
            return $item ? $item->getAvailableQuantity() : 0;
        }
        
        // Somme sur tous les entrepôts
        $items = $this->inventoryRepository->findAllInventoryItems(
            $product->getId(),
            $variant?->getId()
        );
        
        return array_sum(array_map(fn($item) => $item->getAvailableQuantity(), $items));
    }
    
    public function isAvailable(
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        int $warehouseId = null
    ): bool {
        return $this->getAvailableQuantity($product, $variant, $warehouseId) >= $quantity;
    }
    
    public function reserve(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        string $reference = '',
        int $warehouseId = null,
        ?\DateTimeInterface $expiresAt = null
    ): StockReservation {
        // Trouver le meilleur entrepôt si non spécifié
        if (!$warehouseId) {
            $warehouseId = $this->findBestWarehouse($product, $variant, $quantity);
        }
        
        $inventoryItem = $this->getInventoryItem($product, $variant, $warehouseId);
        
        if (!$inventoryItem || !$inventoryItem->reserveQuantity($quantity, $reference)) {
            throw new \RuntimeException('Insufficient stock for reservation');
        }
        
        // Créer la réservation
        $reservation = new StockReservation();
        $reservation->setInventoryItem($inventoryItem)
                   ->setQuantity($quantity)
                   ->setReference($reference)
                   ->setStatus('active')
                   ->setExpiresAt($expiresAt ?? new \DateTimeImmutable('+1 hour'));
        
        $this->em->persist($reservation);
        $this->em->flush();
        
        $this->logger->info('Stock reserved', [
            'product_id' => $product->getId(),
            'variant_id' => $variant?->getId(),
            'quantity' => $quantity,
            'warehouse_id' => $warehouseId,
            'reference' => $reference
        ]);
        
        return $reservation;
    }
    
    public function release(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        int $warehouseId = null
    ): void {
        if (!$warehouseId) {
            $warehouseId = $this->findBestWarehouse($product, $variant, $quantity);
        }
        
        $inventoryItem = $this->getInventoryItem($product, $variant, $warehouseId);
        
        if ($inventoryItem) {
            $inventoryItem->releaseReservation($quantity);
            $this->em->flush();
            
            $this->logger->info('Stock reservation released', [
                'product_id' => $product->getId(),
                'variant_id' => $variant?->getId(),
                'quantity' => $quantity,
                'warehouse_id' => $warehouseId
            ]);
        }
    }
    
    public function confirm(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        int $warehouseId = null
    ): StockMovement {
        if (!$warehouseId) {
            $warehouseId = $this->findBestWarehouse($product, $variant, $quantity);
        }
        
        $inventoryItem = $this->getInventoryItem($product, $variant, $warehouseId);
        
        if (!$inventoryItem) {
            throw new \RuntimeException('Inventory item not found');
        }
        
        // Libérer la réservation et retirer du stock
        $inventoryItem->releaseReservation($quantity);
        $movement = $inventoryItem->adjustQuantity(-$quantity, 'sale');
        
        $this->em->persist($movement);
        $this->em->flush();
        
        $this->logger->info('Stock confirmed and allocated', [
            'product_id' => $product->getId(),
            'variant_id' => $variant?->getId(),
            'quantity' => $quantity,
            'warehouse_id' => $warehouseId
        ]);
        
        return $movement;
    }
    
    public function adjustStock(
        Product $product,
        ?ProductVariant $variant,
        int $adjustment,
        string $reason = 'manual_adjustment',
        int $warehouseId = 1,
        ?string $reference = null
    ): StockMovement {
        $inventoryItem = $this->getInventoryItem($product, $variant, $warehouseId);
        
        if (!$inventoryItem) {
            // Créer l'item d'inventaire s'il n'existe pas
            $inventoryItem = $this->createInventoryItem($product, $variant, $warehouseId);
        }
        
        $movement = $inventoryItem->adjustQuantity($adjustment, $reason);
        
        if ($reference) {
            $movement->setReference($reference);
        }
        
        $this->em->persist($movement);
        $this->em->flush();
        
        $this->logger->info('Stock adjusted', [
            'product_id' => $product->getId(),
            'variant_id' => $variant?->getId(),
            'adjustment' => $adjustment,
            'reason' => $reason,
            'warehouse_id' => $warehouseId,
            'new_quantity' => $inventoryItem->getQuantityOnHand()
        ]);
        
        return $movement;
    }
    
    public function transferStock(
        Product $product,
        ?ProductVariant $variant,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        string $reason = 'warehouse_transfer'
    ): array {
        try {
            $this->em->beginTransaction();
            
            // Mouvement sortant
            $outMovement = $this->adjustStock(
                $product,
                $variant,
                -$quantity,
                'transfer_out',
                $fromWarehouseId,
                "Transfer to warehouse {$toWarehouseId}"
            );
            
            // Mouvement entrant
            $inMovement = $this->adjustStock(
                $product,
                $variant,
                $quantity,
                'transfer_in',
                $toWarehouseId,
                "Transfer from warehouse {$fromWarehouseId}"
            );
            
            $this->em->commit();
            
            $this->logger->info('Stock transferred', [
                'product_id' => $product->getId(),
                'variant_id' => $variant?->getId(),
                'quantity' => $quantity,
                'from_warehouse' => $fromWarehouseId,
                'to_warehouse' => $toWarehouseId
            ]);
            
            return [$outMovement, $inMovement];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
    
    public function performStockCount(
        int $warehouseId,
        array $countData,
        int $countedBy
    ): array {
        $discrepancies = [];
        
        try {
            $this->em->beginTransaction();
            
            foreach ($countData as $count) {
                $inventoryItem = $this->inventoryRepository->find($count['inventory_item_id']);
                
                if (!$inventoryItem) {
                    continue;
                }
                
                $countedQuantity = (int) $count['counted_quantity'];
                $systemQuantity = $inventoryItem->getQuantityOnHand();
                $difference = $countedQuantity - $systemQuantity;
                
                if ($difference !== 0) {
                    $discrepancies[] = [
                        'inventory_item_id' => $inventoryItem->getId(),
                        'product_name' => $inventoryItem->getProduct()->getName(),
                        'system_quantity' => $systemQuantity,
                        'counted_quantity' => $countedQuantity,
                        'difference' => $difference
                    ];
                    
                    // Ajuster le stock
                    $movement = $inventoryItem->adjustQuantity($difference, 'stock_count');
                    $movement->setCreatedBy($countedBy);
                    $movement->setNotes($count['notes'] ?? '');
                    
                    $this->em->persist($movement);
                }
                
                // Mettre à jour la date de comptage
                $inventoryItem->setLastCountedAt(new \DateTimeImmutable());
            }
            
            $this->em->commit();
            
            $this->logger->info('Stock count completed', [
                'warehouse_id' => $warehouseId,
                'items_counted' => count($countData),
                'discrepancies' => count($discrepancies),
                'counted_by' => $countedBy
            ]);
            
            return $discrepancies;
            
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
    
    public function getLowStockItems(int $warehouseId = null): array
    {
        return $this->inventoryRepository->findLowStockItems($warehouseId);
    }
    
    public function getStockMovements(
        Product $product,
        ?ProductVariant $variant = null,
        int $warehouseId = null,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): array {
        return $this->inventoryRepository->findMovements(
            $product->getId(),
            $variant?->getId(),
            $warehouseId,
            $from,
            $to
        );
    }
    
    public function cleanupExpiredReservations(): int
    {
        $expiredReservations = $this->em->getRepository(StockReservation::class)
                                       ->findExpiredReservations();
        
        $count = 0;
        
        foreach ($expiredReservations as $reservation) {
            $inventoryItem = $reservation->getInventoryItem();
            $inventoryItem->releaseReservation($reservation->getQuantity());
            
            $reservation->setStatus('expired');
            $count++;
        }
        
        $this->em->flush();
        
        $this->logger->info('Expired reservations cleaned up', ['count' => $count]);
        
        return $count;
    }
    
    private function findBestWarehouse(Product $product, ?ProductVariant $variant, int $quantity): int
    {
        $items = $this->inventoryRepository->findAllInventoryItems(
            $product->getId(),
            $variant?->getId()
        );
        
        // Trier par priorité d'entrepôt et stock disponible
        usort($items, function($a, $b) {
            $priorityA = $a->getWarehouse()->getPriority();
            $priorityB = $b->getWarehouse()->getPriority();
            
            if ($priorityA === $priorityB) {
                return $b->getAvailableQuantity() <=> $a->getAvailableQuantity();
            }
            
            return $priorityB <=> $priorityA;
        });
        
        foreach ($items as $item) {
            if ($item->getAvailableQuantity() >= $quantity) {
                return $item->getWarehouseId();
            }
        }
        
        throw new \RuntimeException('No warehouse has sufficient stock');
    }
    
    private function createInventoryItem(
        Product $product,
        ?ProductVariant $variant,
        int $warehouseId
    ): InventoryItem {
        $inventoryItem = new InventoryItem();
        $inventoryItem->setWarehouseId($warehouseId)
                     ->setProductId($product->getId())
                     ->setProduct($product)
                     ->setVariantId($variant?->getId())
                     ->setVariant($variant)
                     ->setMinQuantity($product->getMinStockLevel())
                     ->setReorderPoint($product->getMinStockLevel())
                     ->setReorderQuantity($product->getMinStockLevel() * 2);
        
        $this->em->persist($inventoryItem);
        
        return $inventoryItem;
    }
}
```

## Gestion des alertes

### InventoryAlertService

```php
<?php

namespace App\Service\Inventory;

use App\Entity\Inventory\InventoryItem;
use App\Repository\Inventory\InventoryRepository;
use App\Service\Notification\NotificationService;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class InventoryAlertService
{
    private EntityManager $em;
    private InventoryRepository $inventoryRepository;
    private NotificationService $notificationService;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManager $em,
        InventoryRepository $inventoryRepository,
        NotificationService $notificationService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->inventoryRepository = $inventoryRepository;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }
    
    public function checkStockLevels(): array
    {
        $alerts = [];
        
        // Vérifier les stocks bas
        $lowStockItems = $this->inventoryRepository->findLowStockItems();
        foreach ($lowStockItems as $item) {
            $alerts[] = $this->createLowStockAlert($item);
        }
        
        // Vérifier les surstocks
        $overstockedItems = $this->inventoryRepository->findOverstockedItems();
        foreach ($overstockedItems as $item) {
            $alerts[] = $this->createOverstockAlert($item);
        }
        
        // Vérifier les ruptures
        $outOfStockItems = $this->inventoryRepository->findOutOfStockItems();
        foreach ($outOfStockItems as $item) {
            $alerts[] = $this->createOutOfStockAlert($item);
        }
        
        // Envoyer les notifications
        if (!empty($alerts)) {
            $this->sendAlertNotifications($alerts);
        }
        
        $this->logger->info('Stock level check completed', [
            'total_alerts' => count($alerts),
            'low_stock' => count(array_filter($alerts, fn($a) => $a['type'] === 'low_stock')),
            'overstocked' => count(array_filter($alerts, fn($a) => $a['type'] === 'overstocked')),
            'out_of_stock' => count(array_filter($alerts, fn($a) => $a['type'] === 'out_of_stock'))
        ]);
        
        return $alerts;
    }
    
    public function checkExpiringProducts(): array
    {
        $alerts = [];
        
        // Produits expirant dans les 30 jours
        $expiringItems = $this->inventoryRepository->findExpiringItems(30);
        
        foreach ($expiringItems as $item) {
            $expiryDate = $item->getMetadata()['expiry_date'] ?? null;
            
            if ($expiryDate) {
                $alerts[] = [
                    'type' => 'expiring_product',
                    'severity' => 'warning',
                    'inventory_item_id' => $item->getId(),
                    'product_name' => $item->getProduct()->getName(),
                    'warehouse' => $item->getWarehouse()->getName(),
                    'quantity' => $item->getQuantityOnHand(),
                    'expiry_date' => $expiryDate,
                    'days_until_expiry' => $this->calculateDaysUntilExpiry($expiryDate),
                    'message' => "Product expires in {$this->calculateDaysUntilExpiry($expiryDate)} days"
                ];
            }
        }
        
        return $alerts;
    }
    
    public function createReorderSuggestions(): array
    {
        $suggestions = [];
        $lowStockItems = $this->inventoryRepository->findLowStockItems();
        
        foreach ($lowStockItems as $item) {
            // Calculer la quantité suggérée basée sur l'historique
            $suggestedQuantity = $this->calculateSuggestedReorderQuantity($item);
            
            $suggestions[] = [
                'inventory_item_id' => $item->getId(),
                'product_name' => $item->getProduct()->getName(),
                'sku' => $item->getProduct()->getSku(),
                'warehouse' => $item->getWarehouse()->getName(),
                'current_stock' => $item->getQuantityOnHand(),
                'available_stock' => $item->getAvailableQuantity(),
                'reorder_point' => $item->getReorderPoint(),
                'suggested_quantity' => $suggestedQuantity,
                'estimated_cost' => $this->estimateReorderCost($item, $suggestedQuantity),
                'priority' => $this->calculateReorderPriority($item)
            ];
        }
        
        // Trier par priorité
        usort($suggestions, fn($a, $b) => $b['priority'] <=> $a['priority']);
        
        return $suggestions;
    }
    
    public function generateStockReport(int $warehouseId = null): array
    {
        $items = $warehouseId ? 
            $this->inventoryRepository->findByWarehouse($warehouseId) :
            $this->inventoryRepository->findAll();
        
        $report = [
            'summary' => [
                'total_items' => 0,
                'total_quantity' => 0,
                'total_value' => 0,
                'in_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'overstocked' => 0
            ],
            'by_category' => [],
            'by_warehouse' => [],
            'movements_summary' => []
        ];
        
        foreach ($items as $item) {
            $report['summary']['total_items']++;
            $report['summary']['total_quantity'] += $item->getQuantityOnHand();
            
            // Valeur estimée (utiliser le prix du produit)
            $unitValue = $item->getProduct()->getCostPrice() ?? $item->getProduct()->getPrice();
            $report['summary']['total_value'] += $item->getQuantityOnHand() * $unitValue;
            
            // Statut du stock
            $status = $item->getStockStatus();
            $report['summary'][$status]++;
            
            // Grouper par catégorie
            $categoryName = $item->getProduct()->getCategory()?->getName() ?? 'Sans catégorie';
            if (!isset($report['by_category'][$categoryName])) {
                $report['by_category'][$categoryName] = [
                    'items_count' => 0,
                    'total_quantity' => 0,
                    'total_value' => 0
                ];
            }
            
            $report['by_category'][$categoryName]['items_count']++;
            $report['by_category'][$categoryName]['total_quantity'] += $item->getQuantityOnHand();
            $report['by_category'][$categoryName]['total_value'] += $item->getQuantityOnHand() * $unitValue;
            
            // Grouper par entrepôt
            $warehouseName = $item->getWarehouse()->getName();
            if (!isset($report['by_warehouse'][$warehouseName])) {
                $report['by_warehouse'][$warehouseName] = [
                    'items_count' => 0,
                    'total_quantity' => 0,
                    'utilization_rate' => 0
                ];
            }
            
            $report['by_warehouse'][$warehouseName]['items_count']++;
            $report['by_warehouse'][$warehouseName]['total_quantity'] += $item->getQuantityOnHand();
        }
        
        return $report;
    }
    
    private function createLowStockAlert(InventoryItem $item): array
    {
        return [
            'type' => 'low_stock',
            'severity' => 'warning',
            'inventory_item_id' => $item->getId(),
            'product_name' => $item->getProduct()->getName(),
            'warehouse' => $item->getWarehouse()->getName(),
            'current_stock' => $item->getQuantityOnHand(),
            'available_stock' => $item->getAvailableQuantity(),
            'reorder_point' => $item->getReorderPoint(),
            'message' => "Low stock: {$item->getAvailableQuantity()} units remaining (reorder point: {$item->getReorderPoint()})"
        ];
    }
    
    private function createOverstockAlert(InventoryItem $item): array
    {
        return [
            'type' => 'overstocked',
            'severity' => 'info',
            'inventory_item_id' => $item->getId(),
            'product_name' => $item->getProduct()->getName(),
            'warehouse' => $item->getWarehouse()->getName(),
            'current_stock' => $item->getQuantityOnHand(),
            'max_quantity' => $item->getMaxQuantity(),
            'excess_quantity' => $item->getQuantityOnHand() - $item->getMaxQuantity(),
            'message' => "Overstocked: {$item->getQuantityOnHand()} units (max: {$item->getMaxQuantity()})"
        ];
    }
    
    private function createOutOfStockAlert(InventoryItem $item): array
    {
        return [
            'type' => 'out_of_stock',
            'severity' => 'critical',
            'inventory_item_id' => $item->getId(),
            'product_name' => $item->getProduct()->getName(),
            'warehouse' => $item->getWarehouse()->getName(),
            'current_stock' => $item->getQuantityOnHand(),
            'reserved_stock' => $item->getQuantityReserved(),
            'message' => "Out of stock: {$item->getQuantityOnHand()} units available, {$item->getQuantityReserved()} reserved"
        ];
    }
    
    private function calculateSuggestedReorderQuantity(InventoryItem $item): int
    {
        // Algorithme basé sur la demande moyenne des 30 derniers jours
        $averageDemand = $this->calculateAverageDemand($item, 30);
        $leadTime = 7; // Délai de livraison en jours
        $safetyStock = $averageDemand * 3; // 3 jours de stock de sécurité
        
        $suggestedQuantity = ($averageDemand * $leadTime) + $safetyStock;
        
        // Respecter les contraintes min/max
        if ($item->getReorderQuantity() > 0) {
            $suggestedQuantity = max($suggestedQuantity, $item->getReorderQuantity());
        }
        
        if ($item->getMaxQuantity()) {
            $suggestedQuantity = min($suggestedQuantity, $item->getMaxQuantity() - $item->getQuantityOnHand());
        }
        
        return max(0, $suggestedQuantity);
    }
    
    private function calculateAverageDemand(InventoryItem $item, int $days): float
    {
        $from = new \DateTimeImmutable("-{$days} days");
        
        $movements = $this->em->getRepository(StockMovement::class)
                             ->createQueryBuilder()
                             ->select('SUM(sm.quantity)')
                             ->from('stock_movements', 'sm')
                             ->where('sm.inventory_item_id = ? AND sm.type = "out" AND sm.created_at >= ?')
                             ->setParameter(0, $item->getId())
                             ->setParameter(1, $from)
                             ->getQuery()
                             ->getSingleScalarResult();
        
        return ($movements ?? 0) / $days;
    }
    
    private function calculateReorderPriority(InventoryItem $item): int
    {
        $priority = 0;
        
        // Plus le stock est bas, plus la priorité est élevée
        $stockRatio = $item->getAvailableQuantity() / max(1, $item->getReorderPoint());
        $priority += (1 - min(1, $stockRatio)) * 50;
        
        // Produits avec plus de ventes récentes = priorité plus élevée
        $recentSales = $this->calculateRecentSalesVolume($item, 7);
        $priority += min(30, $recentSales);
        
        // Produits en rupture = priorité maximale
        if ($item->getAvailableQuantity() <= 0) {
            $priority += 100;
        }
        
        return (int) $priority;
    }
    
    private function sendAlertNotifications(array $alerts): void
    {
        $criticalAlerts = array_filter($alerts, fn($a) => $a['severity'] === 'critical');
        
        if (!empty($criticalAlerts)) {
            $this->notificationService->sendInventoryAlerts($criticalAlerts, 'critical');
        }
        
        $warningAlerts = array_filter($alerts, fn($a) => $a['severity'] === 'warning');
        
        if (count($warningAlerts) >= 5) { // Seuil pour les alertes groupées
            $this->notificationService->sendInventoryAlerts($warningAlerts, 'warning');
        }
    }
}
```

## API d'inventaire

### InventoryController

```php
<?php

namespace App\Controller\Api;

use App\Service\Inventory\InventoryService;
use App\Service\Inventory\InventoryAlertService;
use App\Entity\Catalog\Product;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/inventory')]
class InventoryController extends AbstractApiController
{
    private InventoryService $inventoryService;
    private InventoryAlertService $alertService;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        InventoryService $inventoryService,
        InventoryAlertService $alertService
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->inventoryService = $inventoryService;
        $this->alertService = $alertService;
    }
    
    #[Route('/stock/{productId}', methods: ['GET'])]
    public function getStock(int $productId, Request $request): JsonResponse
    {
        $product = $this->em->getRepository(Product::class)->find($productId);
        
        if (!$product) {
            return $this->createErrorResponse('Product not found', 404);
        }
        
        $warehouseId = $request->query->get('warehouse_id');
        $variantId = $request->query->get('variant_id');
        
        $variant = $variantId ? 
            $this->em->getRepository(ProductVariant::class)->find($variantId) : 
            null;
        
        $availableQuantity = $this->inventoryService->getAvailableQuantity(
            $product,
            $variant,
            $warehouseId ? (int) $warehouseId : null
        );
        
        return $this->jsonResponse([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'available_quantity' => $availableQuantity,
            'is_available' => $availableQuantity > 0
        ]);
    }
    
    #[Route('/movements/{productId}', methods: ['GET'])]
    public function getMovements(int $productId, Request $request): JsonResponse
    {
        $product = $this->em->getRepository(Product::class)->find($productId);
        
        if (!$product) {
            return $this->createErrorResponse('Product not found', 404);
        }
        
        $variantId = $request->query->get('variant_id');
        $warehouseId = $request->query->get('warehouse_id');
        $from = $request->query->get('from') ? new \DateTimeImmutable($request->query->get('from')) : null;
        $to = $request->query->get('to') ? new \DateTimeImmutable($request->query->get('to')) : null;
        
        $variant = $variantId ? 
            $this->em->getRepository(ProductVariant::class)->find($variantId) : 
            null;
        
        $movements = $this->inventoryService->getStockMovements(
            $product,
            $variant,
            $warehouseId ? (int) $warehouseId : null,
            $from,
            $to
        );
        
        return $this->jsonResponse([
            'movements' => array_map(function($movement) {
                return [
                    'id' => $movement->getId(),
                    'type' => $movement->getType(),
                    'quantity' => $movement->getQuantity(),
                    'quantity_before' => $movement->getQuantityBefore(),
                    'quantity_after' => $movement->getQuantityAfter(),
                    'reason' => $movement->getDisplayReason(),
                    'reference' => $movement->getReference(),
                    'created_at' => $movement->getCreatedAt()->format('Y-m-d H:i:s'),
                    'notes' => $movement->getNotes()
                ];
            }, $movements)
        ]);
    }
    
    #[Route('/alerts', methods: ['GET'])]
    public function getAlerts(Request $request): JsonResponse
    {
        $this->requireAdminAccess($request);
        
        $alerts = $this->alertService->checkStockLevels();
        $expiringAlerts = $this->alertService->checkExpiringProducts();
        
        return $this->jsonResponse([
            'stock_alerts' => $alerts,
            'expiry_alerts' => $expiringAlerts,
            'total_alerts' => count($alerts) + count($expiringAlerts)
        ]);
    }
    
    #[Route('/reorder-suggestions', methods: ['GET'])]
    public function getReorderSuggestions(Request $request): JsonResponse
    {
        $this->requireAdminAccess($request);
        
        $suggestions = $this->alertService->createReorderSuggestions();
        
        return $this->jsonResponse(['suggestions' => $suggestions]);
    }
    
    #[Route('/report', methods: ['GET'])]
    public function getStockReport(Request $request): JsonResponse
    {
        $this->requireAdminAccess($request);
        
        $warehouseId = $request->query->get('warehouse_id');
        
        $report = $this->alertService->generateStockReport(
            $warehouseId ? (int) $warehouseId : null
        );
        
        return $this->jsonResponse($report);
    }
}
```

---

Ce système complet de gestion d'inventaire démontre une architecture sophistiquée avec gestion multi-entrepôts, réservations temps réel, alertes automatiques, rapports détaillés et API complète pour une solution e-commerce de niveau professionnel.