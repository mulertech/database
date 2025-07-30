<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Comparator;

/**
 * Compares different types of values for change detection
 */
class ValueComparator
{
    /**
     * Compare entity references
     * @param array{__entity__: class-string, __id__: mixed, __hash__: int} $value1
     * @param array{__entity__: class-string, __id__: mixed, __hash__: int} $value2
     * @return bool
     */
    public function compareEntityReferences(array $value1, array $value2): bool
    {
        if ($value1['__entity__'] !== $value2['__entity__']) {
            return false;
        }

        $id1 = $value1['__id__'];
        $id2 = $value2['__id__'];

        if ($id1 !== null && $id2 !== null) {
            return $id1 === $id2;
        }

        if (($id1 === null) !== ($id2 === null)) {
            return false;
        }

        return $value1['__hash__'] === $value2['__hash__'];
    }

    /**
     * Compare object references
     * @param array{__object__: class-string, __hash__: int} $value1
     * @param array{__object__: class-string, __hash__: int} $value2
     * @return bool
     */
    public function compareObjectReferences(array $value1, array $value2): bool
    {
        if ($value1['__object__'] !== $value2['__object__']) {
            return false;
        }

        return $value1['__hash__'] === $value2['__hash__'];
    }

    /**
     * Compare collections
     * @param array{
     *     __collection__: bool,
     *     __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>
     *     } $value1
     * @param array{
     *     __collection__: bool,
     *     __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>
     *     } $value2
     * @return bool
     */
    public function compareCollections(array $value1, array $value2): bool
    {
        return $this->collectionsAreEqual($value1['__items__'], $value2['__items__']);
    }

    /**
     * @param array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}> $items1
     * @param array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}> $items2
     * @return bool
     */
    private function collectionsAreEqual(array $items1, array $items2): bool
    {
        if (count($items1) !== count($items2)) {
            return false;
        }

        $sort = static function ($classA, $classB) {
            $classCompare = strcmp($classA['__entity__'] ?? '', $classB['__entity__'] ?? '');
            if ($classCompare !== 0) {
                return $classCompare;
            }
            return ($classA['__id__'] ?? 0) <=> ($classB['__id__'] ?? 0);
        };

        usort($items1, $sort);
        usort($items2, $sort);

        return $items1 === $items2;
    }
}
