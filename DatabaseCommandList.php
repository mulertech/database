<?php

use MulerTech\Database\Schema\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Schema\Migration\Command\MigrationRollbackCommand;
use MulerTech\Database\Schema\Migration\Command\MigrationRunCommand;

return [
    MigrationGenerateCommand::class,
    MigrationRunCommand::class,
    MigrationRollbackCommand::class
];