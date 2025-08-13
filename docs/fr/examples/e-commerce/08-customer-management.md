# Gestion des Clients - E-commerce

Cette section présente l'implémentation complète du système de gestion des clients, démontrant la gestion des profils, préférences, historique, fidélité et segmentation avec MulerTech Database ORM.

## Table des matières

- [Architecture client](#architecture-client)
- [Gestion des profils](#gestion-des-profils)
- [Système d'adresses](#système-dadresses)
- [Préférences et paramètres](#préférences-et-paramètres)
- [Programme de fidélité](#programme-de-fidélité)
- [Segmentation client](#segmentation-client)
- [Historique et analytics](#historique-et-analytics)
- [Wishlist et favoris](#wishlist-et-favoris)
- [Notifications et communication](#notifications-et-communication)
- [API de gestion client](#api-de-gestion-client)

## Architecture client

### Schéma étendu des entités client

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   customers     │    │customer_profiles│    │customer_groups  │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ id (PK)         │◄──┐│ id (PK)         │   ┌►│ id (PK)         │
│ email           │   ││ customer_id     │   │ │ name            │
│ password_hash   │   ││ avatar_url      │   │ │ description     │
│ first_name      │   ││ bio             │   │ │ discount_rate   │
│ last_name       │   ││ preferences     │   │ │ benefits        │
│ phone           │   ││ social_profiles │   │ │ created_at      │
│ date_of_birth   │   ││ interests       │   │ └─────────────────┘
│ gender          │   ││ created_at      │   │
│ customer_group ────┘ └─────────────────┘   │
│ total_spent     │                          │
│ orders_count    │    ┌─────────────────┐   │
│ last_order_at   │    │customer_addresses│   │
│ loyalty_points  │    ├─────────────────┤   │
│ referral_code   │    │ id (PK)         │   │
│ referred_by     │    │ customer_id     │   │
│ email_verified  │    │ type            │   │
│ marketing_opt   │    │ label           │   │
│ created_at      │    │ first_name      │   │
└─────────────────┘    │ last_name       │   │
       │               │ company         │   │
       │               │ street_1        │   │
       │               │ street_2        │   │
┌─────────────────┐    │ city            │   │
│customer_wishlist│    │ state           │   │
├─────────────────┤    │ postal_code     │   │
│ id (PK)         │    │ country_code    │   │
│ customer_id     │    │ phone           │   │
│ product_id      │    │ is_default      │   │
│ variant_id      │    │ created_at      │   │
│ added_at        │    └─────────────────┘   │
│ notes           │                          │
└─────────────────┘    ┌─────────────────┐   │
                       │loyalty_transactions│  │
                       ├─────────────────┤   │
                       │ id (PK)         │   │
                       │ customer_id     │   │
                       │ type            │   │
                       │ points          │   │
                       │ order_id        │   │
                       │ description     │   │
                       │ expires_at      │   │
                       │ created_at      │   │
                       └─────────────────┘   │
```

## Gestion des profils

### CustomerProfile - Profil étendu

```php
<?php

namespace App\Entity\Customer;

use App\Entity\BaseEntity;
use MulerTech\Database\ORM\Attribute\MtEntity;
use MulerTech\Database\ORM\Attribute\MtColumn;
use MulerTech\Database\ORM\Attribute\MtRelation;

#[MtEntity(table: 'customer_profiles')]
class CustomerProfile extends BaseEntity
{
    #[MtColumn(type: 'int', primaryKey: true, autoIncrement: true)]
    private int $id;
    
    #[MtColumn(name: 'customer_id', type: 'int', unique: true)]
    private int $customerId;
    
    #[MtColumn(name: 'avatar_url', type: 'varchar', length: 500, nullable: true)]
    private ?string $avatarUrl = null;
    
    #[MtColumn(type: 'text', nullable: true)]
    private ?string $bio = null;
    
    #[MtColumn(type: 'varchar', length: 100, nullable: true)]
    private ?string $profession = null;
    
    #[MtColumn(type: 'varchar', length: 100, nullable: true)]
    private ?string $company = null;
    
    #[MtColumn(name: 'website_url', type: 'varchar', length: 255, nullable: true)]
    private ?string $websiteUrl = null;
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $preferences = null; // UI, notifications, etc.
    
    #[MtColumn(name: 'social_profiles', type: 'json', nullable: true)]
    private ?array $socialProfiles = null; // Facebook, Instagram, Twitter
    
    #[MtColumn(type: 'json', nullable: true)]
    private ?array $interests = null; // Tags d'intérêts
    
    #[MtColumn(name: 'communication_preferences', type: 'json', nullable: true)]
    private ?array $communicationPreferences = null;
    
    #[MtColumn(name: 'privacy_settings', type: 'json', nullable: true)]
    private ?array $privacySettings = null;
    
    #[MtColumn(type: 'varchar', length: 10, default: 'fr')]
    private string $language = 'fr';
    
    #[MtColumn(type: 'varchar', length: 50, default: 'Europe/Paris')]
    private string $timezone = 'Europe/Paris';
    
    #[MtColumn(type: 'varchar', length: 3, default: 'EUR')]
    private string $currency = 'EUR';
    
    // Relations
    #[MtRelation('OneToOne', targetEntity: Customer::class)]
    private ?Customer $customer = null;
    
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return $this->preferences[$key] ?? $default;
    }
    
    public function setPreference(string $key, mixed $value): self
    {
        $this->preferences = $this->preferences ?? [];
        $this->preferences[$key] = $value;
        return $this;
    }
    
    public function addInterest(string $interest): self
    {
        $this->interests = $this->interests ?? [];
        if (!in_array($interest, $this->interests)) {
            $this->interests[] = $interest;
        }
        return $this;
    }
    
    public function removeInterest(string $interest): self
    {
        if ($this->interests) {
            $this->interests = array_values(array_filter($this->interests, fn($i) => $i !== $interest));
        }
        return $this;
    }
    
    public function hasInterest(string $interest): bool
    {
        return in_array($interest, $this->interests ?? []);
    }
    
    public function getCommunicationPreference(string $channel): bool
    {
        return $this->communicationPreferences[$channel] ?? true;
    }
    
    public function setCommunicationPreference(string $channel, bool $enabled): self
    {
        $this->communicationPreferences = $this->communicationPreferences ?? [];
        $this->communicationPreferences[$channel] = $enabled;
        return $this;
    }
    
    // Getters et setters...
}
```

### CustomerService - Service de gestion

```php
<?php

namespace App\Service\Customer;

use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerProfile;
use App\Entity\Customer\CustomerGroup;
use App\Repository\Customer\CustomerRepository;
use MulerTech\Database\ORM\EntityManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

class CustomerService
{
    private EntityManager $em;
    private CustomerRepository $customerRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManager $em,
        CustomerRepository $customerRepository,
        UserPasswordHasherInterface $passwordHasher,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->customerRepository = $customerRepository;
        $this->passwordHasher = $passwordHasher;
        $this->logger = $logger;
    }
    
    public function createCustomer(array $data, bool $sendWelcomeEmail = true): Customer
    {
        // Vérifier si l'email existe déjà
        if ($this->customerRepository->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Email already exists');
        }
        
        $customer = new Customer();
        $customer->setEmail($data['email'])
                 ->setFirstName($data['first_name'])
                 ->setLastName($data['last_name'])
                 ->setPhone($data['phone'] ?? null)
                 ->setDateOfBirth(isset($data['date_of_birth']) ? new \DateTimeImmutable($data['date_of_birth']) : null)
                 ->setGender($data['gender'] ?? null)
                 ->setLocale($data['locale'] ?? 'fr')
                 ->setAcceptsMarketing($data['accepts_marketing'] ?? false)
                 ->setReferralCode($this->generateReferralCode());
        
        // Hash du mot de passe
        if (isset($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($customer, $data['password']);
            $customer->setPasswordHash($hashedPassword);
        }
        
        // Traiter le code de parrainage
        if (isset($data['referral_code'])) {
            $referrer = $this->customerRepository->findByReferralCode($data['referral_code']);
            if ($referrer) {
                $customer->setReferredBy($referrer->getId());
            }
        }
        
        // Assigner un groupe par défaut
        $defaultGroup = $this->getDefaultCustomerGroup();
        if ($defaultGroup) {
            $customer->setCustomerGroupId($defaultGroup->getId());
        }
        
        $this->em->persist($customer);
        $this->em->flush();
        
        // Créer le profil
        $this->createCustomerProfile($customer, $data);
        
        if ($sendWelcomeEmail) {
            $this->sendWelcomeEmail($customer);
        }
        
        $this->logger->info('Customer created', [
            'customer_id' => $customer->getId(),
            'email' => $customer->getEmail()
        ]);
        
        return $customer;
    }
    
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        // Mise à jour des champs autorisés
        $allowedFields = ['first_name', 'last_name', 'phone', 'date_of_birth', 'gender', 'accepts_marketing'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $setter = 'set' . str_replace('_', '', ucwords($field, '_'));
                if (method_exists($customer, $setter)) {
                    $value = $data[$field];
                    
                    // Conversion spéciale pour les dates
                    if ($field === 'date_of_birth' && $value) {
                        $value = new \DateTimeImmutable($value);
                    }
                    
                    $customer->$setter($value);
                }
            }
        }
        
        $this->em->flush();
        
        return $customer;
    }
    
    public function updateCustomerProfile(Customer $customer, array $profileData): CustomerProfile
    {
        $profile = $customer->getProfile();
        
        if (!$profile) {
            $profile = $this->createCustomerProfile($customer, $profileData);
        }
        
        // Mise à jour des champs du profil
        if (isset($profileData['bio'])) {
            $profile->setBio($profileData['bio']);
        }
        
        if (isset($profileData['profession'])) {
            $profile->setProfession($profileData['profession']);
        }
        
        if (isset($profileData['company'])) {
            $profile->setCompany($profileData['company']);
        }
        
        if (isset($profileData['website_url'])) {
            $profile->setWebsiteUrl($profileData['website_url']);
        }
        
        if (isset($profileData['interests']) && is_array($profileData['interests'])) {
            $profile->setInterests($profileData['interests']);
        }
        
        if (isset($profileData['language'])) {
            $profile->setLanguage($profileData['language']);
        }
        
        if (isset($profileData['timezone'])) {
            $profile->setTimezone($profileData['timezone']);
        }
        
        if (isset($profileData['currency'])) {
            $profile->setCurrency($profileData['currency']);
        }
        
        // Gestion des préférences
        if (isset($profileData['preferences']) && is_array($profileData['preferences'])) {
            foreach ($profileData['preferences'] as $key => $value) {
                $profile->setPreference($key, $value);
            }
        }
        
        // Gestion des préférences de communication
        if (isset($profileData['communication_preferences']) && is_array($profileData['communication_preferences'])) {
            foreach ($profileData['communication_preferences'] as $channel => $enabled) {
                $profile->setCommunicationPreference($channel, $enabled);
            }
        }
        
        $this->em->flush();
        
        return $profile;
    }
    
    public function changePassword(Customer $customer, string $currentPassword, string $newPassword): bool
    {
        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($customer, $currentPassword)) {
            return false;
        }
        
        // Hash du nouveau mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($customer, $newPassword);
        $customer->setPasswordHash($hashedPassword);
        
        $this->em->flush();
        
        $this->logger->info('Password changed', ['customer_id' => $customer->getId()]);
        
        return true;
    }
    
    public function verifyEmail(Customer $customer): void
    {
        $customer->setEmailVerifiedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        $this->logger->info('Email verified', ['customer_id' => $customer->getId()]);
    }
    
    public function deactivateCustomer(Customer $customer, string $reason = ''): void
    {
        $customer->setIsActive(false);
        $customer->setDeletedAt(new \DateTimeImmutable());
        
        $this->em->flush();
        
        $this->logger->info('Customer deactivated', [
            'customer_id' => $customer->getId(),
            'reason' => $reason
        ]);
    }
    
    public function getCustomerSummary(Customer $customer): array
    {
        $orders = $this->em->getRepository(Order::class)->findByCustomer($customer, 10);
        $totalSpent = $customer->getTotalSpent();
        $profile = $customer->getProfile();
        
        return [
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'full_name' => $customer->getFullName(),
            'phone' => $customer->getPhone(),
            'created_at' => $customer->getCreatedAt()->format('Y-m-d H:i:s'),
            'last_login_at' => $customer->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'email_verified' => $customer->isEmailVerified(),
            'is_active' => $customer->getIsActive(),
            'total_spent' => $totalSpent,
            'total_spent_formatted' => $this->formatPrice($totalSpent),
            'orders_count' => $customer->getOrdersCount(),
            'loyalty_points' => $customer->getLoyaltyPoints(),
            'customer_group' => $customer->getCustomerGroup()?->getName(),
            'profile' => $profile ? [
                'avatar_url' => $profile->getAvatarUrl(),
                'bio' => $profile->getBio(),
                'interests' => $profile->getInterests(),
                'language' => $profile->getLanguage(),
                'timezone' => $profile->getTimezone()
            ] : null,
            'recent_orders' => array_map(function($order) {
                return [
                    'id' => $order->getId(),
                    'number' => $order->getNumber(),
                    'status' => $order->getStatus()->getLabel(),
                    'total' => $this->formatPrice($order->getTotal()),
                    'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s')
                ];
            }, array_slice($orders, 0, 5))
        ];
    }
    
    private function createCustomerProfile(Customer $customer, array $data): CustomerProfile
    {
        $profile = new CustomerProfile();
        $profile->setCustomer($customer)
                ->setCustomerId($customer->getId())
                ->setLanguage($data['language'] ?? 'fr')
                ->setTimezone($data['timezone'] ?? 'Europe/Paris')
                ->setCurrency($data['currency'] ?? 'EUR');
        
        // Préférences par défaut
        $profile->setPreferences([
            'email_notifications' => true,
            'sms_notifications' => false,
            'marketing_emails' => $customer->getAcceptsMarketing(),
            'theme' => 'light',
            'newsletter' => $customer->getAcceptsMarketing()
        ]);
        
        // Préférences de communication par défaut
        $profile->setCommunicationPreference('email', true);
        $profile->setCommunicationPreference('sms', false);
        $profile->setCommunicationPreference('push', true);
        
        if (isset($data['interests']) && is_array($data['interests'])) {
            $profile->setInterests($data['interests']);
        }
        
        $this->em->persist($profile);
        $this->em->flush();
        
        return $profile;
    }
    
    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
        } while ($this->customerRepository->findByReferralCode($code));
        
        return $code;
    }
    
    private function getDefaultCustomerGroup(): ?CustomerGroup
    {
        return $this->em->getRepository(CustomerGroup::class)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('is_default = ?')
                       ->setParameter(0, true)
                       ->getQuery()
                       ->getSingleResult();
    }
    
    private function sendWelcomeEmail(Customer $customer): void
    {
        // Implémentation d'envoi d'email de bienvenue
        $this->logger->info('Welcome email sent', ['customer_id' => $customer->getId()]);
    }
    
    private function formatPrice(int $priceInCents): string
    {
        return number_format($priceInCents / 100, 2, ',', ' ') . ' €';
    }
}
```

## Programme de fidélité

### LoyaltyService

```php
<?php

namespace App\Service\Customer;

use App\Entity\Customer\Customer;
use App\Entity\Customer\LoyaltyTransaction;
use App\Entity\Order\Order;
use MulerTech\Database\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class LoyaltyService
{
    private EntityManager $em;
    private LoggerInterface $logger;
    
    // Configuration du programme de fidélité
    private array $config = [
        'points_per_euro' => 1,
        'welcome_bonus' => 100,
        'referral_bonus' => 500,
        'birthday_bonus' => 200,
        'review_bonus' => 50,
        'points_expiry_months' => 12
    ];
    
    public function __construct(EntityManager $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
    }
    
    public function awardPointsForOrder(Order $order): int
    {
        if (!$order->getCustomer() || $order->getStatus() !== OrderStatus::COMPLETED) {
            return 0;
        }
        
        $customer = $order->getCustomer();
        $orderTotal = $order->getTotal();
        
        // Calculer les points basés sur le montant de la commande
        $points = (int) floor($orderTotal / 100 * $this->config['points_per_euro']);
        
        // Bonus pour les gros achats
        if ($orderTotal >= 10000) { // 100€
            $points = (int) ($points * 1.5);
        } elseif ($orderTotal >= 5000) { // 50€
            $points = (int) ($points * 1.2);
        }
        
        if ($points > 0) {
            $this->addPoints(
                $customer,
                $points,
                'order_purchase',
                "Achat commande #{$order->getNumber()}",
                $order->getId()
            );
        }
        
        return $points;
    }
    
    public function awardWelcomeBonus(Customer $customer): int
    {
        $existingWelcome = $this->em->getRepository(LoyaltyTransaction::class)
                                   ->createQueryBuilder()
                                   ->select('COUNT(*)')
                                   ->where('customer_id = ? AND type = ?')
                                   ->setParameter(0, $customer->getId())
                                   ->setParameter(1, 'welcome_bonus')
                                   ->getQuery()
                                   ->getSingleScalarResult();
        
        if ($existingWelcome > 0) {
            return 0; // Déjà reçu
        }
        
        return $this->addPoints(
            $customer,
            $this->config['welcome_bonus'],
            'welcome_bonus',
            'Bonus de bienvenue'
        );
    }
    
    public function awardReferralBonus(Customer $referrer, Customer $referred): int
    {
        // Vérifier que le référé a fait au moins une commande
        $referredOrders = $this->em->getRepository(Order::class)
                                  ->createQueryBuilder()
                                  ->select('COUNT(*)')
                                  ->where('customer_id = ? AND status = ?')
                                  ->setParameter(0, $referred->getId())
                                  ->setParameter(1, OrderStatus::COMPLETED->value)
                                  ->getQuery()
                                  ->getSingleScalarResult();
        
        if ($referredOrders < 1) {
            return 0;
        }
        
        // Vérifier que le bonus n'a pas déjà été accordé
        $existingBonus = $this->em->getRepository(LoyaltyTransaction::class)
                                 ->createQueryBuilder()
                                 ->select('COUNT(*)')
                                 ->where('customer_id = ? AND type = ? AND reference_id = ?')
                                 ->setParameter(0, $referrer->getId())
                                 ->setParameter(1, 'referral_bonus')
                                 ->setParameter(2, $referred->getId())
                                 ->getQuery()
                                 ->getSingleScalarResult();
        
        if ($existingBonus > 0) {
            return 0;
        }
        
        return $this->addPoints(
            $referrer,
            $this->config['referral_bonus'],
            'referral_bonus',
            "Bonus de parrainage - {$referred->getFullName()}",
            $referred->getId()
        );
    }
    
    public function awardBirthdayBonus(Customer $customer): int
    {
        if (!$customer->getDateOfBirth()) {
            return 0;
        }
        
        $today = new \DateTimeImmutable();
        $birthday = $customer->getDateOfBirth();
        
        // Vérifier si c'est l'anniversaire (mois et jour)
        if ($today->format('m-d') !== $birthday->format('m-d')) {
            return 0;
        }
        
        // Vérifier si le bonus a déjà été accordé cette année
        $currentYear = $today->format('Y');
        $existingBonus = $this->em->getRepository(LoyaltyTransaction::class)
                                 ->createQueryBuilder()
                                 ->select('COUNT(*)')
                                 ->where('customer_id = ? AND type = ? AND YEAR(created_at) = ?')
                                 ->setParameter(0, $customer->getId())
                                 ->setParameter(1, 'birthday_bonus')
                                 ->setParameter(2, $currentYear)
                                 ->getQuery()
                                 ->getSingleScalarResult();
        
        if ($existingBonus > 0) {
            return 0;
        }
        
        return $this->addPoints(
            $customer,
            $this->config['birthday_bonus'],
            'birthday_bonus',
            'Bonus d\'anniversaire'
        );
    }
    
    public function redeemPoints(Customer $customer, int $points, string $reason, ?int $orderId = null): bool
    {
        if ($customer->getLoyaltyPoints() < $points) {
            return false;
        }
        
        $this->addPoints(
            $customer,
            -$points,
            'redemption',
            $reason,
            $orderId
        );
        
        return true;
    }
    
    public function getPointsValue(int $points): int
    {
        // 100 points = 1€
        return (int) ($points / 100 * 100);
    }
    
    public function calculatePointsNeeded(int $amountInCents): int
    {
        // 1€ = 100 points
        return (int) ($amountInCents / 100 * 100);
    }
    
    public function getCustomerPointsHistory(Customer $customer, int $limit = 50): array
    {
        return $this->em->getRepository(LoyaltyTransaction::class)
                       ->createQueryBuilder()
                       ->select('*')
                       ->where('customer_id = ?')
                       ->orderBy('created_at', 'DESC')
                       ->limit($limit)
                       ->setParameter(0, $customer->getId())
                       ->getQuery()
                       ->getResult();
    }
    
    public function getExpiringPoints(Customer $customer, int $daysAhead = 30): int
    {
        $expiryDate = new \DateTimeImmutable("+{$daysAhead} days");
        
        $expiring = $this->em->getRepository(LoyaltyTransaction::class)
                            ->createQueryBuilder()
                            ->select('SUM(points)')
                            ->where('customer_id = ? AND points > 0 AND expires_at <= ? AND expires_at IS NOT NULL')
                            ->setParameter(0, $customer->getId())
                            ->setParameter(1, $expiryDate)
                            ->getQuery()
                            ->getSingleScalarResult();
        
        return (int) ($expiring ?? 0);
    }
    
    public function expirePoints(): int
    {
        $now = new \DateTimeImmutable();
        $expiredTransactions = $this->em->getRepository(LoyaltyTransaction::class)
                                       ->createQueryBuilder()
                                       ->select('*')
                                       ->where('expires_at < ? AND points > 0 AND is_expired = 0')
                                       ->setParameter(0, $now)
                                       ->getQuery()
                                       ->getResult();
        
        $totalExpired = 0;
        
        foreach ($expiredTransactions as $transaction) {
            $customer = $transaction->getCustomer();
            $points = $transaction->getPoints();
            
            // Marquer comme expiré
            $transaction->setIsExpired(true);
            
            // Créer une transaction d'expiration
            $this->addPoints(
                $customer,
                -$points,
                'expiration',
                'Expiration de points',
                $transaction->getId()
            );
            
            $totalExpired += $points;
        }
        
        $this->em->flush();
        
        $this->logger->info('Points expired', ['total_expired' => $totalExpired]);
        
        return $totalExpired;
    }
    
    private function addPoints(
        Customer $customer,
        int $points,
        string $type,
        string $description,
        ?int $referenceId = null
    ): int {
        $transaction = new LoyaltyTransaction();
        $transaction->setCustomer($customer)
                   ->setCustomerId($customer->getId())
                   ->setPoints($points)
                   ->setType($type)
                   ->setDescription($description)
                   ->setReferenceId($referenceId);
        
        // Définir l'expiration pour les points gagnés
        if ($points > 0) {
            $expiryMonths = $this->config['points_expiry_months'];
            $transaction->setExpiresAt(new \DateTimeImmutable("+{$expiryMonths} months"));
        }
        
        // Mettre à jour le total des points du client
        $newTotal = $customer->getLoyaltyPoints() + $points;
        $customer->setLoyaltyPoints(max(0, $newTotal));
        
        $this->em->persist($transaction);
        $this->em->flush();
        
        $this->logger->info('Loyalty points transaction', [
            'customer_id' => $customer->getId(),
            'points' => $points,
            'type' => $type,
            'new_total' => $customer->getLoyaltyPoints()
        ]);
        
        return abs($points);
    }
}
```

## Segmentation client

### CustomerSegmentationService

```php
<?php

namespace App\Service\Customer;

use App\Repository\Customer\CustomerRepository;
use MulerTech\Database\ORM\EntityManager;

class CustomerSegmentationService
{
    private EntityManager $em;
    private CustomerRepository $customerRepository;
    
    public function __construct(EntityManager $em, CustomerRepository $customerRepository)
    {
        $this->em = $em;
        $this->customerRepository = $customerRepository;
    }
    
    public function segmentCustomers(): array
    {
        return [
            'vip' => $this->getVIPCustomers(),
            'loyal' => $this->getLoyalCustomers(),
            'at_risk' => $this->getAtRiskCustomers(),
            'new' => $this->getNewCustomers(),
            'inactive' => $this->getInactiveCustomers(),
            'high_value' => $this->getHighValueCustomers(),
            'frequent_buyers' => $this->getFrequentBuyers()
        ];
    }
    
    public function getVIPCustomers(): array
    {
        // Clients avec plus de 1000€ de commandes et plus de 5 commandes
        return $this->customerRepository->findBySpendingAndOrders(100000, 5);
    }
    
    public function getLoyalCustomers(): array
    {
        // Clients avec plus de 3 commandes dans les 6 derniers mois
        $sixMonthsAgo = new \DateTimeImmutable('-6 months');
        
        return $this->em->createQueryBuilder()
            ->select('c.id, c.email, c.first_name, c.last_name, COUNT(o.id) as recent_orders')
            ->from('customers', 'c')
            ->innerJoin('orders', 'o', 'o.customer_id = c.id')
            ->where('o.created_at >= ? AND o.status IN (?, ?)')
            ->groupBy('c.id')
            ->having('COUNT(o.id) >= 3')
            ->setParameter(0, $sixMonthsAgo)
            ->setParameter(1, OrderStatus::COMPLETED->value)
            ->setParameter(2, OrderStatus::DELIVERED->value)
            ->getQuery()
            ->getResult();
    }
    
    public function getAtRiskCustomers(): array
    {
        // Clients qui ont commandé dans les 3-12 derniers mois mais pas récemment
        $threeMonthsAgo = new \DateTimeImmutable('-3 months');
        $twelveMonthsAgo = new \DateTimeImmutable('-12 months');
        
        return $this->em->createQueryBuilder()
            ->select('c.*, MAX(o.created_at) as last_order_date')
            ->from('customers', 'c')
            ->innerJoin('orders', 'o', 'o.customer_id = c.id')
            ->where('o.status IN (?, ?)')
            ->groupBy('c.id')
            ->having('MAX(o.created_at) BETWEEN ? AND ?')
            ->setParameter(0, OrderStatus::COMPLETED->value)
            ->setParameter(1, OrderStatus::DELIVERED->value)
            ->setParameter(2, $twelveMonthsAgo)
            ->setParameter(3, $threeMonthsAgo)
            ->getQuery()
            ->getResult();
    }
    
    public function getNewCustomers(): array
    {
        // Clients inscrits dans les 30 derniers jours
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        
        return $this->customerRepository->findRegisteredSince($thirtyDaysAgo);
    }
    
    public function getInactiveCustomers(): array
    {
        // Clients sans commande depuis plus de 12 mois
        $twelveMonthsAgo = new \DateTimeImmutable('-12 months');
        
        return $this->em->createQueryBuilder()
            ->select('c.*')
            ->from('customers', 'c')
            ->leftJoin('orders', 'o', 'o.customer_id = c.id AND o.created_at >= ?')
            ->where('o.id IS NULL')
            ->andWhere('c.created_at < ?')
            ->setParameter(0, $twelveMonthsAgo)
            ->setParameter(1, $twelveMonthsAgo)
            ->getQuery()
            ->getResult();
    }
    
    public function getHighValueCustomers(): array
    {
        // Top 10% des clients par valeur totale
        return $this->em->createQueryBuilder()
            ->select('c.*, c.total_spent')
            ->from('customers', 'c')
            ->where('c.total_spent > ?')
            ->orderBy('c.total_spent', 'DESC')
            ->limit(100) // Ajuster selon les besoins
            ->setParameter(0, $this->calculatePercentileThreshold(90))
            ->getQuery()
            ->getResult();
    }
    
    public function getFrequentBuyers(): array
    {
        // Clients avec plus de 2 commandes par mois en moyenne
        return $this->em->createQueryBuilder()
            ->select('c.*, COUNT(o.id) as total_orders, 
                     DATEDIFF(NOW(), c.created_at) / 30 as months_since_registration,
                     COUNT(o.id) / (DATEDIFF(NOW(), c.created_at) / 30) as orders_per_month')
            ->from('customers', 'c')
            ->innerJoin('orders', 'o', 'o.customer_id = c.id')
            ->where('o.status IN (?, ?) AND c.created_at <= ?')
            ->groupBy('c.id')
            ->having('orders_per_month >= 2 AND months_since_registration >= 1')
            ->setParameter(0, OrderStatus::COMPLETED->value)
            ->setParameter(1, OrderStatus::DELIVERED->value)
            ->setParameter(2, new \DateTimeImmutable('-1 month'))
            ->getQuery()
            ->getResult();
    }
    
    public function getCustomerLifetimeValue(Customer $customer): array
    {
        $orders = $this->em->getRepository(Order::class)->findByCustomer($customer);
        
        $totalSpent = $customer->getTotalSpent();
        $totalOrders = count($orders);
        $avgOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;
        
        // Calculer la fréquence d'achat
        if ($totalOrders > 1) {
            $firstOrder = min(array_map(fn($o) => $o->getCreatedAt(), $orders));
            $lastOrder = max(array_map(fn($o) => $o->getCreatedAt(), $orders));
            $daysBetween = $firstOrder->diff($lastOrder)->days;
            $avgDaysBetweenOrders = $daysBetween / ($totalOrders - 1);
        } else {
            $avgDaysBetweenOrders = null;
        }
        
        // Prédiction CLV simple (Customer Lifetime Value)
        $predictedLifetimeMonths = 24; // 2 ans par défaut
        if ($avgDaysBetweenOrders) {
            $ordersPerYear = 365 / $avgDaysBetweenOrders;
            $predictedCLV = $avgOrderValue * $ordersPerYear * 2; // 2 ans
        } else {
            $predictedCLV = $totalSpent; // Pas assez de données
        }
        
        return [
            'total_spent' => $totalSpent,
            'total_orders' => $totalOrders,
            'avg_order_value' => (int) $avgOrderValue,
            'avg_days_between_orders' => $avgDaysBetweenOrders ? round($avgDaysBetweenOrders, 1) : null,
            'predicted_clv' => (int) $predictedCLV,
            'customer_since_days' => $customer->getCreatedAt()->diff(new \DateTimeImmutable())->days,
            'segment' => $this->determineCustomerSegment($customer)
        ];
    }
    
    private function calculatePercentileThreshold(int $percentile): int
    {
        $result = $this->em->createQueryBuilder()
            ->select('c.total_spent')
            ->from('customers', 'c')
            ->where('c.total_spent > 0')
            ->orderBy('c.total_spent', 'ASC')
            ->getQuery()
            ->getResult();
        
        $values = array_column($result, 'total_spent');
        $index = (int) ceil(count($values) * $percentile / 100) - 1;
        
        return $values[$index] ?? 0;
    }
    
    private function determineCustomerSegment(Customer $customer): string
    {
        $totalSpent = $customer->getTotalSpent();
        $ordersCount = $customer->getOrdersCount();
        $daysSinceLastOrder = $customer->getLastOrderAt() ? 
            $customer->getLastOrderAt()->diff(new \DateTimeImmutable())->days : 
            999;
        
        if ($totalSpent >= 100000 && $ordersCount >= 5) {
            return 'vip';
        } elseif ($ordersCount >= 3 && $daysSinceLastOrder <= 90) {
            return 'loyal';
        } elseif ($daysSinceLastOrder >= 90 && $daysSinceLastOrder <= 365 && $ordersCount > 0) {
            return 'at_risk';
        } elseif ($customer->getCreatedAt() >= new \DateTimeImmutable('-30 days')) {
            return 'new';
        } elseif ($daysSinceLastOrder > 365 || $ordersCount === 0) {
            return 'inactive';
        } else {
            return 'regular';
        }
    }
}
```

## API de gestion client

### CustomerController

```php
<?php

namespace App\Controller\Api;

use App\Entity\Customer\Customer;
use App\Service\Customer\CustomerService;
use App\Service\Customer\LoyaltyService;
use App\Service\Customer\CustomerSegmentationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/customers')]
class CustomerController extends AbstractApiController
{
    private CustomerService $customerService;
    private LoyaltyService $loyaltyService;
    private CustomerSegmentationService $segmentationService;
    
    public function __construct(
        EntityManager $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CustomerService $customerService,
        LoyaltyService $loyaltyService,
        CustomerSegmentationService $segmentationService
    ) {
        parent::__construct($em, $serializer, $validator);
        $this->customerService = $customerService;
        $this->loyaltyService = $loyaltyService;
        $this->segmentationService = $segmentationService;
    }
    
    #[Route('/profile', methods: ['GET'])]
    public function getProfile(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        
        if (!$customer) {
            return $this->createErrorResponse('Authentication required', 401);
        }
        
        $summary = $this->customerService->getCustomerSummary($customer);
        $ltv = $this->segmentationService->getCustomerLifetimeValue($customer);
        
        return $this->jsonResponse([
            'customer' => $summary,
            'lifetime_value' => $ltv
        ]);
    }
    
    #[Route('/profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        
        if (!$customer) {
            return $this->createErrorResponse('Authentication required', 401);
        }
        
        $data = json_decode($request->getContent(), true);
        
        try {
            // Mise à jour du profil client
            $this->customerService->updateCustomer($customer, $data);
            
            // Mise à jour du profil étendu
            if (isset($data['profile'])) {
                $this->customerService->updateCustomerProfile($customer, $data['profile']);
            }
            
            $summary = $this->customerService->getCustomerSummary($customer);
            
            return $this->jsonResponse([
                'message' => 'Profile updated successfully',
                'customer' => $summary
            ]);
            
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 400);
        }
    }
    
    #[Route('/loyalty/points', methods: ['GET'])]
    public function getLoyaltyPoints(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        
        if (!$customer) {
            return $this->createErrorResponse('Authentication required', 401);
        }
        
        $history = $this->loyaltyService->getCustomerPointsHistory($customer, 20);
        $expiringPoints = $this->loyaltyService->getExpiringPoints($customer, 30);
        
        return $this->jsonResponse([
            'current_points' => $customer->getLoyaltyPoints(),
            'points_value' => $this->loyaltyService->getPointsValue($customer->getLoyaltyPoints()),
            'expiring_soon' => $expiringPoints,
            'history' => array_map(function($transaction) {
                return [
                    'id' => $transaction->getId(),
                    'points' => $transaction->getPoints(),
                    'type' => $transaction->getType(),
                    'description' => $transaction->getDescription(),
                    'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
                    'expires_at' => $transaction->getExpiresAt()?->format('Y-m-d H:i:s')
                ];
            }, $history)
        ]);
    }
    
    #[Route('/addresses', methods: ['GET'])]
    public function getAddresses(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        
        if (!$customer) {
            return $this->createErrorResponse('Authentication required', 401);
        }
        
        $addresses = $customer->getAddresses();
        
        return $this->jsonResponse([
            'addresses' => array_map(function($address) {
                return [
                    'id' => $address->getId(),
                    'type' => $address->getType(),
                    'label' => $address->getLabel(),
                    'full_name' => $address->getFullName(),
                    'company' => $address->getCompany(),
                    'formatted_address' => $address->getFormattedAddress(),
                    'is_default' => $address->isDefault()
                ];
            }, $addresses)
        ]);
    }
    
    #[Route('/wishlist', methods: ['GET'])]
    public function getWishlist(Request $request): JsonResponse
    {
        $customer = $this->getCurrentCustomer($request);
        
        if (!$customer) {
            return $this->createErrorResponse('Authentication required', 401);
        }
        
        $wishlistItems = $this->em->getRepository(CustomerWishlist::class)
                                 ->findByCustomerWithProducts($customer);
        
        return $this->jsonResponse([
            'items' => array_map(function($item) {
                return [
                    'id' => $item->getId(),
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'name' => $item->getProduct()->getName(),
                        'slug' => $item->getProduct()->getSlug(),
                        'price' => $item->getProduct()->getPrice(),
                        'formatted_price' => $this->formatPrice($item->getProduct()->getPrice())
                    ],
                    'variant' => $item->getVariant() ? [
                        'id' => $item->getVariant()->getId(),
                        'name' => $item->getVariant()->getName(),
                        'attributes' => $item->getVariant()->getAttributes()
                    ] : null,
                    'added_at' => $item->getAddedAt()->format('Y-m-d H:i:s'),
                    'notes' => $item->getNotes()
                ];
            }, $wishlistItems)
        ]);
    }
}
```

---

Ce système complet de gestion des clients démontre une architecture sophistiquée avec profils étendus, programme de fidélité, segmentation automatique, analytics comportementaux et API complète pour une expérience client personnalisée et engageante.