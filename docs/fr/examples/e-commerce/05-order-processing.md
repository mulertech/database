# Traitement des Commandes - E-commerce

Cette section présente l'implémentation complète du système de traitement des commandes, démontrant la gestion des workflows, états, validations et intégrations avec MulerTech Database ORM.

## Table des matières

- [Workflow de commande](#workflow-de-commande)
- [Service de traitement](#service-de-traitement)
- [Gestion des états](#gestion-des-états)
- [Validation et vérifications](#validation-et-vérifications)
- [Réservation de stock](#réservation-de-stock)
- [Intégration paiement](#intégration-paiement)
- [Système de facturation](#système-de-facturation)
- [Notifications client](#notifications-client)
- [Gestion des retours](#gestion-des-retours)
- [API de commande](#api-de-commande)

## Workflow de commande

### OrderWorkflow - Machine à états

```php
<?php

namespace App\Service\Order;

use App\Entity\Order\Order;
use App\Enum\OrderStatus;
use Psr\Log\LoggerInterface;

class OrderWorkflow
{
    private LoggerInterface $logger;
    
    private array $transitions = [
        OrderStatus::PENDING->value => [
            OrderStatus::CONFIRMED->value,
            OrderStatus::CANCELLED->value
        ],
        OrderStatus::CONFIRMED->value => [
            OrderStatus::PROCESSING->value,
            OrderStatus::CANCELLED->value
        ],
        OrderStatus::PROCESSING->value => [
            OrderStatus::SHIPPED->value,
            OrderStatus::CANCELLED->value
        ],
        OrderStatus::SHIPPED->value => [
            OrderStatus::DELIVERED->value
        ],
        OrderStatus::DELIVERED->value => [
            OrderStatus::COMPLETED->value,
            OrderStatus::REFUNDED->value
        ],
        OrderStatus::COMPLETED->value => [
            OrderStatus::REFUNDED->value
        ]
    ];
    
    private array $guards = [
        OrderStatus::CONFIRMED->value => 'canConfirm',
        OrderStatus::PROCESSING->value => 'canProcess',
        OrderStatus::SHIPPED->value => 'canShip',
        OrderStatus::DELIVERED->value => 'canDeliver',
        OrderStatus::COMPLETED->value => 'canComplete',
        OrderStatus::CANCELLED->value => 'canCancel',
        OrderStatus::REFUNDED->value => 'canRefund'
    ];
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function canTransition(Order $order, OrderStatus $newStatus): bool
    {
        $currentStatus = $order->getStatus()->value;
        
        // Vérifier si la transition est autorisée
        if (!isset($this->transitions[$currentStatus]) || 
            !in_array($newStatus->value, $this->transitions[$currentStatus])) {
            return false;
        }
        
        // Vérifier les conditions spécifiques
        if (isset($this->guards[$newStatus->value])) {
            $guardMethod = $this->guards[$newStatus->value];
            return $this->$guardMethod($order);
        }
        
        return true;
    }
    
    public function transition(Order $order, OrderStatus $newStatus, string $reason = ''): bool
    {
        if (!$this->canTransition($order, $newStatus)) {
            $this->logger->warning('Order transition denied', [
                'order_id' => $order->getId(),
                'current_status' => $order->getStatus()->value,
                'target_status' => $newStatus->value,
                'reason' => $reason
            ]);
            return false;
        }
        
        $oldStatus = $order->getStatus();
        $order->setStatus($newStatus);
        
        // Actions spécifiques selon le nouveau statut
        $this->executeTransitionActions($order, $oldStatus, $newStatus);
        
        $this->logger->info('Order status changed', [
            'order_id' => $order->getId(),
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'reason' => $reason
        ]);
        
        return true;
    }
    
    public function getAvailableTransitions(Order $order): array
    {
        $currentStatus = $order->getStatus()->value;
        
        if (!isset($this->transitions[$currentStatus])) {
            return [];
        }
        
        $availableTransitions = [];
        
        foreach ($this->transitions[$currentStatus] as $statusValue) {
            $status = OrderStatus::from($statusValue);
            if ($this->canTransition($order, $status)) {
                $availableTransitions[] = $status;
            }
        }
        
        return $availableTransitions;
    }
    
    private function canConfirm(Order $order): bool
    {
        // Vérifier que tous les produits sont disponibles
        foreach ($order->getItems() as $item) {
            if (!$this->isProductAvailable($item)) {
                return false;
            }
        }
        
        // Vérifier l'adresse de livraison
        if (empty($order->getShippingAddress())) {
            return false;
        }
        
        return true;
    }
    
    private function canProcess(Order $order): bool
    {
        // Doit être confirmée et payée
        $payment = $order->getPayment();
        return $payment && $payment->isCompleted();
    }
    
    private function canShip(Order $order): bool
    {
        // Doit être en cours de traitement et stock réservé
        return $order->getStatus() === OrderStatus::PROCESSING;
    }
    
    private function canDeliver(Order $order): bool
    {
        // Doit être expédiée
        return $order->getStatus() === OrderStatus::SHIPPED;
    }
    
    private function canComplete(Order $order): bool
    {
        // Doit être livrée et délai de retour passé
        if ($order->getStatus() !== OrderStatus::DELIVERED) {
            return false;
        }
        
        $deliveredAt = $order->getShippedAt(); // En production, utiliser delivered_at
        if (!$deliveredAt) {
            return false;
        }
        
        $returnDeadline = $deliveredAt->modify('+14 days');
        return new \DateTimeImmutable() > $returnDeadline;
    }
    
    private function canCancel(Order $order): bool
    {
        return $order->canBeCancelled();
    }
    
    private function canRefund(Order $order): bool
    {
        return in_array($order->getStatus(), [OrderStatus::DELIVERED, OrderStatus::COMPLETED]);
    }
    
    private function executeTransitionActions(Order $order, OrderStatus $oldStatus, OrderStatus $newStatus): void
    {
        switch ($newStatus) {
            case OrderStatus::CONFIRMED:
                $this->onOrderConfirmed($order);
                break;
                
            case OrderStatus::PROCESSING:
                $this->onOrderProcessing($order);
                break;
                
            case OrderStatus::SHIPPED:
                $this->onOrderShipped($order);
                break;
                
            case OrderStatus::DELIVERED:
                $this->onOrderDelivered($order);
                break;
                
            case OrderStatus::COMPLETED:
                $this->onOrderCompleted($order);
                break;
                
            case OrderStatus::CANCELLED:
                $this->onOrderCancelled($order);
                break;
                
            case OrderStatus::REFUNDED:
                $this->onOrderRefunded($order);
                break;
        }
    }
    
    private function onOrderConfirmed(Order $order): void
    {
        // Réserver le stock
        // Envoyer email de confirmation
        // Créer les tâches de préparation
    }
    
    private function onOrderProcessing(Order $order): void
    {
        // Imprimer les étiquettes
        // Notifier l'entrepôt
    }
    
    private function onOrderShipped(Order $order): void
    {
        $order->setShippedAt(new \DateTimeImmutable());
        // Envoyer numéro de suivi
        // Mettre à jour le stock définitivement
    }
    
    private function onOrderDelivered(Order $order): void
    {
        // Envoyer email de livraison
        // Demander avis client
    }
    
    private function onOrderCompleted(Order $order): void
    {
        $order->setCompletedAt(new \DateTimeImmutable());
        // Traiter les points de fidélité
        // Archiver la commande
    }
    
    private function onOrderCancelled(Order $order): void
    {
        $order->setCancelledAt(new \DateTimeImmutable());
        // Libérer le stock réservé
        // Rembourser le paiement si nécessaire
    }
    
    private function onOrderRefunded(Order $order): void
    {
        // Traiter le remboursement
        // Gérer le retour de stock
    }
    
    private function isProductAvailable($item): bool
    {
        // Vérifier la disponibilité du produit/variant
        return true; // Simplification
    }
}
```

## Service de traitement

### OrderService

```php
<?php

namespace App\Service\Order;

use App\Entity\Cart\Cart;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Customer\Customer;
use App\Repository\Order\OrderRepository;
use App\Service\Cart\CartService;
use App\Service\Inventory\InventoryService;
use App\Service\Payment\PaymentService;
use App\Service\Notification\NotificationService;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class OrderService
{
    private EntityManager $em;
    private OrderRepository $orderRepository;
    private CartService $cartService;
    private InventoryService $inventoryService;
    private PaymentService $paymentService;
    private NotificationService $notificationService;
    private OrderWorkflow $orderWorkflow;
    private OrderNumberGenerator $orderNumberGenerator;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManager $em,
        OrderRepository $orderRepository,
        CartService $cartService,
        InventoryService $inventoryService,
        PaymentService $paymentService,
        NotificationService $notificationService,
        OrderWorkflow $orderWorkflow,
        OrderNumberGenerator $orderNumberGenerator,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->orderRepository = $orderRepository;
        $this->cartService = $cartService;
        $this->inventoryService = $inventoryService;
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
        $this->orderWorkflow = $orderWorkflow;
        $this->orderNumberGenerator = $orderNumberGenerator;
        $this->logger = $logger;
    }
    
    public function createOrderFromCart(
        Cart $cart, 
        array $shippingAddress,
        array $billingAddress = null,
        ?Customer $customer = null,
        ?string $guestEmail = null
    ): Order {
        // Validation du panier
        $issues = $this->cartService->validateCart($cart);
        if (!empty($issues)) {
            throw new \RuntimeException('Cart has issues that must be resolved first');
        }
        
        if ($cart->isEmpty()) {
            throw new \RuntimeException('Cannot create order from empty cart');
        }
        
        // Validation des adresses
        $this->validateAddress($shippingAddress, 'shipping');
        if ($billingAddress) {
            $this->validateAddress($billingAddress, 'billing');
        } else {
            $billingAddress = $shippingAddress;
        }
        
        try {
            $this->em->beginTransaction();
            
            // Créer la commande
            $order = new Order();
            $order->setNumber($this->orderNumberGenerator->generate())
                  ->setCustomerId($customer?->getId())
                  ->setCustomer($customer)
                  ->setGuestEmail($guestEmail)
                  ->setStatus(OrderStatus::PENDING)
                  ->setCurrencyCode($cart->getCurrencyCode())
                  ->setShippingAddress($shippingAddress)
                  ->setBillingAddress($billingAddress)
                  ->setCouponCode($cart->getCouponCode())
                  ->setDiscountAmount($cart->getDiscountAmount());
            
            $this->em->persist($order);
            
            // Créer les items de commande
            $subtotal = 0;
            foreach ($cart->getItems() as $cartItem) {
                $orderItem = new OrderItem();
                $orderItem->setOrder($order)
                          ->setProductId($cartItem->getProductId())
                          ->setProduct($cartItem->getProduct())
                          ->setVariantId($cartItem->getVariantId())
                          ->setVariant($cartItem->getVariant())
                          ->setQuantity($cartItem->getQuantity())
                          ->setUnitPrice($cartItem->getUnitPrice())
                          ->setTotalPrice($cartItem->getTotalPrice())
                          ->updateFromProduct($cartItem->getProduct(), $cartItem->getVariant());
                
                $this->em->persist($orderItem);
                $order->addItem($orderItem);
                
                $subtotal += $orderItem->getTotalPrice();
            }
            
            // Calculer les totaux
            $taxAmount = $this->calculateTax($subtotal, $customer, $shippingAddress);
            $shippingAmount = $this->calculateShipping($cart, $shippingAddress);
            
            $order->setSubtotal($subtotal)
                  ->setTaxAmount($taxAmount)
                  ->setShippingAmount($shippingAmount);
            
            $order->recalculateTotal();
            
            // Réserver le stock
            $this->reserveOrderStock($order);
            
            $this->em->flush();
            $this->em->commit();
            
            // Vider le panier
            $this->cartService->clearCart($cart);
            
            $this->logger->info('Order created successfully', [
                'order_id' => $order->getId(),
                'order_number' => $order->getNumber(),
                'customer_id' => $customer?->getId(),
                'total' => $order->getTotal()
            ]);
            
            // Notifications
            $this->notificationService->sendOrderCreatedNotification($order);
            
            return $order;
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Order creation failed', [
                'cart_id' => $cart->getId(),
                'customer_id' => $customer?->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to create order: ' . $e->getMessage());
        }
    }
    
    public function updateOrderStatus(Order $order, OrderStatus $newStatus, string $reason = ''): bool
    {
        if (!$this->orderWorkflow->canTransition($order, $newStatus)) {
            return false;
        }
        
        $this->orderWorkflow->transition($order, $newStatus, $reason);
        
        // Créer l'historique des statuts
        $this->createStatusHistory($order, $newStatus, $reason);
        
        $this->em->flush();
        
        // Notifications selon le nouveau statut
        $this->handleStatusChangeNotifications($order, $newStatus);
        
        return true;
    }
    
    public function confirmOrder(Order $order): bool
    {
        // Vérifications avant confirmation
        if (!$this->canConfirmOrder($order)) {
            return false;
        }
        
        return $this->updateOrderStatus($order, OrderStatus::CONFIRMED, 'Order confirmed after validation');
    }
    
    public function processOrder(Order $order): bool
    {
        // Vérifier le paiement
        $payment = $order->getPayment();
        if (!$payment || !$payment->isCompleted()) {
            return false;
        }
        
        return $this->updateOrderStatus($order, OrderStatus::PROCESSING, 'Payment confirmed, ready for fulfillment');
    }
    
    public function shipOrder(Order $order, array $trackingInfo = []): bool
    {
        if (!$this->orderWorkflow->canTransition($order, OrderStatus::SHIPPED)) {
            return false;
        }
        
        // Créer l'expédition
        $shipment = $this->createShipment($order, $trackingInfo);
        
        // Mettre à jour le statut
        $success = $this->updateOrderStatus($order, OrderStatus::SHIPPED, 'Order shipped');
        
        if ($success) {
            // Confirmer définitivement la sortie de stock
            $this->confirmOrderStock($order);
            
            // Envoyer les informations de suivi
            $this->notificationService->sendTrackingNotification($order, $shipment);
        }
        
        return $success;
    }
    
    public function cancelOrder(Order $order, string $reason = ''): bool
    {
        if (!$this->orderWorkflow->canTransition($order, OrderStatus::CANCELLED)) {
            return false;
        }
        
        try {
            $this->em->beginTransaction();
            
            // Libérer le stock réservé
            $this->releaseOrderStock($order);
            
            // Annuler/rembourser le paiement si nécessaire
            $payment = $order->getPayment();
            if ($payment && $payment->isCompleted()) {
                $this->paymentService->refund($payment, $order->getTotal(), 'Order cancellation');
            }
            
            // Mettre à jour le statut
            $this->updateOrderStatus($order, OrderStatus::CANCELLED, $reason);
            
            $this->em->commit();
            
            $this->logger->info('Order cancelled', [
                'order_id' => $order->getId(),
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Order cancellation failed', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    public function refundOrder(Order $order, int $amount = null, string $reason = ''): bool
    {
        $payment = $order->getPayment();
        if (!$payment || !$payment->isCompleted()) {
            return false;
        }
        
        $refundAmount = $amount ?? $order->getTotal();
        
        try {
            $this->em->beginTransaction();
            
            // Traiter le remboursement
            $refund = $this->paymentService->refund($payment, $refundAmount, $reason);
            
            if ($refund && $refund->isSuccessful()) {
                // Si remboursement total, changer le statut
                if ($refundAmount >= $order->getTotal()) {
                    $this->updateOrderStatus($order, OrderStatus::REFUNDED, $reason);
                }
                
                $this->em->commit();
                
                $this->logger->info('Order refunded', [
                    'order_id' => $order->getId(),
                    'refund_amount' => $refundAmount,
                    'reason' => $reason
                ]);
                
                return true;
            }
            
            $this->em->rollback();
            return false;
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Order refund failed', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    public function getOrderSummary(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'number' => $order->getNumber(),
            'status' => [
                'value' => $order->getStatus()->value,
                'label' => $order->getStatus()->getLabel(),
                'color' => $order->getStatus()->getColor()
            ],
            'customer' => $order->getCustomer() ? [
                'id' => $order->getCustomer()->getId(),
                'name' => $order->getCustomer()->getFullName(),
                'email' => $order->getCustomer()->getEmail()
            ] : [
                'email' => $order->getGuestEmail(),
                'type' => 'guest'
            ],
            'dates' => [
                'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'shipped_at' => $order->getShippedAt()?->format('Y-m-d H:i:s'),
                'completed_at' => $order->getCompletedAt()?->format('Y-m-d H:i:s')
            ],
            'items' => array_map(fn($item) => [
                'id' => $item->getId(),
                'product_name' => $item->getDisplayName(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'total_price' => $item->getTotalPrice(),
                'formatted_unit_price' => $this->formatPrice($item->getUnitPrice()),
                'formatted_total_price' => $this->formatPrice($item->getTotalPrice())
            ], $order->getItems()),
            'totals' => [
                'subtotal' => $order->getSubtotal(),
                'tax_amount' => $order->getTaxAmount(),
                'shipping_amount' => $order->getShippingAmount(),
                'discount_amount' => $order->getDiscountAmount(),
                'total' => $order->getTotal(),
                'formatted_subtotal' => $this->formatPrice($order->getSubtotal()),
                'formatted_tax_amount' => $this->formatPrice($order->getTaxAmount()),
                'formatted_shipping_amount' => $this->formatPrice($order->getShippingAmount()),
                'formatted_discount_amount' => $this->formatPrice($order->getDiscountAmount()),
                'formatted_total' => $this->formatPrice($order->getTotal())
            ],
            'addresses' => [
                'shipping' => $order->getShippingAddress(),
                'billing' => $order->getBillingAddress()
            ],
            'payment' => $order->getPayment() ? [
                'method' => $order->getPayment()->getMethod(),
                'status' => $order->getPayment()->getStatus(),
                'amount' => $order->getPayment()->getAmount(),
                'formatted_amount' => $this->formatPrice($order->getPayment()->getAmount())
            ] : null,
            'available_actions' => $this->getAvailableActions($order)
        ];
    }
    
    private function validateAddress(array $address, string $type): void
    {
        $required = ['first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country_code'];
        
        foreach ($required as $field) {
            if (empty($address[$field])) {
                throw new \InvalidArgumentException("Missing required {$type} address field: {$field}");
            }
        }
    }
    
    private function canConfirmOrder(Order $order): bool
    {
        // Vérifier que tous les produits sont encore disponibles
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();
            
            if (!$product || !$product->getIsActive()) {
                return false;
            }
            
            if ($variant && !$variant->getIsActive()) {
                return false;
            }
            
            // Vérifier le stock
            if (!$this->inventoryService->isAvailable($product, $variant, $item->getQuantity())) {
                return false;
            }
        }
        
        return true;
    }
    
    private function reserveOrderStock(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $this->inventoryService->reserve(
                $item->getProduct(),
                $item->getVariant(),
                $item->getQuantity(),
                "Order #{$order->getNumber()}"
            );
        }
    }
    
    private function releaseOrderStock(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $this->inventoryService->release(
                $item->getProduct(),
                $item->getVariant(),
                $item->getQuantity()
            );
        }
    }
    
    private function confirmOrderStock(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $this->inventoryService->confirm(
                $item->getProduct(),
                $item->getVariant(),
                $item->getQuantity()
            );
        }
    }
    
    private function calculateTax(int $subtotal, ?Customer $customer, array $shippingAddress): int
    {
        // Implémentation simple - en production, utiliser un service de calcul de taxes
        $taxRate = $this->getTaxRate($shippingAddress['country_code']);
        return (int) ($subtotal * $taxRate);
    }
    
    private function calculateShipping(Cart $cart, array $shippingAddress): int
    {
        // Implémentation simple - en production, utiliser un service de calcul de transport
        if ($cart->getSubtotal() >= 5000) { // 50€
            return 0; // Livraison gratuite
        }
        
        return 590; // 5.90€
    }
    
    private function getTaxRate(string $countryCode): float
    {
        // Taux de TVA par pays (exemple)
        $taxRates = [
            'FR' => 0.20,
            'DE' => 0.19,
            'BE' => 0.21,
            'ES' => 0.21,
            'IT' => 0.22
        ];
        
        return $taxRates[$countryCode] ?? 0.20;
    }
    
    private function createStatusHistory(Order $order, OrderStatus $status, string $comment): void
    {
        $history = new OrderStatusHistory();
        $history->setOrder($order)
                ->setStatus($status)
                ->setComment($comment)
                ->setCreatedAt(new \DateTimeImmutable());
        
        $this->em->persist($history);
    }
    
    private function createShipment(Order $order, array $trackingInfo): Shipment
    {
        $shipment = new Shipment();
        $shipment->setOrder($order)
                 ->setTrackingNumber($trackingInfo['tracking_number'] ?? '')
                 ->setCarrier($trackingInfo['carrier'] ?? '')
                 ->setShippedAt(new \DateTimeImmutable());
        
        $this->em->persist($shipment);
        
        return $shipment;
    }
    
    private function handleStatusChangeNotifications(Order $order, OrderStatus $newStatus): void
    {
        switch ($newStatus) {
            case OrderStatus::CONFIRMED:
                $this->notificationService->sendOrderConfirmedNotification($order);
                break;
                
            case OrderStatus::SHIPPED:
                $this->notificationService->sendOrderShippedNotification($order);
                break;
                
            case OrderStatus::DELIVERED:
                $this->notificationService->sendOrderDeliveredNotification($order);
                break;
                
            case OrderStatus::CANCELLED:
                $this->notificationService->sendOrderCancelledNotification($order);
                break;
        }
    }
    
    private function getAvailableActions(Order $order): array
    {
        $actions = [];
        $availableTransitions = $this->orderWorkflow->getAvailableTransitions($order);
        
        foreach ($availableTransitions as $transition) {
            $actions[] = [
                'action' => strtolower($transition->value),
                'label' => $this->getActionLabel($transition),
                'confirmRequired' => $this->requiresConfirmation($transition)
            ];
        }
        
        return $actions;
    }
    
    private function getActionLabel(OrderStatus $status): string
    {
        return match($status) {
            OrderStatus::CONFIRMED => 'Confirmer',
            OrderStatus::PROCESSING => 'Traiter',
            OrderStatus::SHIPPED => 'Expédier',
            OrderStatus::DELIVERED => 'Marquer comme livrée',
            OrderStatus::COMPLETED => 'Terminer',
            OrderStatus::CANCELLED => 'Annuler',
            OrderStatus::REFUNDED => 'Rembourser',
            default => $status->getLabel()
        };
    }
    
    private function requiresConfirmation(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED]);
    }
    
    private function formatPrice(int $priceInCents): string
    {
        return number_format($priceInCents / 100, 2, ',', ' ') . ' €';
    }
}
```

## Gestion des états

### OrderStatusHistory - Historique des statuts

```php
<?php

namespace App\Entity\Order;

use App\Entity\BaseEntity;
use App\Enum\OrderStatus;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\ColumnKey;

#[MtEntity(table: 'order_status_history')]
class OrderStatusHistory extends BaseEntity
{
    #[MtColumn(columnType: ColumnType::INT, columnKey: ColumnKey::PRIMARY_KEY, extra: 'AUTO_INCREMENT')]
    private int $id;
    
    #[MtColumn(columnName: 'order_id', columnType: ColumnType::INT)]
    private int $orderId;
    
    #[MtColumn(columnType: ColumnType::ENUM, choices: OrderStatus::class)]
    private OrderStatus $status;
    
    #[MtColumn(columnType: ColumnType::TEXT, isNullable: true)]
    private ?string $comment = null;
    
    #[MtColumn(columnName: 'created_by', columnType: ColumnType::INT, isNullable: true)]
    private ?int $createdBy = null; // Admin user qui a fait le changement
    
    #[MtColumn(columnName: 'is_customer_notified', columnType: ColumnType::TINYINT, columnDefault: '0')]
    private bool $isCustomerNotified = false;
    
    #[MtColumn(columnName: 'notification_sent_at', columnType: ColumnType::TIMESTAMP, isNullable: true)]
    private ?\DateTimeInterface $notificationSentAt = null;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Order::class, inversedBy: 'statusHistory')]
    private ?Order $order = null;
    
    // Getters et setters...
}
```

### OrderRepository avec requêtes métier

```php
<?php

namespace App\Repository\Order;

use App\Entity\Order\Order;
use App\Entity\Customer\Customer;
use App\Enum\OrderStatus;
use MulerTech\Database\ORM\Repository\EntityRepository;

class OrderRepository extends EntityRepository
{
    protected function getEntityClass(): string
    {
        return Order::class;
    }
    
    public function findByCustomer(Customer $customer, int $limit = 50): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('customer_id = ?')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->setParameter(0, $customer->getId())
            ->getQuery()
            ->getResult();
    }
    
    public function findByStatus(OrderStatus $status): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('status = ?')
            ->orderBy('created_at', 'ASC')
            ->setParameter(0, $status->value)
            ->getQuery()
            ->getResult();
    }
    
    public function findPendingOrders(\DateTimeInterface $olderThan = null): array
    {
        $qb = $this->createQueryBuilder()
                  ->select('*')
                  ->where('status = ?')
                  ->setParameter(0, OrderStatus::PENDING->value);
        
        if ($olderThan) {
            $qb->andWhere('created_at < ?')
               ->setParameter(1, $olderThan);
        }
        
        return $qb->orderBy('created_at', 'ASC')
                 ->getQuery()
                 ->getResult();
    }
    
    public function findOrdersRequiringAction(): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('status IN (?, ?)')
            ->orderBy('created_at', 'ASC')
            ->setParameter(0, OrderStatus::CONFIRMED->value)
            ->setParameter(1, OrderStatus::PROCESSING->value)
            ->getQuery()
            ->getResult();
    }
    
    public function getOrderStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('
                status,
                COUNT(*) as count,
                SUM(total) as total_amount,
                AVG(total) as average_amount
            ')
            ->from('orders')
            ->where('created_at >= ? AND created_at <= ?')
            ->groupBy('status')
            ->setParameter(0, $from)
            ->setParameter(1, $to)
            ->getQuery()
            ->getResult();
    }
    
    public function findRecentOrders(int $days = 7, int $limit = 100): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->where('created_at >= ?')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->setParameter(0, new \DateTimeImmutable("-{$days} days"))
            ->getQuery()
            ->getResult();
    }
    
    public function findByNumberOrEmail(string $search): array
    {
        return $this->em->createQueryBuilder()
            ->select('o.*, c.first_name, c.last_name, c.email')
            ->from('orders', 'o')
            ->leftJoin('customers', 'c', 'c.id = o.customer_id')
            ->where('o.number LIKE ? OR c.email LIKE ? OR o.guest_email LIKE ?')
            ->orderBy('o.created_at', 'DESC')
            ->limit(50)
            ->setParameter(0, '%' . $search . '%')
            ->setParameter(1, '%' . $search . '%')
            ->setParameter(2, '%' . $search . '%')
            ->getQuery()
            ->getResult();
    }
    
    public function getMonthlyRevenue(int $year): array
    {
        return $this->em->createQueryBuilder()
            ->select('
                MONTH(created_at) as month,
                COUNT(*) as orders_count,
                SUM(total) as revenue
            ')
            ->from('orders')
            ->where('YEAR(created_at) = ? AND status IN (?, ?, ?)')
            ->groupBy('MONTH(created_at)')
            ->orderBy('month')
            ->setParameter(0, $year)
            ->setParameter(1, OrderStatus::COMPLETED->value)
            ->setParameter(2, OrderStatus::DELIVERED->value)
            ->setParameter(3, OrderStatus::SHIPPED->value)
            ->getQuery()
            ->getResult();
    }
}
```

## Système de facturation

### InvoiceService

```php
<?php

namespace App\Service\Order;

use App\Entity\Order\Order;
use App\Entity\Order\Invoice;

class InvoiceService
{
    private EntityManager $em;
    private InvoiceNumberGenerator $numberGenerator;
    private PdfGenerator $pdfGenerator;
    
    public function __construct(
        EntityManager $em,
        InvoiceNumberGenerator $numberGenerator,
        PdfGenerator $pdfGenerator
    ) {
        $this->em = $em;
        $this->numberGenerator = $numberGenerator;
        $this->pdfGenerator = $pdfGenerator;
    }
    
    public function generateInvoice(Order $order): Invoice
    {
        // Vérifier que la commande peut avoir une facture
        if (!$this->canGenerateInvoice($order)) {
            throw new \RuntimeException('Cannot generate invoice for this order');
        }
        
        // Vérifier s'il existe déjà une facture
        $existingInvoice = $this->em->getRepository(Invoice::class)
                                   ->findByOrder($order);
        
        if ($existingInvoice) {
            return $existingInvoice;
        }
        
        $invoice = new Invoice();
        $invoice->setOrder($order)
                ->setNumber($this->numberGenerator->generate())
                ->setAmount($order->getTotal())
                ->setTaxAmount($order->getTaxAmount())
                ->setIssuedAt(new \DateTimeImmutable())
                ->setDueAt(new \DateTimeImmutable('+30 days'));
        
        // Copier les données pour archive
        $invoice->setCustomerData([
            'name' => $order->getCustomer()?->getFullName() ?? 'Guest',
            'email' => $order->getCustomerEmail(),
            'billing_address' => $order->getBillingAddress()
        ]);
        
        $invoice->setItemsData(
            array_map(fn($item) => [
                'name' => $item->getDisplayName(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'total_price' => $item->getTotalPrice()
            ], $order->getItems())
        );
        
        $this->em->persist($invoice);
        $this->em->flush();
        
        // Générer le PDF
        $this->generateInvoicePdf($invoice);
        
        return $invoice;
    }
    
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $pdfPath = $this->pdfGenerator->generateInvoicePdf($invoice);
        
        $invoice->setPdfPath($pdfPath);
        $this->em->flush();
        
        return $pdfPath;
    }
    
    private function canGenerateInvoice(Order $order): bool
    {
        return in_array($order->getStatus(), [
            OrderStatus::CONFIRMED,
            OrderStatus::PROCESSING,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED,
            OrderStatus::COMPLETED
        ]);
    }
}
```

## API de commande

### OrderController

```php
<?php

namespace App\Controller\Api;

use App\Entity\Order\Order;
use App\Service\Order\OrderService;
use App\Service\Cart\CartService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/orders')]
class OrderController extends AbstractApiController
{
    private OrderService $orderService;
    private CartService $cartService;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        OrderService $orderService,
        CartService $cartService
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->orderService = $orderService;
        $this->cartService = $cartService;
    }
    
    #[Route('', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validation des données requises
        $required = ['shipping_address'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->createErrorResponse("Missing required field: {$field}", 400);
            }
        }
        
        $customer = $this->getCurrentCustomer($request);
        
        // Récupérer le panier actuel
        $cart = $this->cartService->getCurrentCart($customer);
        
        if ($cart->isEmpty()) {
            return $this->createErrorResponse('Cart is empty', 400);
        }
        
        try {
            $order = $this->orderService->createOrderFromCart(
                $cart,
                $data['shipping_address'],
                $data['billing_address'] ?? null,
                $customer,
                $data['guest_email'] ?? null
            );
            
            $orderSummary = $this->orderService->getOrderSummary($order);
            
            return $this->jsonResponse($orderSummary, 201);
            
        } catch (\RuntimeException $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
    
    #[Route('', methods: ['GET'])]
    public function getOrders(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        
        if (!$customer) {
            return $this->createErrorResponse('Authentication required', 401);
        }
        
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(10, (int) $request->query->get('limit', 20)));
        
        $orders = $this->em->getRepository(Order::class)
                          ->findByCustomer($customer, $limit);
        
        $orderSummaries = array_map(
            fn($order) => $this->orderService->getOrderSummary($order),
            $orders
        );
        
        return $this->jsonResponse([
            'orders' => $orderSummaries,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => count($orders)
            ]
        ]);
    }
    
    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOrder(int $id, Request $request): JsonResponse
    {
        $order = $this->em->getRepository(Order::class)->find($id);
        
        if (!$order) {
            return $this->createErrorResponse('Order not found', 404);
        }
        
        $customer = $this->getCurrentCustomer($request);
        
        // Vérifier les permissions
        if (!$customer || $order->getCustomerId() !== $customer->getId()) {
            return $this->createErrorResponse('Access denied', 403);
        }
        
        $orderSummary = $this->orderService->getOrderSummary($order);
        
        return $this->jsonResponse($orderSummary);
    }
    
    #[Route('/{id}/cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancelOrder(int $id, Request $request): JsonResponse
    {
        $order = $this->em->getRepository(Order::class)->find($id);
        
        if (!$order) {
            return $this->createErrorResponse('Order not found', 404);
        }
        
        $customer = $this->getCurrentCustomer($request);
        
        // Vérifier les permissions
        if (!$customer || $order->getCustomerId() !== $customer->getId()) {
            return $this->createErrorResponse('Access denied', 403);
        }
        
        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'Cancelled by customer';
        
        $success = $this->orderService->cancelOrder($order, $reason);
        
        if (!$success) {
            return $this->createErrorResponse('Cannot cancel this order', 400);
        }
        
        return $this->jsonResponse([
            'message' => 'Order cancelled successfully',
            'order' => $this->orderService->getOrderSummary($order)
        ]);
    }
    
    #[Route('/by-number/{number}', methods: ['GET'])]
    public function getOrderByNumber(string $number, Request $request): JsonResponse
    {
        $order = $this->em->getRepository(Order::class)->findByNumber($number);
        
        if (!$order) {
            return $this->createErrorResponse('Order not found', 404);
        }
        
        // Pour les invités, permettre l'accès avec email
        $customer = $this->getCurrentCustomer($request);
        $guestEmail = $request->query->get('email');
        
        if (!$customer && (!$guestEmail || $order->getGuestEmail() !== $guestEmail)) {
            return $this->createErrorResponse('Access denied', 403);
        }
        
        if ($customer && $order->getCustomerId() !== $customer->getId()) {
            return $this->createErrorResponse('Access denied', 403);
        }
        
        $orderSummary = $this->orderService->getOrderSummary($order);
        
        return $this->jsonResponse($orderSummary);
    }
}
```

---

Ce système de traitement des commandes complet démontre une architecture robuste avec workflow d'états, gestion des stocks, intégrations de paiement, système de facturation et API REST complète pour une expérience e-commerce moderne et fiable.
