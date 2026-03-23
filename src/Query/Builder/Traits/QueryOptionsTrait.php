<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

/**
 * Trait QueryOptionsTrait.
 *
 * Provides common query options (IGNORE, LOW_PRIORITY, etc.) for query builders
 *
 * @author Sébastien Muler
 */
trait QueryOptionsTrait
{
    protected bool $ignore = false;

    protected bool $lowPriority = false;

    /**
     * Enable IGNORE option.
     */
    public function ignore(): self
    {
        $this->ignore = true;
        $this->isDirty = true;

        return $this;
    }

    /**
     * Disable IGNORE option.
     */
    public function withoutIgnore(): self
    {
        $this->ignore = false;
        $this->isDirty = true;

        return $this;
    }

    /**
     * Enable LOW_PRIORITY option.
     */
    public function lowPriority(): self
    {
        $this->lowPriority = true;
        $this->isDirty = true;

        return $this;
    }

    /**
     * Disable LOW_PRIORITY option.
     */
    public function withoutLowPriority(): self
    {
        $this->lowPriority = false;
        $this->isDirty = true;

        return $this;
    }

    /**
     * Build query modifiers for SQL.
     *
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
