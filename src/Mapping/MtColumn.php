<?php

     namespace MulerTech\Database\Mapping;

     use Attribute;

     /**
      * Class MtColumn
      * @package MulerTech\Database\Mapping
      * @author Sébastien Muler
      */
     #[Attribute(Attribute::TARGET_PROPERTY)]
     class MtColumn
     {
         /**
          * MtColumn constructor.
          * @param string|null $columnName
          * @param string|null $columnType
          * @param bool $isNullable
          * @param string|null $extra
          * @param string|null $columnDefault
          * @param ColumnKey|null $columnKey
          */
         public function __construct(
             public string|null $columnName = null,
             public string|null $columnType = null,
             public bool $isNullable = true,
             public string|null $extra = null,
             public string|null $columnDefault = null,
             public ColumnKey|null $columnKey = null
         )
         {}
     }