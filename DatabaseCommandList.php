<?php

use MulerTech\Database\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Migration\Command\MigrationRollbackCommand;
use MulerTech\Database\Migration\Command\MigrationRunCommand;

return [
    MigrationGenerateCommand::class,
    MigrationRunCommand::class,
    MigrationRollbackCommand::class
];