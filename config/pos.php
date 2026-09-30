<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permissions (grouped for the role editor)
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'Dashboard' => ['dashboard.view' => 'View dashboard'],
        'Business Settings' => ['settings.manage' => 'Manage business settings & tax rates'],
        'Users & Roles' => [
            'users.view' => 'View users', 'users.create' => 'Add users', 'users.edit' => 'Edit users',
            'users.delete' => 'Delete users', 'roles.manage' => 'Manage roles & permissions',
            'activity_log.view' => 'View activity log',
        ],
        'Products' => [
            'products.view' => 'View products', 'products.create' => 'Add products', 'products.edit' => 'Edit products',
            'products.delete' => 'Delete products', 'categories.manage' => 'Manage categories', 'units.manage' => 'Manage units',
        ],
        'Stock Adjustments' => [
            'stock_adjustments.view' => 'View stock adjustments', 'stock_adjustments.create' => 'Add stock adjustments',
            'stock_adjustments.delete' => 'Delete stock adjustments',
        ],
        'Customers' => [
            'customers.view' => 'View customers', 'customers.create' => 'Add customers', 'customers.edit' => 'Edit customers',
            'customers.delete' => 'Delete customers',
        ],
        'Suppliers' => [
            'suppliers.view' => 'View suppliers', 'suppliers.create' => 'Add suppliers', 'suppliers.edit' => 'Edit suppliers',
            'suppliers.delete' => 'Delete suppliers',
        ],
        'Sales' => [
            'sales.view' => 'View sales invoices', 'sales.create' => 'Create sales invoices', 'sales.edit' => 'Edit sales invoices',
            'sales.delete' => 'Delete sales invoices',
            'sale_returns.view' => 'View sales returns', 'sale_returns.create' => 'Create sales returns', 'sale_returns.delete' => 'Delete sales returns',
            'delivery_notes.view' => 'View delivery notes', 'delivery_notes.create' => 'Create delivery notes',
            'delivery_notes.edit' => 'Edit delivery notes', 'delivery_notes.delete' => 'Delete delivery notes',
        ],
        'Purchases' => [
            'purchase_orders.view' => 'View LPOs', 'purchase_orders.create' => 'Create LPOs', 'purchase_orders.edit' => 'Edit / cancel LPOs',
            'purchase_orders.delete' => 'Delete LPOs',
            'purchases.view' => 'View purchases', 'purchases.create' => 'Create purchases', 'purchases.edit' => 'Edit purchases',
            'purchases.delete' => 'Delete purchases',
            'purchase_returns.view' => 'View purchase returns', 'purchase_returns.create' => 'Create purchase returns',
            'purchase_returns.delete' => 'Delete purchase returns',
        ],
        'Expenses' => [
            'expenses.view' => 'View expenses', 'expenses.create' => 'Add expenses', 'expenses.edit' => 'Edit expenses',
            'expenses.delete' => 'Delete expenses', 'expense_categories.manage' => 'Manage expense categories',
        ],
        'Payments' => [
            'payments.view' => 'View payments', 'payments.create' => 'Record payments', 'payments.delete' => 'Delete payments',
        ],
        'Reports' => [
            'reports.sales' => 'Sales reports', 'reports.purchases' => 'Purchase reports', 'reports.inventory' => 'Stock reports',
            'reports.expenses' => 'Expense reports', 'reports.contacts' => 'Customer & supplier reports', 'reports.profit' => 'Profit report',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default roles. "*" = every permission, "*.view" = every view permission, "!perm" excludes.
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'Admin' => ['*'],
        'Manager' => [
            'dashboard.view', 'activity_log.view',
            'products.*', 'categories.manage', 'units.manage', 'stock_adjustments.*',
            'customers.*', 'suppliers.*', 'sales.*', 'sale_returns.*', 'delivery_notes.*',
            'purchase_orders.*', 'purchases.*', 'purchase_returns.*',
            'expenses.*', 'expense_categories.manage', 'payments.*', 'reports.*',
        ],
        'Accountant' => [
            'dashboard.view', 'products.view', 'stock_adjustments.view',
            'customers.view', 'customers.create', 'customers.edit', 'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'sales.view', 'sale_returns.view', 'delivery_notes.view', 'purchase_orders.view', 'purchases.view', 'purchase_returns.view',
            'expenses.*', 'expense_categories.manage', 'payments.*', 'reports.*',
        ],
        'Viewer' => ['*.view', 'reports.*', '!users.view', '!activity_log.view'],
    ],

    // Roles that bypass every permission check.
    'super_roles' => ['Admin'],

    /*
    |--------------------------------------------------------------------------
    | Payment methods catalogue. Which ones are offered is chosen in
    | Business Settings (enabled_payment_methods); keys are stored on records.
    |--------------------------------------------------------------------------
    */
    'payment_methods' => [
        'cash' => 'Cash',
        'bank' => 'Bank Transfer',
        'mobile_money' => 'Mobile Money',
        'cheque' => 'Cheque',
        'card' => 'Card',
    ],

    // Default stock adjustment reasons (overridable in Business Settings).
    'stock_adjustment_reasons' => [
        'Damaged goods', 'Expired stock', 'Stock count variance', 'Theft / loss',
        'Internal use', 'Found / surplus', 'Opening balance correction', 'Other',
    ],

    // Receivable / payable aging: upper bounds (days) of the first three buckets; the fourth is open-ended.
    'aging_boundaries' => [30, 60, 90],
];
