<?php

/**
 * Production Manager — configuration template.
 *
 * Copy to `config.php` (kept local, gitignored) and map it to YOUR tables. The
 * plugin is generic: it manufactures `product` units by consuming the `supply`
 * (insumo) quantities defined in the `recipe` table, and logs each run in
 * `production`. Stock is decremented/incremented atomically.
 *
 * SECURITY: every `table`/column below is interpolated into SQL, so it MUST be a
 * bare SQL identifier (^[a-zA-Z0-9_]+$). All VALUES are bound parameters.
 */

return [

    // The product whose stock INCREASES when manufactured.
    'product' => [
        'table' => 'productos',
        'id'    => 'id_producto',
        'name'  => 'nombre_producto',
        'stock' => 'stock_producto',
        'yield' => 'rendimiento_producto', // optional — units a recipe batch makes (default 1)
    ],

    // The supplies / insumos whose stock DECREASES when manufacturing.
    'supply' => [
        'table' => 'insumos',
        'id'    => 'id_insumo',
        'name'  => 'nombre_insumo',
        'stock' => 'stock_insumo',
        'unit'  => 'unidad_insumo', // optional — shown in the UI
    ],

    // The recipe: how much of each supply a single product unit needs.
    'recipe' => [
        'table'   => 'recetas',
        'product' => 'producto_receta', // FK → product.id
        'supply'  => 'insumo_receta',   // FK → supply.id
        'qty'     => 'cantidad_receta', // amount of supply per 1 product unit
    ],

    // The production log (one row per manufacturing run).
    'production' => [
        'table'   => 'producciones',
        'id'      => 'id_produccion',
        'product' => 'producto_produccion',
        'qty'     => 'cantidad_produccion',
        'user'    => 'responsable_produccion', // optional (admin id)
        'status'  => 'estado_produccion',      // optional
        'date'    => 'date_created_produccion', // optional (set to NOW())
    ],

    'completed_status' => 'completed',
    'roles_allowed'    => ['superadmin', 'admin'],
];
