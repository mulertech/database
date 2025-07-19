<?php

namespace MulerTech\Database\Tests\Files\Entity;

use DateTime;
use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\Tests\Files\Repository\UserRepository;

/**
 * Class User
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(repository: UserRepository::class, tableName: "users_test", autoIncrement: 100)]
class User
{
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false, columnDefault: "John")]
    private ?string $username = null;

    #[MtColumn(columnName: "size", columnType: ColumnType::INT, isNullable: true)]
    private ?int $size = null;

    #[MtColumn(columnName: "account_balance", columnType: ColumnType::FLOAT, length: 10, scale: 2, isNullable: true)]
    private ?float $accountBalance = null;

    #[MtColumn(columnName: "unit_id", columnType: ColumnType::INT, unsigned: true, isNullable: false, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Unit::class, referencedColumn: "id", deleteRule: FkRule::RESTRICT, updateRule: FkRule::CASCADE)]
    #[MtOneToOne(targetEntity: Unit::class)]
    private ?Unit $unit = null;

    #[MtColumn(columnName: "age", columnType: ColumnType::TINYINT, unsigned: true, isNullable: true)]
    private ?int $age = null;

    #[MtColumn(columnName: "score", columnType: ColumnType::SMALLINT, isNullable: true)]
    private ?int $score = null;

    #[MtColumn(columnName: "views", columnType: ColumnType::MEDIUMINT, unsigned: true, isNullable: true)]
    private ?int $views = null;

    #[MtColumn(columnName: "big_number", columnType: ColumnType::BIGINT, unsigned: true, isNullable: true)]
    private ?int $bigNumber = null;

    #[MtColumn(columnName: "decimal_value", columnType: ColumnType::DECIMAL, length: 10, scale: 2, isNullable: true, columnDefault: '0.00')]
    private ?float $decimalValue = null;

    #[MtColumn(columnName: "double_value", columnType: ColumnType::DOUBLE, isNullable: true)]
    private ?float $doubleValue = null;

    #[MtColumn(columnName: "char_code", columnType: ColumnType::CHAR, length: 5, isNullable: true)]
    private ?string $charCode = null;

    #[MtColumn(columnName: "description", columnType: ColumnType::TEXT, isNullable: true)]
    private ?string $description = null;

    #[MtColumn(columnName: "tiny_text", columnType: ColumnType::TINYTEXT, isNullable: true)]
    private ?string $tinyText = null;

    #[MtColumn(columnName: "medium_text", columnType: ColumnType::MEDIUMTEXT, isNullable: true)]
    private ?string $mediumText = null;

    #[MtColumn(columnName: "long_text", columnType: ColumnType::LONGTEXT, isNullable: true)]
    private ?string $longText = null;

    #[MtColumn(columnName: "binary_data", columnType: ColumnType::BINARY, length: 16, isNullable: true)]
    private ?string $binaryData = null;

    #[MtColumn(columnName: "varbinary_data", columnType: ColumnType::VARBINARY, length: 255, isNullable: true)]
    private ?string $varbinaryData = null;

    #[MtColumn(columnName: "blob_data", columnType: ColumnType::BLOB, isNullable: true)]
    private ?string $blobData = null;

    #[MtColumn(columnName: "tiny_blob", columnType: ColumnType::TINYBLOB, isNullable: true)]
    private ?string $tinyBlob = null;

    #[MtColumn(columnName: "medium_blob", columnType: ColumnType::MEDIUMBLOB, isNullable: true)]
    private ?string $mediumBlob = null;

    #[MtColumn(columnName: "long_blob", columnType: ColumnType::LONGBLOB, isNullable: true)]
    private ?string $longBlob = null;

    #[MtColumn(columnName: "birth_date", columnType: ColumnType::DATE, isNullable: true)]
    private ?string $birthDate = null;

    #[MtColumn(columnName: "created_at", columnType: ColumnType::DATETIME, isNullable: true)]
    private ?string $createdAt = null;

    #[MtColumn(columnName: "updated_at", columnType: ColumnType::TIMESTAMP, isNullable: true)]
    private ?string $updatedAt = null;

    #[MtColumn(columnName: "work_time", columnType: ColumnType::TIME, isNullable: true)]
    private ?string $workTime = null;

    #[MtColumn(columnName: "birth_year", columnType: ColumnType::YEAR, isNullable: true, columnDefault: null)]
    private ?int $birthYear = null;

    #[MtColumn(columnName: "is_active", columnType: ColumnType::TINYINT, length: 1, isNullable: true, columnDefault: '0')]
    private ?bool $isActive = null;

    #[MtColumn(columnName: "is_verified", columnType: ColumnType::TINYINT, length: 1, isNullable: true)]
    private ?bool $isVerified = null;

    #[MtColumn(columnName: "status", columnType: ColumnType::ENUM, isNullable: true, choices: ["active", "inactive", "pending", "banned"])]
    private ?string $status = null;

    #[MtColumn(columnName: "permissions", columnType: ColumnType::SET, isNullable: true, choices: ["read", "write", "delete", "update"])]
    private ?string $permissions = null;

    #[MtColumn(columnName: "metadata", columnType: ColumnType::JSON, isNullable: true)]
    private ?string $metadata = null;

    #[MtColumn(columnName: "location", columnType: ColumnType::GEOMETRY, isNullable: true)]
    private ?string $location = null;

    #[MtColumn(columnName: "coordinates", columnType: ColumnType::POINT, isNullable: true)]
    private ?string $coordinates = null;

    #[MtColumn(columnName: "path", columnType: ColumnType::LINESTRING, isNullable: true)]
    private ?string $path = null;

    #[MtColumn(columnName: "area", columnType: ColumnType::POLYGON, isNullable: true)]
    private ?string $area = null;

    // Normally, we should use manager_id as column name. It's for test purpose
    #[MtColumn(columnName: "manager", columnType: ColumnType::INT, unsigned: true, isNullable: true, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: User::class, referencedColumn: "id", deleteRule: FkRule::SET_NULL, updateRule: FkRule::CASCADE)]
    #[MtOneToOne(targetEntity: User::class)]
    private ?User $manager = null;

    #[MtManyToMany(
        targetEntity: Group::class,
        mappedBy: GroupUser::class,
        joinProperty: "user",
        inverseJoinProperty: "group"
    )]
    private Collection $groups;

    private ?DateTime $blockedAtDateTime = null;

    private int $notColumn = 0;

    public function __construct()
    {
        $this->groups = new DatabaseCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    public function getGroups(): Collection
    {
        if (!($this->groups instanceof DatabaseCollection)) {
            $this->groups = new DatabaseCollection($this->groups->items());
        }
        return $this->groups;
    }

    public function setGroups(Collection $groups): self
    {
        // Always ensure we use DatabaseCollection for change tracking
        if (!($groups instanceof DatabaseCollection)) {
            $this->groups = new DatabaseCollection($groups->items());
        } else {
            $this->groups = $groups;
        }
        
        return $this;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->getGroups()->contains($group)) {
            $this->getGroups()->push($group);
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        $this->getGroups()->removeItem($group, false);
        
        return $this;
    }

    public function getAccountBalance(): ?float
    {
        return $this->accountBalance;
    }

    public function setAccountBalance(?float $accountBalance): self
    {
        $this->accountBalance = $accountBalance;
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews(?int $views): self
    {
        $this->views = $views;
        return $this;
    }

    public function getBigNumber(): ?int
    {
        return $this->bigNumber;
    }

    public function setBigNumber(?int $bigNumber): self
    {
        $this->bigNumber = $bigNumber;
        return $this;
    }

    public function getDecimalValue(): ?float
    {
        return $this->decimalValue;
    }

    public function setDecimalValue(?float $decimalValue): self
    {
        $this->decimalValue = $decimalValue;
        return $this;
    }

    public function getDoubleValue(): ?float
    {
        return $this->doubleValue;
    }

    public function setDoubleValue(?float $doubleValue): self
    {
        $this->doubleValue = $doubleValue;
        return $this;
    }

    public function getCharCode(): ?string
    {
        return $this->charCode;
    }

    public function setCharCode(?string $charCode): self
    {
        $this->charCode = $charCode;
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

    public function getTinyText(): ?string
    {
        return $this->tinyText;
    }

    public function setTinyText(?string $tinyText): self
    {
        $this->tinyText = $tinyText;
        return $this;
    }

    public function getMediumText(): ?string
    {
        return $this->mediumText;
    }

    public function setMediumText(?string $mediumText): self
    {
        $this->mediumText = $mediumText;
        return $this;
    }

    public function getLongText(): ?string
    {
        return $this->longText;
    }

    public function setLongText(?string $longText): self
    {
        $this->longText = $longText;
        return $this;
    }

    public function getBinaryData(): ?string
    {
        return $this->binaryData;
    }

    public function setBinaryData(?string $binaryData): self
    {
        $this->binaryData = $binaryData;
        return $this;
    }

    public function getVarbinaryData(): ?string
    {
        return $this->varbinaryData;
    }

    public function setVarbinaryData(?string $varbinaryData): self
    {
        $this->varbinaryData = $varbinaryData;
        return $this;
    }

    public function getBlobData(): ?string
    {
        return $this->blobData;
    }

    public function setBlobData(?string $blobData): self
    {
        $this->blobData = $blobData;
        return $this;
    }

    public function getTinyBlob(): ?string
    {
        return $this->tinyBlob;
    }

    public function setTinyBlob(?string $tinyBlob): self
    {
        $this->tinyBlob = $tinyBlob;
        return $this;
    }

    public function getMediumBlob(): ?string
    {
        return $this->mediumBlob;
    }

    public function setMediumBlob(?string $mediumBlob): self
    {
        $this->mediumBlob = $mediumBlob;
        return $this;
    }

    public function getLongBlob(): ?string
    {
        return $this->longBlob;
    }

    public function setLongBlob(?string $longBlob): self
    {
        $this->longBlob = $longBlob;
        return $this;
    }

    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function setBirthDate(?string $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getWorkTime(): ?string
    {
        return $this->workTime;
    }

    public function setWorkTime(?string $workTime): self
    {
        $this->workTime = $workTime;
        return $this;
    }

    public function getBirthYear(): ?int
    {
        return $this->birthYear;
    }

    public function setBirthYear(?int $birthYear): self
    {
        $this->birthYear = $birthYear;
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getIsVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(?bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPermissions(): ?string
    {
        return $this->permissions;
    }

    public function setPermissions(?string $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getCoordinates(): ?string
    {
        return $this->coordinates;
    }

    public function setCoordinates(?string $coordinates): self
    {
        $this->coordinates = $coordinates;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): self
    {
        $this->area = $area;
        return $this;
    }

    public function getBlockedAtDateTime(): ?DateTime
    {
        return $this->blockedAtDateTime;
    }

    public function setBlockedAtDateTime(?DateTime $blockedAtDateTime): self
    {
        $this->blockedAtDateTime = $blockedAtDateTime;
        return $this;
    }
}