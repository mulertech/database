<?php

namespace MulerTech\Database\Tests\Files\Migrations;

use MulerTech\Database\Schema\Migration\Migration;

/**
 * Failing migration for testing error handling
 */
class Migration202506011200 extends Migration
{
    public function up(): void
    {
        throw new \Exception('Intentional migration failure');
    }

    public function down(): void
    {
        throw new \Exception('Intentional rollback failure');
    }
}
