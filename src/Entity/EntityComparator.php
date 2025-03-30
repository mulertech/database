<?php

namespace MulerTech\Database\Entity;

class EntityComparator
{
    /**
     * Compare deux objets et retourne les différences
     *
     * @param Object $old_item
     * @param Object $new_item
     * @return array|null
     */
    public function compare(Object $old_item, Object $new_item): ?array
    {
        $new_properties = $new_item->properties($new_item);
        $old_properties = $old_item->properties($old_item);
        $oldDiffProperties = array_diff_assoc($old_properties, $new_properties);
        $differences = [];

        foreach ($oldDiffProperties as $key => $value) {
            if ($value !== $new_properties[$key]) {
                $differences[$key] = [$value, $new_properties[$key]];
            }
        }

        return (!empty($differences)) ? $differences : null;
    }
}