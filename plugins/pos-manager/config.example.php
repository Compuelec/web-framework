<?php

/**
 * POS Manager — configuration template.
 *
 * NOTE: configuration is normally done VISUALLY from the CMS (the ⚙ button in the
 * POS screen, superadmin only) and stored in the `pos_settings` table — no file
 * editing needed. This file is an OPTIONAL fallback used only when no settings
 * row exists yet. Copy it to `config.php` (kept local, gitignored) if you prefer
 * a file. The DB settings take precedence over this file.
 *
 * The plugin is generic: it only needs to know which table holds the products
 * being sold, where to write sales and sale lines, and the column names.
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
        'date'    => 'date_created_venta',     // optional (set to NOW() on sale)
        'discount' => 'descuento_venta',       // optional — enables sale discounts
    ],

    // The sale line table (one row per item in a sale).
    'sale_item' => [
        'table'      => 'detalle_venta',
        'id'         => 'id_detalle',
        'sale'       => 'venta_detalle',           // FK → sale.id
        'product'    => 'producto_detalle',        // FK → product.id
        'qty'        => 'cantidad_detalle',
        'unit_price' => 'precio_unitario_detalle',
        'subtotal'   => 'subtotal_detalle',
        'name'       => 'nombre_detalle',          // optional — enables manual line items
    ],

    // Admin roles allowed to operate the register.
    'roles_allowed' => ['superadmin', 'admin', 'cashier'],

    // Allowed payment methods (shown in the POS).
    'payment_methods' => ['efectivo', 'tarjeta'],

    // Status written to a completed sale.
    'completed_status' => 'completed',

    // Optional per-cashier permissions (managed from the ⚙ settings screen).
    //   discount: admin ids allowed to apply a discount (absent = everyone).
    //   payments: admin id => payment methods that cashier may use (absent = all).
    // Superadmins always bypass these.
    // 'permissions' => [
    //     'discount' => ['2'],
    //     'payments' => ['3' => ['efectivo']],
    // ],
];
