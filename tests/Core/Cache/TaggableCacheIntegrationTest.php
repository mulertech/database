<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\MemoryCache;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Core\Cache\ResultSetCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for taggable cache functionality across different cache implementations
 */
#[CoversClass(MemoryCache::class)]
#[CoversClass(MetadataCache::class)]
#[CoversClass(ResultSetCache::class)]
#[CoversClass(CacheFactory::class)]
final class TaggableCacheIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        CacheFactory::reset();
    }

    public function testMemoryCacheTaggingIntegration(): void
    {
        $cache = CacheFactory::createMemoryCache('test_memory');
        
        // Set up test data with different tags
        $cache->set('user:1', ['id' => 1, 'name' => 'John']);
        $cache->set('user:2', ['id' => 2, 'name' => 'Jane']);
        $cache->set('post:1', ['id' => 1, 'title' => 'Post 1']);
        $cache->set('post:2', ['id' => 2, 'title' => 'Post 2']);
        
        // Tag the data
        $cache->tag('user:1', ['users', 'active_users']);
        $cache->tag('user:2', ['users', 'inactive_users']);
        $cache->tag('post:1', ['posts', 'published']);
        $cache->tag('post:2', ['posts', 'draft']);
        
        // Verify all data exists
        $this->assertNotNull($cache->get('user:1'));
        $this->assertNotNull($cache->get('user:2'));
        $this->assertNotNull($cache->get('post:1'));
        $this->assertNotNull($cache->get('post:2'));
        
        // Invalidate all users
        $cache->invalidateTag('users');
        
        $this->assertNull($cache->get('user:1'));
        $this->assertNull($cache->get('user:2'));
        $this->assertNotNull($cache->get('post:1'));
        $this->assertNotNull($cache->get('post:2'));
        
        // Invalidate published content
        $cache->invalidateTag('published');
        
        $this->assertNull($cache->get('post:1'));
        $this->assertNotNull($cache->get('post:2'));
    }

    public function testMetadataCacheTaggingIntegration(): void
    {
        $cache = CacheFactory::createMetadataCache('test_metadata');
        
        // Set entity metadata with automatic tagging
        $cache->setEntityMetadata('User', ['table' => 'users']);
        $cache->setEntityMetadata('Post', ['table' => 'posts']);
        
        // Set property metadata with automatic tagging
        $cache->setPropertyMetadata('User', 'name', ['column' => 'user_name']);
        $cache->setPropertyMetadata('User', 'email', ['column' => 'user_email']);
        $cache->setPropertyMetadata('Post', 'title', ['column' => 'post_title']);
        
        // Verify all metadata exists
        $this->assertNotNull($cache->get('entity:User'));
        $this->assertNotNull($cache->get('entity:Post'));
        $this->assertNotNull($cache->get('property:User:name'));
        $this->assertNotNull($cache->get('property:User:email'));
        $this->assertNotNull($cache->get('property:Post:title'));
        
        // Invalidate all entity metadata
        $cache->invalidateTag('entity_metadata');
        
        $this->assertNull($cache->get('entity:User'));
        $this->assertNull($cache->get('entity:Post'));
        $this->assertNotNull($cache->get('property:User:name')); // Property metadata should remain
        
        // Invalidate User-specific property metadata
        $cache->invalidateTag('User');
        
        $this->assertNull($cache->get('property:User:name'));
        $this->assertNull($cache->get('property:User:email'));
        $this->assertNotNull($cache->get('property:Post:title')); // Post metadata should remain
    }

    public function testResultSetCacheTaggingIntegration(): void
    {
        $resultSetCache = CacheFactory::createResultSetCache('test_resultset');
        
        // Simulate result sets from different tables
        $users = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];
        $posts = [
            ['id' => 1, 'title' => 'Post 1', 'user_id' => 1],
            ['id' => 2, 'title' => 'Post 2', 'user_id' => 2]
        ];
        
        $resultSetCache->set('query:users:all', $users);
        $resultSetCache->set('query:posts:all', $posts);
        $resultSetCache->set('query:users:active', [$users[0]]);
        
        // Tag with table names
        $resultSetCache->tag('query:users:all', ['table:users']);
        $resultSetCache->tag('query:posts:all', ['table:posts']);  
        $resultSetCache->tag('query:users:active', ['table:users']);
        
        // Verify all data exists
        $this->assertEquals($users, $resultSetCache->get('query:users:all'));
        $this->assertEquals($posts, $resultSetCache->get('query:posts:all'));
        $this->assertEquals([$users[0]], $resultSetCache->get('query:users:active'));
        
        // Invalidate all users table results
        $resultSetCache->invalidateTable('users');
        
        $this->assertNull($resultSetCache->get('query:users:all'));
        $this->assertNull($resultSetCache->get('query:users:active'));
        $this->assertEquals($posts, $resultSetCache->get('query:posts:all'));
        
        // Invalidate multiple tables
        $resultSetCache->set('query:users:reload', $users);
        $resultSetCache->tag('query:users:reload', ['table:users']);
        
        $resultSetCache->invalidateTables(['users', 'posts']);
        
        $this->assertNull($resultSetCache->get('query:users:reload'));
        $this->assertNull($resultSetCache->get('query:posts:all'));
    }

    public function testCrossCacheTaggingConsistency(): void
    {
        $memoryCache = CacheFactory::createMemoryCache('cross_memory');
        $metadataCache = CacheFactory::createMetadataCache('cross_metadata');
        $resultSetCache = CacheFactory::createResultSetCache('cross_resultset');
        
        // Set data in different caches with similar tags
        $memoryCache->set('temp:user:1', 'temporary user data');
        $memoryCache->tag('temp:user:1', ['user_data']);
        
        $metadataCache->setPropertyMetadata('User', 'id', ['column' => 'user_id']);
        // MetadataCache automatically tags with 'User' and 'property_metadata'
        
        $resultSetCache->set('results:user:search', [['id' => 1, 'name' => 'John']]);
        $resultSetCache->tag('results:user:search', ['user_data']);
        
        // Verify initial state
        $this->assertNotNull($memoryCache->get('temp:user:1'));
        $this->assertNotNull($metadataCache->get('property:User:id'));
        $this->assertNotNull($resultSetCache->get('results:user:search'));
        
        // Each cache should handle its own tags independently
        $memoryCache->invalidateTag('user_data');
        
        $this->assertNull($memoryCache->get('temp:user:1'));
        $this->assertNotNull($metadataCache->get('property:User:id'));
        
        // ResultSetCache should also invalidate the tagged data
        $resultSetCache->invalidateTag('user_data');
        $this->assertNull($resultSetCache->get('results:user:search'));
    }

    public function testTaggingWithComplexDataStructures(): void
    {
        $cache = CacheFactory::createMemoryCache('complex_data');
        
        $complexData = [
            'users' => [
                1 => ['id' => 1, 'name' => 'John', 'posts' => [1, 2]],
                2 => ['id' => 2, 'name' => 'Jane', 'posts' => [3]]
            ],
            'posts' => [
                1 => ['id' => 1, 'title' => 'Post 1', 'author_id' => 1],
                2 => ['id' => 2, 'title' => 'Post 2', 'author_id' => 1],
                3 => ['id' => 3, 'title' => 'Post 3', 'author_id' => 2]
            ],
            'meta' => [
                'total_users' => 2,
                'total_posts' => 3,
                'last_updated' => time()
            ]
        ];
        
        $cache->set('complex:dataset', $complexData);
        $cache->tag('complex:dataset', ['users', 'posts', 'statistics']);
        
        // Verify complex data is stored and retrieved correctly
        $retrieved = $cache->get('complex:dataset');
        $this->assertEquals($complexData, $retrieved);
        
        // Invalidate and verify
        $cache->invalidateTag('statistics');
        $this->assertNull($cache->get('complex:dataset'));
    }

    public function testTaggingWithEmptyAndNullValues(): void
    {
        $cache = CacheFactory::createMemoryCache('edge_cases');
        
        // Test with null values
        $cache->set('null_value', null);
        $cache->tag('null_value', ['nullable_data']);
        
        // Test with empty arrays
        $cache->set('empty_array', []);
        $cache->tag('empty_array', ['empty_data']);
        
        // Test with empty strings
        $cache->set('empty_string', '');
        $cache->tag('empty_string', ['string_data']);
        
        // Test with zero values
        $cache->set('zero_int', 0);
        $cache->set('zero_float', 0.0);
        $cache->tag('zero_int', ['numeric_data']);
        $cache->tag('zero_float', ['numeric_data']);
        
        // Verify all data exists
        $this->assertNull($cache->get('null_value'));
        $this->assertEquals([], $cache->get('empty_array'));
        $this->assertEquals('', $cache->get('empty_string'));
        $this->assertEquals(0, $cache->get('zero_int'));
        $this->assertEquals(0.0, $cache->get('zero_float'));
        
        // Invalidate and verify proper handling
        $cache->invalidateTag('numeric_data');
        
        $this->assertNull($cache->get('zero_int'));
        $this->assertNull($cache->get('zero_float'));
        $this->assertNull($cache->get('null_value')); // Should still be null
        $this->assertEquals([], $cache->get('empty_array'));
        $this->assertEquals('', $cache->get('empty_string'));
    }

    public function testConcurrentTagOperations(): void
    {
        $cache = CacheFactory::createMemoryCache('concurrent');
        
        // Simulate concurrent operations on same data
        for ($i = 1; $i <= 10; $i++) {
            $cache->set("item:$i", "data $i");
            $cache->tag("item:$i", ['batch_1', 'all_items']);
        }
        
        for ($i = 11; $i <= 20; $i++) {
            $cache->set("item:$i", "data $i");
            $cache->tag("item:$i", ['batch_2', 'all_items']);
        }
        
        // Verify all items exist
        for ($i = 1; $i <= 20; $i++) {
            $this->assertEquals("data $i", $cache->get("item:$i"));
        }
        
        // Invalidate batch_1
        $cache->invalidateTag('batch_1');
        
        // Verify only batch_1 items are gone
        for ($i = 1; $i <= 10; $i++) {
            $this->assertNull($cache->get("item:$i"));
        }
        for ($i = 11; $i <= 20; $i++) {
            $this->assertEquals("data $i", $cache->get("item:$i"));
        }
        
        // Invalidate all remaining items
        $cache->invalidateTag('all_items');
        
        for ($i = 11; $i <= 20; $i++) {
            $this->assertNull($cache->get("item:$i"));
        }
    }

    public function testTagInvalidationCascading(): void
    {
        $cache = CacheFactory::createMemoryCache('cascading');
        
        // Create hierarchical tagging structure
        $cache->set('category:1', 'Technology');
        $cache->set('category:2', 'Sports');
        $cache->set('post:1', 'Tech Post 1');
        $cache->set('post:2', 'Tech Post 2');
        $cache->set('post:3', 'Sports Post 1');
        $cache->set('comment:1', 'Comment on Tech Post 1');
        $cache->set('comment:2', 'Comment on Sports Post 1');
        
        // Tag with hierarchical relationships
        $cache->tag('category:1', ['categories', 'tech']);
        $cache->tag('category:2', ['categories', 'sports']);
        $cache->tag('post:1', ['posts', 'tech_posts', 'tech']);
        $cache->tag('post:2', ['posts', 'tech_posts', 'tech']);
        $cache->tag('post:3', ['posts', 'sports_posts', 'sports']);
        $cache->tag('comment:1', ['comments', 'tech_comments', 'tech']);
        $cache->tag('comment:2', ['comments', 'sports_comments', 'sports']);
        
        // Invalidate all tech-related content
        $cache->invalidateTag('tech');
        
        // Verify only tech content is invalidated
        $this->assertNull($cache->get('category:1'));
        $this->assertNotNull($cache->get('category:2'));
        $this->assertNull($cache->get('post:1'));
        $this->assertNull($cache->get('post:2'));
        $this->assertNotNull($cache->get('post:3'));
        $this->assertNull($cache->get('comment:1'));
        $this->assertNotNull($cache->get('comment:2'));
        
        // Invalidate all posts
        $cache->invalidateTag('posts');
        
        // Only the remaining post should be invalidated
        $this->assertNull($cache->get('post:3'));
        $this->assertNotNull($cache->get('category:2'));
        $this->assertNotNull($cache->get('comment:2'));
    }

    public function testFactoryIntegrationWithTagging(): void
    {
        // Test that factory-created caches maintain tagging functionality
        $memoryCache1 = CacheFactory::createMemoryCache('factory_memory_1');
        $memoryCache2 = CacheFactory::createMemoryCache('factory_memory_2');
        $metadataCache = CacheFactory::createMetadataCache('factory_metadata');
        
        // Set data in each cache
        $memoryCache1->set('data:1', 'cache 1 data');
        $memoryCache1->tag('data:1', ['shared_tag']);
        
        $memoryCache2->set('data:2', 'cache 2 data');
        $memoryCache2->tag('data:2', ['shared_tag']);
        
        $metadataCache->setEntityMetadata('TestEntity', ['table' => 'test']);
        
        // Verify caches are independent
        $memoryCache1->invalidateTag('shared_tag');
        
        $this->assertNull($memoryCache1->get('data:1'));
        $this->assertNotNull($memoryCache2->get('data:2')); // Different cache instance
        $this->assertNotNull($metadataCache->get('entity:TestEntity'));
        
        // Test same cache name returns same instance
        $sameCache = CacheFactory::createMemoryCache('factory_memory_1');
        $this->assertSame($memoryCache1, $sameCache);
    }

    public function testStatisticsWithTaggedOperations(): void
    {
        $cache = CacheFactory::createMemoryCache('stats_test');
        
        // Perform operations that affect statistics
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $cache->tag('key1', ['tag1']);
        $cache->tag('key2', ['tag1', 'tag2']);
        $cache->tag('key3', ['tag2']);
        
        // Perform gets and misses
        $cache->get('key1'); // hit
        $cache->get('key2'); // hit
        $cache->get('nonexistent'); // miss
        
        $initialStats = $cache->getStatistics();
        $this->assertEquals(2, $initialStats['hits']);
        $this->assertEquals(1, $initialStats['misses']);
        $this->assertEquals(3, $initialStats['writes']);
        $this->assertEquals(0, $initialStats['deletes']);
        
        // Invalidate by tag (should count as deletes)
        $cache->invalidateTag('tag1');
        
        $statsAfterInvalidation = $cache->getStatistics();
        $this->assertEquals(2, $statsAfterInvalidation['deletes']); // key1 and key2 deleted
        $this->assertEquals(1, $statsAfterInvalidation['size']); // Only key3 remains
    }
}