<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

use MulerTech\Database\Mapping\Types\FkRule;

/**
 * Class MtFk.
 *
 * @author Sébastien Muler
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MtFk
{
    /**
     * MtFk constructor.
     */
    public function __construct(
        public ?string $constraintName = null,
        public ?string $column = null,
        public ?string $referencedTable = null,
        public ?string $referencedColumn = null,
        public ?FkRule $deleteRule = null,
        public ?FkRule $updateRule = null,
    ) {
    }
}
