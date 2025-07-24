<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

/**
 * Trait QueryOptionsTrait
 *
 * Provides common query options (IGNORE, LOW_PRIORITY, etc.) for query builders
 *
 * @package MulerTech\Database\Query\Builder\Traits
 * @author Sébastien Muler
 */
trait QueryOptionsTrait
{
    /**
     * @var bool
     */
    protected bool $ignore = false;

    /**
     * @var bool
     */
    protected bool $lowPriority = false;

    /**
     * Enable IGNORE option
     * @return self
     */
    public function ignore(): self
    {
        $this->ignore = true;
        $this->isDirty = true;
        return $this;
    }

    /**
     * Disable IGNORE option
     * @return self
     */
    public function withoutIgnore(): self
    {
        $this->ignore = false;
        $this->isDirty = true;
        return $this;
    }

    /**
     * Enable LOW_PRIORITY option
     * @return self
     */
    public function lowPriority(): self
    {
        $this->lowPriority = true;
        $this->isDirty = true;
        return $this;
    }

    /**
     * Disable LOW_PRIORITY option
     * @return self
     */
    public function withoutLowPriority(): self
    {
        $this->lowPriority = false;
        $this->isDirty = true;
        return $this;
    }

    /**
     * Build query modifiers for SQL
     * @return array<string>
     */
    protected function buildQueryModifiers(): array
    {
        $modifiers = [];

        if ($this->lowPriority) {
            $modifiers[] = 'LOW_PRIORITY';
        }

        if ($this->ignore) {
            $modifiers[] = 'IGNORE';
        }

        return $modifiers;
    }
}
