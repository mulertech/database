<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

/**
 * @author Sébastien Muler
 */
interface TaggableCacheInterface extends CacheInterface
{
    /**
     * @param array<string> $tags
     */
    public function tag(string $key, array $tags): void;

    public function invalidateTag(string $tag): void;

    /**
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): void;
}
