<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface TaggableCacheInterface extends CacheInterface
{
    /**
     * @param string $key
     * @param array<string> $tags
     * @return void
     */
    public function tag(string $key, array $tags): void;

    /**
     * @param string $tag
     * @return void
     */
    public function invalidateTag(string $tag): void;

    /**
     * @param array<string> $tags
     * @return void
     */
    public function invalidateTags(array $tags): void;
}
