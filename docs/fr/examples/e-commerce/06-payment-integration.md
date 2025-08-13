# Intégration des Paiements - E-commerce

Cette section présente l'implémentation complète du système de paiement multi-providers, démontrant l'intégration avec Stripe, PayPal et autres passerelles de paiement avec MulerTech Database ORM.

## Table des matières

- [Architecture des paiements](#architecture-des-paiements)
- [Entités de paiement](#entités-de-paiement)
- [Service de paiement abstrait](#service-de-paiement-abstrait)
- [Intégration Stripe](#intégration-stripe)
- [Intégration PayPal](#intégration-paypal)
- [Gestion des webhooks](#gestion-des-webhooks)
- [Remboursements](#remboursements)
- [Sécurité et conformité](#sécurité-et-conformité)
- [Récurrence et abonnements](#récurrence-et-abonnements)
- [API de paiement](#api-de-paiement)

## Architecture des paiements

### Schéma des entités de paiement

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  payments   │    │   refunds   │    │ transactions│
├─────────────┤    ├─────────────┤    ├─────────────┤
│ id (PK)     │◄──┐│ id (PK)     │    │ id (PK)     │
│ order_id    │   ││ payment_id  │    │ payment_id  │
│ amount      │   ││ amount      │    │ type        │
│ method      │   ││ reason      │    │ status      │
│ status      │   ││ status      │    │ amount      │
│ gateway     │   ││ gateway_ref │    │ gateway_ref │
│ gateway_ref │   ││ processed_at│    │ raw_data    │
│ metadata    │   │└─────────────┘    │ created_at  │
│ processed_at│   │                   └─────────────┘
└─────────────┘   │
       │          │
       └──────────┘

┌─────────────┐    ┌─────────────┐
│payment_methods│  │ webhooks    │
├─────────────┤    ├─────────────┤
│ id (PK)     │    │ id (PK)     │
│ customer_id │    │ provider    │
│ type        │    │ event_type  │
│ provider    │    │ event_id    │
│ token       │    │ payload     │
│ last_4      │    │ status      │
│ expires_at  │    │ processed_at│
│ is_default  │    │ created_at  │
└─────────────┘    └─────────────┘
```

## Entités de paiement

### Payment - Paiement principal

```php
<?php

namespace App\Entity\Payment;

use App\Entity\BaseEntity;
use App\Entity\Order\Order;
use App\Type\MoneyType;
use App\Enum\PaymentStatus;
use App\Enum\PaymentMethod;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'payments')]
class Payment extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'order_id', type: 'int')]
    private int $orderId;
    
    #[MtColumn(type: MoneyType::class)]
    private int $amount;
    
    #[MtColumn(type: 'enum', values: PaymentMethod::class)]
    private PaymentMethod $method;
    
    #[MtColumn(type: 'enum', values: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;
    
    #[MtColumn(type: 'varchar', length: 50)]
    private string $gateway; // stripe, paypal, bank_transfer
    
    #[MtColumn(name: 'gateway_id', type: 'varchar', length: 255, nullable: true)]
    private ?string $gatewayId = null; // ID chez le provider
    
    #[MtColumn(name: 'gateway_reference', type: 'varchar', length: 255, nullable: true)]
    private ?string $gatewayReference = null;
    
    #[MtColumn(name: 'currency_code', type: 'varchar', length: 3, default: 'EUR')]
    private string $currencyCode = 'EUR';
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $metadata = null; // Données spécifiques au gateway
    
    #[MtColumn(name: 'failure_reason', type: 'text', nullable: true)]
    private ?string $failureReason = null;
    
    #[MtColumn(name: 'processed_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $processedAt = null;
    
    #[MtColumn(name: 'expires_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;
    
    // Relations
    #[MtRelation('OneToOne', targetEntity: Order::class, inversedBy: 'payment')]
    private ?Order $order = null;
    
    #[MtRelation('OneToMany', targetEntity: PaymentTransaction::class, mappedBy: 'payment')]
    private array $transactions = [];
    
    #[MtRelation('OneToMany', targetEntity: Refund::class, mappedBy: 'payment')]
    private array $refunds = [];
    
    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }
    
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }
    
    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }
    
    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }
    
    public function canBeRefunded(): bool
    {
        if (!$this->isCompleted()) {
            return false;
        }
        
        $totalRefunded = $this->getTotalRefunded();
        return $totalRefunded < $this->amount;
    }
    
    public function getTotalRefunded(): int
    {
        $total = 0;
        foreach ($this->refunds as $refund) {
            if ($refund->getStatus() === RefundStatus::COMPLETED) {
                $total += $refund->getAmount();
            }
        }
        return $total;
    }
    
    public function getRemainingRefundableAmount(): int
    {
        return $this->amount - $this->getTotalRefunded();
    }
    
    public function addTransaction(PaymentTransaction $transaction): self
    {
        $transaction->setPayment($this);
        $this->transactions[] = $transaction;
        return $this;
    }
    
    // Getters et setters...
}
```

### PaymentMethod - Méthodes de paiement sauvegardées

```php
<?php

namespace App\Entity\Payment;

use App\Entity\BaseEntity;
use App\Entity\Customer\Customer;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'payment_methods')]
class PaymentMethod extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'customer_id', type: 'int')]
    private int $customerId;
    
    #[MtColumn(type: 'varchar', length: 50)]
    private string $type; // card, paypal, bank_account
    
    #[MtColumn(type: 'varchar', length: 50)]
    private string $provider; // stripe, paypal
    
    #[MtColumn(name: 'provider_id', type: 'varchar', length: 255)]
    private string $providerId; // ID chez le provider
    
    #[MtColumn(name: 'display_name', type: 'varchar', length: 100)]
    private string $displayName; // "Visa ****1234"
    
    #[MtColumn(name: 'last_four', type: 'varchar', length: 4, nullable: true)]
    private ?string $lastFour = null;
    
    #[MtColumn(name: 'card_brand', type: 'varchar', length: 20, nullable: true)]
    private ?string $cardBrand = null; // visa, mastercard, amex
    
    #[MtColumn(name: 'expires_month', type: 'int', nullable: true)]
    private ?int $expiresMonth = null;
    
    #[MtColumn(name: 'expires_year', type: 'int', nullable: true)]
    private ?int $expiresYear = null;
    
    #[MtColumn(name: 'is_default', type: 'boolean', default: false)]
    private bool $isDefault = false;
    
    #[MtColumn(name: 'is_active', type: 'boolean', default: true)]
    private bool $isActive = true;
    
    #[MtColumn(name: 'last_used_at', type: 'timestamp', nullable: true)]
    private ?\DateTimeInterface $lastUsedAt = null;
    
    // Relations
    #[MtRelation('ManyToOne', targetEntity: Customer::class)]
    private ?Customer $customer = null;
    
    public function isExpired(): bool
    {
        if (!$this->expiresMonth || !$this->expiresYear) {
            return false;
        }
        
        $now = new \DateTimeImmutable();
        $expiryDate = new \DateTimeImmutable("{$this->expiresYear}-{$this->expiresMonth}-01");
        $expiryDate = $expiryDate->modify('last day of this month');
        
        return $now > $expiryDate;
    }
    
    public function getDisplayInfo(): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName,
            'type' => $this->type,
            'brand' => $this->cardBrand,
            'last_four' => $this->lastFour,
            'expires' => $this->expiresMonth && $this->expiresYear ? 
                sprintf('%02d/%d', $this->expiresMonth, $this->expiresYear) : null,
            'is_default' => $this->isDefault,
            'is_expired' => $this->isExpired()
        ];
    }
    
    // Getters et setters...
}
```

## Service de paiement abstrait

### PaymentService - Service principal

```php
<?php

namespace App\Service\Payment;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Customer\Customer;
use App\Enum\PaymentStatus;
use App\Service\Payment\Provider\PaymentProviderInterface;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class PaymentService
{
    private EntityManager $em;
    private array $providers = [];
    private LoggerInterface $logger;
    
    public function __construct(EntityManager $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
    }
    
    public function addProvider(string $name, PaymentProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }
    
    public function createPayment(Order $order, string $gateway, array $paymentData = []): Payment
    {
        if (!isset($this->providers[$gateway])) {
            throw new \InvalidArgumentException("Payment provider '{$gateway}' not found");
        }
        
        $payment = new Payment();
        $payment->setOrderId($order->getId())
                ->setOrder($order)
                ->setAmount($order->getTotal())
                ->setMethod($paymentData['method'] ?? PaymentMethod::CARD)
                ->setGateway($gateway)
                ->setCurrencyCode($order->getCurrencyCode())
                ->setStatus(PaymentStatus::PENDING)
                ->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        
        if (isset($paymentData['metadata'])) {
            $payment->setMetadata($paymentData['metadata']);
        }
        
        $this->em->persist($payment);
        $this->em->flush();
        
        $this->logger->info('Payment created', [
            'payment_id' => $payment->getId(),
            'order_id' => $order->getId(),
            'amount' => $payment->getAmount(),
            'gateway' => $gateway
        ]);
        
        return $payment;
    }
    
    public function processPayment(Payment $payment, array $paymentData = []): PaymentResult
    {
        $provider = $this->getProvider($payment->getGateway());
        
        try {
            $this->logger->info('Processing payment', [
                'payment_id' => $payment->getId(),
                'gateway' => $payment->getGateway()
            ]);
            
            $result = $provider->processPayment($payment, $paymentData);
            
            // Mettre à jour le paiement selon le résultat
            $this->updatePaymentFromResult($payment, $result);
            
            $this->em->flush();
            
            $this->logger->info('Payment processed', [
                'payment_id' => $payment->getId(),
                'status' => $result->getStatus(),
                'success' => $result->isSuccessful()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $payment->setStatus(PaymentStatus::FAILED);
            $payment->setFailureReason($e->getMessage());
            
            $this->em->flush();
            
            $this->logger->error('Payment processing failed', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    public function createPaymentIntent(Order $order, string $gateway, array $options = []): array
    {
        $provider = $this->getProvider($gateway);
        
        // Créer le paiement en base
        $payment = $this->createPayment($order, $gateway, $options);
        
        // Créer l'intent chez le provider
        $intent = $provider->createPaymentIntent($payment, $options);
        
        // Sauvegarder l'ID de l'intent
        $payment->setGatewayId($intent['id']);
        $payment->setMetadata(array_merge($payment->getMetadata() ?? [], $intent));
        
        $this->em->flush();
        
        return [
            'payment_id' => $payment->getId(),
            'client_secret' => $intent['client_secret'],
            'intent_id' => $intent['id']
        ];
    }
    
    public function confirmPayment(Payment $payment, array $confirmationData = []): PaymentResult
    {
        $provider = $this->getProvider($payment->getGateway());
        
        $result = $provider->confirmPayment($payment, $confirmationData);
        
        $this->updatePaymentFromResult($payment, $result);
        $this->em->flush();
        
        return $result;
    }
    
    public function refund(Payment $payment, int $amount = null, string $reason = ''): Refund
    {
        if (!$payment->canBeRefunded()) {
            throw new \RuntimeException('Payment cannot be refunded');
        }
        
        $refundAmount = $amount ?? $payment->getRemainingRefundableAmount();
        
        if ($refundAmount > $payment->getRemainingRefundableAmount()) {
            throw new \InvalidArgumentException('Refund amount exceeds refundable amount');
        }
        
        $provider = $this->getProvider($payment->getGateway());
        
        // Créer l'entité remboursement
        $refund = new Refund();
        $refund->setPayment($payment)
               ->setAmount($refundAmount)
               ->setReason($reason)
               ->setStatus(RefundStatus::PENDING);
        
        $this->em->persist($refund);
        $this->em->flush();
        
        try {
            // Traiter le remboursement chez le provider
            $result = $provider->refund($payment, $refundAmount, $reason);
            
            $refund->setStatus($result->isSuccessful() ? RefundStatus::COMPLETED : RefundStatus::FAILED);
            $refund->setGatewayReference($result->getTransactionId());
            $refund->setProcessedAt(new \DateTimeImmutable());
            
            if (!$result->isSuccessful()) {
                $refund->setFailureReason($result->getErrorMessage());
            }
            
            $this->em->flush();
            
            $this->logger->info('Refund processed', [
                'payment_id' => $payment->getId(),
                'refund_id' => $refund->getId(),
                'amount' => $refundAmount,
                'success' => $result->isSuccessful()
            ]);
            
            return $refund;
            
        } catch (\Exception $e) {
            $refund->setStatus(RefundStatus::FAILED);
            $refund->setFailureReason($e->getMessage());
            
            $this->em->flush();
            
            $this->logger->error('Refund failed', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    public function savePaymentMethod(
        Customer $customer, 
        string $providerId, 
        string $provider, 
        array $methodData
    ): PaymentMethod {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCustomer($customer)
                     ->setCustomerId($customer->getId())
                     ->setProviderId($providerId)
                     ->setProvider($provider)
                     ->setType($methodData['type'])
                     ->setDisplayName($methodData['display_name'])
                     ->setLastFour($methodData['last_four'] ?? null)
                     ->setCardBrand($methodData['brand'] ?? null)
                     ->setExpiresMonth($methodData['exp_month'] ?? null)
                     ->setExpiresYear($methodData['exp_year'] ?? null);
        
        // Si c'est la première méthode, la marquer comme défaut
        $existingMethods = $this->em->getRepository(PaymentMethod::class)
                                  ->findByCustomer($customer);
        
        if (empty($existingMethods)) {
            $paymentMethod->setIsDefault(true);
        }
        
        $this->em->persist($paymentMethod);
        $this->em->flush();
        
        return $paymentMethod;
    }
    
    public function handleWebhook(string $provider, array $payload, array $headers = []): bool
    {
        if (!isset($this->providers[$provider])) {
            $this->logger->warning('Webhook received for unknown provider', ['provider' => $provider]);
            return false;
        }
        
        $providerInstance = $this->providers[$provider];
        
        // Vérifier la signature du webhook
        if (!$providerInstance->verifyWebhookSignature($payload, $headers)) {
            $this->logger->warning('Webhook signature verification failed', ['provider' => $provider]);
            return false;
        }
        
        // Traiter le webhook
        return $providerInstance->handleWebhook($payload);
    }
    
    private function getProvider(string $gateway): PaymentProviderInterface
    {
        if (!isset($this->providers[$gateway])) {
            throw new \InvalidArgumentException("Payment provider '{$gateway}' not found");
        }
        
        return $this->providers[$gateway];
    }
    
    private function updatePaymentFromResult(Payment $payment, PaymentResult $result): void
    {
        $payment->setStatus($result->getStatus());
        
        if ($result->getTransactionId()) {
            $payment->setGatewayReference($result->getTransactionId());
        }
        
        if ($result->isSuccessful()) {
            $payment->setProcessedAt(new \DateTimeImmutable());
        } elseif ($result->isFailed()) {
            $payment->setFailureReason($result->getErrorMessage());
        }
        
        // Créer une transaction pour tracer l'opération
        $transaction = new PaymentTransaction();
        $transaction->setPayment($payment)
                   ->setType($result->getTransactionType())
                   ->setStatus($result->getStatus())
                   ->setAmount($payment->getAmount())
                   ->setGatewayReference($result->getTransactionId())
                   ->setRawData($result->getRawData());
        
        $this->em->persist($transaction);
        $payment->addTransaction($transaction);
    }
}
```

## Intégration Stripe

### StripePaymentProvider

```php
<?php

namespace App\Service\Payment\Provider;

use App\Entity\Payment\Payment;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentResult;
use Stripe\StripeClient;
use Stripe\PaymentIntent;
use Stripe\Refund as StripeRefund;
use Psr\Log\LoggerInterface;

class StripePaymentProvider implements PaymentProviderInterface
{
    private StripeClient $stripe;
    private string $webhookSecret;
    private LoggerInterface $logger;
    
    public function __construct(string $secretKey, string $webhookSecret, LoggerInterface $logger)
    {
        $this->stripe = new StripeClient($secretKey);
        $this->webhookSecret = $webhookSecret;
        $this->logger = $logger;
    }
    
    public function createPaymentIntent(Payment $payment, array $options = []): array
    {
        $params = [
            'amount' => $payment->getAmount(),
            'currency' => strtolower($payment->getCurrencyCode()),
            'metadata' => [
                'payment_id' => $payment->getId(),
                'order_id' => $payment->getOrderId()
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ];
        
        // Customer existant
        if (isset($options['customer_id'])) {
            $params['customer'] = $options['customer_id'];
        }
        
        // Méthode de paiement spécifique
        if (isset($options['payment_method'])) {
            $params['payment_method'] = $options['payment_method'];
            $params['confirmation_method'] = 'manual';
            $params['confirm'] = true;
        }
        
        $intent = $this->stripe->paymentIntents->create($params);
        
        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status
        ];
    }
    
    public function processPayment(Payment $payment, array $paymentData = []): PaymentResult
    {
        try {
            if ($payment->getGatewayId()) {
                // Récupérer l'intent existant
                $intent = $this->stripe->paymentIntents->retrieve($payment->getGatewayId());
            } else {
                // Créer un nouvel intent
                $intentData = $this->createPaymentIntent($payment, $paymentData);
                $intent = $this->stripe->paymentIntents->retrieve($intentData['id']);
            }
            
            // Confirmer si nécessaire
            if ($intent->status === 'requires_confirmation') {
                $intent = $this->stripe->paymentIntents->confirm($intent->id, $paymentData);
            }
            
            return $this->createPaymentResultFromIntent($intent);
            
        } catch (\Stripe\Exception\CardException $e) {
            return new PaymentResult(
                PaymentStatus::FAILED,
                null,
                $e->getMessage(),
                'payment',
                ['stripe_error' => $e->jsonBody]
            );
        } catch (\Exception $e) {
            throw new \RuntimeException('Stripe payment processing failed: ' . $e->getMessage());
        }
    }
    
    public function confirmPayment(Payment $payment, array $confirmationData = []): PaymentResult
    {
        try {
            $intent = $this->stripe->paymentIntents->confirm(
                $payment->getGatewayId(),
                $confirmationData
            );
            
            return $this->createPaymentResultFromIntent($intent);
            
        } catch (\Stripe\Exception\CardException $e) {
            return new PaymentResult(
                PaymentStatus::FAILED,
                null,
                $e->getMessage(),
                'confirmation',
                ['stripe_error' => $e->jsonBody]
            );
        }
    }
    
    public function refund(Payment $payment, int $amount, string $reason = ''): PaymentResult
    {
        try {
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $payment->getGatewayReference(),
                'amount' => $amount,
                'reason' => $this->mapRefundReason($reason),
                'metadata' => [
                    'payment_id' => $payment->getId(),
                    'custom_reason' => $reason
                ]
            ]);
            
            $status = match($refund->status) {
                'succeeded' => PaymentStatus::COMPLETED,
                'pending' => PaymentStatus::PENDING,
                'failed', 'canceled' => PaymentStatus::FAILED,
                default => PaymentStatus::PENDING
            };
            
            return new PaymentResult(
                $status,
                $refund->id,
                $refund->failure_reason,
                'refund',
                ['refund' => $refund->toArray()]
            );
            
        } catch (\Exception $e) {
            throw new \RuntimeException('Stripe refund failed: ' . $e->getMessage());
        }
    }
    
    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        try {
            $signature = $headers['stripe-signature'] ?? '';
            
            \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $this->webhookSecret
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function handleWebhook(array $payload): bool
    {
        $eventType = $payload['type'] ?? '';
        $data = $payload['data']['object'] ?? [];
        
        $this->logger->info('Processing Stripe webhook', ['type' => $eventType]);
        
        switch ($eventType) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($data);
                
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($data);
                
            case 'charge.dispute.created':
                return $this->handleChargeDispute($data);
                
            default:
                $this->logger->info('Unhandled Stripe webhook event', ['type' => $eventType]);
                return true; // Ne pas considérer comme une erreur
        }
    }
    
    public function createCustomer(array $customerData): string
    {
        $customer = $this->stripe->customers->create([
            'email' => $customerData['email'],
            'name' => $customerData['name'] ?? null,
            'metadata' => [
                'customer_id' => $customerData['id'] ?? null
            ]
        ]);
        
        return $customer->id;
    }
    
    public function attachPaymentMethod(string $customerId, string $paymentMethodId): void
    {
        $this->stripe->paymentMethods->attach($paymentMethodId, [
            'customer' => $customerId
        ]);
    }
    
    private function createPaymentResultFromIntent(PaymentIntent $intent): PaymentResult
    {
        $status = match($intent->status) {
            'succeeded' => PaymentStatus::COMPLETED,
            'requires_payment_method', 'requires_confirmation' => PaymentStatus::PENDING,
            'requires_action' => PaymentStatus::PENDING,
            'processing' => PaymentStatus::PROCESSING,
            'canceled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::FAILED
        };
        
        $errorMessage = null;
        if ($intent->last_payment_error) {
            $errorMessage = $intent->last_payment_error->message;
        }
        
        return new PaymentResult(
            $status,
            $intent->id,
            $errorMessage,
            'payment',
            ['intent' => $intent->toArray()]
        );
    }
    
    private function handlePaymentIntentSucceeded(array $data): bool
    {
        $paymentId = $data['metadata']['payment_id'] ?? null;
        
        if (!$paymentId) {
            return true;
        }
        
        $payment = $this->em->getRepository(Payment::class)->find($paymentId);
        
        if ($payment && $payment->getStatus() !== PaymentStatus::COMPLETED) {
            $payment->setStatus(PaymentStatus::COMPLETED);
            $payment->setProcessedAt(new \DateTimeImmutable());
            $this->em->flush();
            
            $this->logger->info('Payment marked as completed via webhook', [
                'payment_id' => $paymentId
            ]);
        }
        
        return true;
    }
    
    private function handlePaymentIntentFailed(array $data): bool
    {
        $paymentId = $data['metadata']['payment_id'] ?? null;
        
        if (!$paymentId) {
            return true;
        }
        
        $payment = $this->em->getRepository(Payment::class)->find($paymentId);
        
        if ($payment && $payment->getStatus() !== PaymentStatus::FAILED) {
            $payment->setStatus(PaymentStatus::FAILED);
            $payment->setFailureReason($data['last_payment_error']['message'] ?? 'Payment failed');
            $this->em->flush();
            
            $this->logger->info('Payment marked as failed via webhook', [
                'payment_id' => $paymentId
            ]);
        }
        
        return true;
    }
    
    private function mapRefundReason(string $reason): string
    {
        // Mapper les raisons personnalisées vers les raisons Stripe
        return match(strtolower($reason)) {
            'duplicate', 'fraudulent', 'requested_by_customer' => $reason,
            default => 'requested_by_customer'
        };
    }
}
```

## Intégration PayPal

### PayPalPaymentProvider

```php
<?php

namespace App\Service\Payment\Provider;

use App\Entity\Payment\Payment;
use App\Enum\PaymentStatus;
use App\Service\Payment\PaymentResult;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use Psr\Log\LoggerInterface;

class PayPalPaymentProvider implements PaymentProviderInterface
{
    private PayPalHttpClient $client;
    private LoggerInterface $logger;
    private string $webhookId;
    
    public function __construct(string $clientId, string $clientSecret, bool $sandbox, string $webhookId, LoggerInterface $logger)
    {
        $environment = $sandbox ? 
            new SandboxEnvironment($clientId, $clientSecret) :
            new ProductionEnvironment($clientId, $clientSecret);
            
        $this->client = new PayPalHttpClient($environment);
        $this->webhookId = $webhookId;
        $this->logger = $logger;
    }
    
    public function createPaymentIntent(Payment $payment, array $options = []): array
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        
        $order = $payment->getOrder();
        
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $payment->getId(),
                'amount' => [
                    'currency_code' => $payment->getCurrencyCode(),
                    'value' => number_format($payment->getAmount() / 100, 2, '.', '')
                ],
                'description' => "Commande #{$order->getNumber()}"
            ]],
            'application_context' => [
                'brand_name' => $options['brand_name'] ?? 'Mon E-commerce',
                'landing_page' => 'BILLING',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS',
                'user_action' => 'PAY_NOW',
                'return_url' => $options['return_url'] ?? '',
                'cancel_url' => $options['cancel_url'] ?? ''
            ]
        ];
        
        // Ajouter l'adresse de livraison
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $body['purchase_units'][0]['shipping'] = [
                'name' => [
                    'full_name' => $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name']
                ],
                'address' => [
                    'address_line_1' => $shippingAddress['address_line_1'],
                    'address_line_2' => $shippingAddress['address_line_2'] ?? '',
                    'admin_area_2' => $shippingAddress['city'],
                    'postal_code' => $shippingAddress['postal_code'],
                    'country_code' => $shippingAddress['country_code']
                ]
            ];
        }
        
        $request->body = $body;
        
        try {
            $response = $this->client->execute($request);
            $order = $response->result;
            
            return [
                'id' => $order->id,
                'status' => $order->status,
                'approve_link' => $this->getApproveLink($order->links)
            ];
            
        } catch (\Exception $e) {
            throw new \RuntimeException('PayPal order creation failed: ' . $e->getMessage());
        }
    }
    
    public function processPayment(Payment $payment, array $paymentData = []): PaymentResult
    {
        // Pour PayPal, le processPayment est généralement appelé après approbation
        return $this->capturePayment($payment, $paymentData);
    }
    
    public function capturePayment(Payment $payment, array $captureData = []): PaymentResult
    {
        try {
            $request = new OrdersCaptureRequest($payment->getGatewayId());
            $response = $this->client->execute($request);
            
            $order = $response->result;
            
            $status = match($order->status) {
                'COMPLETED' => PaymentStatus::COMPLETED,
                'PENDING' => PaymentStatus::PENDING,
                'CANCELLED' => PaymentStatus::CANCELLED,
                default => PaymentStatus::FAILED
            };
            
            $captureId = null;
            if (!empty($order->purchase_units[0]->payments->captures)) {
                $captureId = $order->purchase_units[0]->payments->captures[0]->id;
            }
            
            return new PaymentResult(
                $status,
                $captureId,
                null,
                'capture',
                ['order' => $order]
            );
            
        } catch (\Exception $e) {
            return new PaymentResult(
                PaymentStatus::FAILED,
                null,
                $e->getMessage(),
                'capture',
                ['error' => $e->getMessage()]
            );
        }
    }
    
    public function confirmPayment(Payment $payment, array $confirmationData = []): PaymentResult
    {
        // Pour PayPal, la confirmation se fait via capture
        return $this->capturePayment($payment, $confirmationData);
    }
    
    public function refund(Payment $payment, int $amount, string $reason = ''): PaymentResult
    {
        try {
            $request = new CapturesRefundRequest($payment->getGatewayReference());
            $request->body = [
                'amount' => [
                    'currency_code' => $payment->getCurrencyCode(),
                    'value' => number_format($amount / 100, 2, '.', '')
                ],
                'note_to_payer' => $reason ?: 'Remboursement de votre commande'
            ];
            
            $response = $this->client->execute($request);
            $refund = $response->result;
            
            $status = match($refund->status) {
                'COMPLETED' => PaymentStatus::COMPLETED,
                'PENDING' => PaymentStatus::PENDING,
                'FAILED', 'CANCELLED' => PaymentStatus::FAILED,
                default => PaymentStatus::PENDING
            };
            
            return new PaymentResult(
                $status,
                $refund->id,
                null,
                'refund',
                ['refund' => $refund]
            );
            
        } catch (\Exception $e) {
            throw new \RuntimeException('PayPal refund failed: ' . $e->getMessage());
        }
    }
    
    public function verifyWebhookSignature(array $payload, array $headers): bool
    {
        // PayPal webhook signature verification
        // Implementation specific à PayPal
        return true; // Simplifié pour l'exemple
    }
    
    public function handleWebhook(array $payload): bool
    {
        $eventType = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];
        
        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePaymentCaptureCompleted($resource);
                
            case 'PAYMENT.CAPTURE.DENIED':
                return $this->handlePaymentCaptureDenied($resource);
                
            default:
                $this->logger->info('Unhandled PayPal webhook event', ['type' => $eventType]);
                return true;
        }
    }
    
    private function getApproveLink(array $links): ?string
    {
        foreach ($links as $link) {
            if ($link->rel === 'approve') {
                return $link->href;
            }
        }
        return null;
    }
    
    private function handlePaymentCaptureCompleted(array $resource): bool
    {
        // Logique pour traiter la capture completed
        $this->logger->info('PayPal payment capture completed', ['capture_id' => $resource['id'] ?? null]);
        return true;
    }
    
    private function handlePaymentCaptureDenied(array $resource): bool
    {
        // Logique pour traiter la capture refusée
        $this->logger->info('PayPal payment capture denied', ['capture_id' => $resource['id'] ?? null]);
        return true;
    }
}
```

## Énumérations de paiement

### PaymentStatus et PaymentMethod

```php
<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    
    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PROCESSING => 'En cours',
            self::COMPLETED => 'Terminé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
            self::REFUNDED => 'Remboursé',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::PROCESSING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'purple',
        };
    }
}

enum PaymentMethod: string
{
    case CARD = 'card';
    case PAYPAL = 'paypal';
    case BANK_TRANSFER = 'bank_transfer';
    case APPLE_PAY = 'apple_pay';
    case GOOGLE_PAY = 'google_pay';
    
    public function getLabel(): string
    {
        return match($this) {
            self::CARD => 'Carte bancaire',
            self::PAYPAL => 'PayPal',
            self::BANK_TRANSFER => 'Virement bancaire',
            self::APPLE_PAY => 'Apple Pay',
            self::GOOGLE_PAY => 'Google Pay',
        };
    }
}
```

## API de paiement

### PaymentController

```php
<?php

namespace App\Controller\Api;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Service\Payment\PaymentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/payments')]
class PaymentController extends AbstractApiController
{
    private PaymentService $paymentService;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        PaymentService $paymentService
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->paymentService = $paymentService;
    }
    
    #[Route('/intent', methods: ['POST'])]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $orderId = $data['order_id'] ?? null;
        $gateway = $data['gateway'] ?? 'stripe';
        
        if (!$orderId) {
            return $this->createErrorResponse('Order ID is required', 400);
        }
        
        $order = $this->em->getRepository(Order::class)->find($orderId);
        if (!$order) {
            return $this->createErrorResponse('Order not found', 404);
        }
        
        // Vérifier les permissions
        $customer = $this->getCurrentCustomer($request);
        if ($customer && $order->getCustomerId() !== $customer->getId()) {
            return $this->createErrorResponse('Access denied', 403);
        }
        
        try {
            $intent = $this->paymentService->createPaymentIntent($order, $gateway, $data);
            
            return $this->jsonResponse($intent, 201);
            
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
    
    #[Route('/{id}/confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function confirmPayment(int $id, Request $request): JsonResponse
    {
        $payment = $this->em->getRepository(Payment::class)->find($id);
        if (!$payment) {
            return $this->createErrorResponse('Payment not found', 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        try {
            $result = $this->paymentService->confirmPayment($payment, $data);
            
            return $this->jsonResponse([
                'success' => $result->isSuccessful(),
                'status' => $result->getStatus()->value,
                'message' => $result->getErrorMessage(),
                'transaction_id' => $result->getTransactionId()
            ]);
            
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
    
    #[Route('/webhook/{provider}', methods: ['POST'])]
    public function handleWebhook(string $provider, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $headers = $request->headers->all();
        
        try {
            $success = $this->paymentService->handleWebhook($provider, $payload, $headers);
            
            return $this->jsonResponse([
                'success' => $success,
                'message' => 'Webhook processed'
            ]);
            
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
}
```

---

Cette intégration complète des paiements démontre une architecture modulaire supportant multiple providers (Stripe, PayPal) avec gestion des webhooks, remboursements, méthodes de paiement sauvegardées et conformité sécuritaire pour une solution e-commerce robuste.