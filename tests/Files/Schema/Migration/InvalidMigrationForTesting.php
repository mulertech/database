<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Schema\Migration;

use MulerTech\Database\Schema\Migration\Migration;

class InvalidMigrationForTesting extends Migration
{
    public function up(): void {}
    public function down(): void {}
}