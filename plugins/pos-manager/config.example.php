<?php

/**
 * POS Manager — configuration template.
 *
 * Copy this file to `config.php` (kept local, gitignored) and map it to YOUR
 * data tables. The plugin is generic: it only needs to know which table holds
 * the products being sold, where to write sales and sale lines, and the column
 * names for each piece of data.
 *
 * SECURITY: every `table` and column name below is interpolated into SQL, so it
 * MUST be a bare SQL identifier (matching ^[a-zA-Z0-9_]+$). The plugin refuses to
 * run if any name is invalid. All VALUES are always sent as bound parameters.
 */

return [

    // The products being sold and whose stock is decremented on each sale.
    'product' => [
        'table'    => 'productos',          // required
        'id'       => 'id_producto',        // required (primary key)
        'name'     => 'nombre_producto',    // required (shown in the POS)
        'price'    => 'precio_producto',    // required (unit price)
        'stock'    => 'stock_producto',     // required (decremented atomically)
        'image'    => 'imagen_producto',    // optional (thumbnail in the POS)
        'active'   => 'estado_producto',    // optional (1 = sellable)
        'category' => 'categoria_producto', // optional (reserved for filters)
    ],

    // The sale header table (one row per completed sale).
    'sale' => [
        'table'   => 'ventas',
        'id'      => 'id_venta',
        'cashier' => 'cajero_venta',         // stores the admin id who sold
        'total'   => 'total_venta',
        'payment' => 'metodo_pago_venta',
        'status'  => 'estado_venta',
        'date'    => 'date_created_venta',    // optional (set to NOW() on sale)
    ],

    // The sale line table (one row per product in a sale).
    'sale_item' => [
        'table'      => 'detalle_venta',
        'id'         => 'id_detalle',
        'sale'       => 'venta_detalle',           // FK → sale.id
        'product'    => 'producto_detalle',        // FK → product.id
        'qty'        => 'cantidad_detalle',
        'unit_price' => 'precio_unitario_detalle',
        'subtotal'   => 'subtotal_detalle',
    ],

    // Admin roles allowed to operate the register.
    'roles_allowed' => ['superadmin', 'admin', 'cashier'],

    // Allowed payment methods (shown in the POS).
    'payment_methods' => ['efectivo', 'tarjeta'],

    // Status written to a completed sale.
    'completed_status' => 'completed',
];
