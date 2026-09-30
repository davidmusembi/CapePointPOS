@php
    /*
     * Sidebar definition. Each item: label, icon, route | children, can (permission), active (route patterns).
     * Section headers: ['header' => 'Sales'].
     */
    $menu = [
        ['label' => 'Dashboard', 'icon' => 'fas fa-th-large', 'route' => 'dashboard', 'can' => 'dashboard.view', 'active' => ['dashboard']],

        ['header' => 'Sales'],
        ['label' => 'Sales', 'icon' => 'fas fa-file-invoice-dollar', 'active' => ['sales.*'], 'children' => [
            ['label' => 'All Invoices', 'route' => 'sales.index', 'can' => 'sales.view', 'active' => ['sales.index', 'sales.show', 'sales.edit']],
            ['label' => 'Create Invoice', 'route' => 'sales.create', 'can' => 'sales.create'],
            ['label' => 'Sales Due', 'route' => 'sales.due', 'can' => 'sales.view'],
        ]],
        ['label' => 'Sales Returns', 'icon' => 'fas fa-undo-alt', 'route' => 'sale-returns.index', 'can' => 'sale_returns.view', 'active' => ['sale-returns.*']],

        ['header' => 'Purchases'],
        ['label' => 'Purchase Orders', 'icon' => 'fas fa-clipboard-list', 'route' => 'purchase-orders.index', 'can' => 'purchase_orders.view', 'active' => ['purchase-orders.*']],
        ['label' => 'Purchases', 'icon' => 'fas fa-truck-loading', 'active' => ['purchases.*'], 'children' => [
            ['label' => 'All Purchases', 'route' => 'purchases.index', 'can' => 'purchases.view', 'active' => ['purchases.index', 'purchases.show', 'purchases.edit']],
            ['label' => 'Add Purchase / GRN', 'route' => 'purchases.create', 'can' => 'purchases.create'],
        ]],
        ['label' => 'Purchase Returns', 'icon' => 'fas fa-reply-all', 'route' => 'purchase-returns.index', 'can' => 'purchase_returns.view', 'active' => ['purchase-returns.*']],

        ['header' => 'Inventory'],
        ['label' => 'Products', 'icon' => 'fas fa-boxes', 'active' => ['products.*', 'categories.*', 'units.*'], 'children' => [
            ['label' => 'All Products', 'route' => 'products.index', 'can' => 'products.view', 'active' => ['products.index', 'products.show', 'products.edit']],
            ['label' => 'Add Product', 'route' => 'products.create', 'can' => 'products.create'],
            ['label' => 'Low Stock Alerts', 'route' => 'products.low-stock', 'can' => 'products.view'],
            ['label' => 'Categories', 'route' => 'categories.index', 'can' => 'categories.manage', 'active' => ['categories.*']],
            ['label' => 'Units', 'route' => 'units.index', 'can' => 'units.manage', 'active' => ['units.*']],
        ]],
        ['label' => 'Stock Adjustments', 'icon' => 'fas fa-sliders-h', 'route' => 'stock-adjustments.index', 'can' => 'stock_adjustments.view', 'active' => ['stock-adjustments.*']],

        ['header' => 'Contacts'],
        ['label' => 'Customers', 'icon' => 'fas fa-user-friends', 'route' => 'customers.index', 'can' => 'customers.view', 'active' => ['customers.*']],
        ['label' => 'Suppliers', 'icon' => 'fas fa-industry', 'route' => 'suppliers.index', 'can' => 'suppliers.view', 'active' => ['suppliers.*']],

        ['header' => 'Finance'],
        ['label' => 'Payments', 'icon' => 'fas fa-money-check-alt', 'active' => ['payments.*'], 'children' => [
            ['label' => 'Customer Receipts', 'route' => ['payments.index', ['type' => 'customer']], 'can' => 'payments.view', 'active_query' => ['type' => 'customer']],
            ['label' => 'Supplier Payments', 'route' => ['payments.index', ['type' => 'supplier']], 'can' => 'payments.view', 'active_query' => ['type' => 'supplier']],
            ['label' => 'Receive Payment', 'route' => ['payments.create', ['type' => 'customer']], 'can' => 'payments.create', 'active' => ['payments.create']],
        ]],
        ['label' => 'Expenses', 'icon' => 'fas fa-wallet', 'active' => ['expenses.*', 'expense-categories.*'], 'children' => [
            ['label' => 'All Expenses', 'route' => 'expenses.index', 'can' => 'expenses.view', 'active' => ['expenses.index', 'expenses.edit']],
            ['label' => 'Add Expense', 'route' => 'expenses.create', 'can' => 'expenses.create'],
            ['label' => 'Expense Categories', 'route' => 'expense-categories.index', 'can' => 'expense_categories.manage', 'active' => ['expense-categories.*']],
        ]],

        ['header' => 'Reports'],
        ['label' => 'Reports', 'icon' => 'fas fa-chart-pie', 'active' => ['reports.*'], 'children' => [
            ['label' => 'Purchase & Sale', 'route' => 'reports.purchase-sale', 'can' => 'reports.sales'],
            ['label' => 'Sales Summary', 'route' => 'reports.sales', 'can' => 'reports.sales'],
            ['label' => 'Sales Due (Receivables)', 'route' => 'reports.sales-due', 'can' => 'reports.sales'],
            ['label' => 'Aging Report', 'route' => 'reports.aging', 'can' => 'reports.sales'],
            ['label' => 'Sales Returns', 'route' => 'reports.sale-returns', 'can' => 'reports.sales'],
            ['label' => 'Purchase Summary', 'route' => 'reports.purchases', 'can' => 'reports.purchases'],
            ['label' => 'Purchase Returns', 'route' => 'reports.purchase-returns', 'can' => 'reports.purchases'],
            ['label' => 'Supplier Payables', 'route' => 'reports.payables', 'can' => 'reports.purchases'],
            ['label' => 'Expense Report', 'route' => 'reports.expenses', 'can' => 'reports.expenses'],
            ['label' => 'Stock & Valuation', 'route' => 'reports.stock', 'can' => 'reports.inventory'],
            ['label' => 'Customer & Supplier', 'route' => 'reports.contacts', 'can' => 'reports.contacts'],
            ['label' => 'Profit Snapshot', 'route' => 'reports.profit', 'can' => 'reports.profit'],
        ]],

        ['header' => 'Settings'],
        ['label' => 'Settings', 'icon' => 'fas fa-cogs', 'active' => ['settings.*', 'tax-rates.*', 'users.*', 'roles.*', 'activity-log.*'], 'children' => [
            ['label' => 'Business Settings', 'route' => 'settings.edit', 'can' => 'settings.manage', 'active' => ['settings.*']],
            ['label' => 'Tax Rates', 'route' => 'tax-rates.index', 'can' => 'settings.manage', 'active' => ['tax-rates.*']],
            ['label' => 'Users', 'route' => 'users.index', 'can' => 'users.view', 'active' => ['users.*']],
            ['label' => 'Roles & Permissions', 'route' => 'roles.index', 'can' => 'roles.manage', 'active' => ['roles.*']],
            ['label' => 'Activity Log', 'route' => 'activity-log.index', 'can' => 'activity_log.view', 'active' => ['activity-log.*']],
        ]],
    ];

    $user = auth()->user();
    $url = function ($route) {
        return is_array($route) ? route($route[0], $route[1]) : route($route);
    };
    $isActive = function ($item) {
        if (isset($item['active_query'])) {
            $routeName = is_array($item['route']) ? $item['route'][0] : $item['route'];
            if (! request()->routeIs($routeName)) {
                return false;
            }
            foreach ($item['active_query'] as $k => $v) {
                if (request()->query($k, 'customer') !== $v) {
                    return false;
                }
            }

            return true;
        }
        $patterns = $item['active'] ?? [is_array($item['route'] ?? null) ? $item['route'][0] : ($item['route'] ?? '')];

        return request()->routeIs(...$patterns);
    };

    // Drop children the user cannot access, then drop empty parents / orphan headers.
    $visible = [];
    foreach ($menu as $item) {
        if (isset($item['children'])) {
            $item['children'] = array_values(array_filter($item['children'], fn ($c) => empty($c['can']) || $user->can($c['can'])));
            if (! $item['children']) {
                continue;
            }
        } elseif (! isset($item['header']) && ! empty($item['can']) && ! $user->can($item['can'])) {
            continue;
        }
        $visible[] = $item;
    }
    $visible = array_values(array_filter($visible, function ($item, $i) use ($visible) {
        return ! isset($item['header']) || (isset($visible[$i + 1]) && ! isset($visible[$i + 1]['header']));
    }, ARRAY_FILTER_USE_BOTH));
@endphp

<aside class="main-sidebar sidebar-dark-primary elevation-0 no-print">
    <a href="{{ route('dashboard') }}" class="brand-link" title="{{ settings('business_name') }}">
        @include('layouts.partials.brand', ['size' => 40])
        <span class="brand-text">{{ settings('business_name') }}</span>
    </a>

    <div class="sidebar">
        <nav class="mt-2 mb-4">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                @foreach ($visible as $item)
                    @if (isset($item['header']))
                        <li class="nav-header">{{ $item['header'] }}</li>
                    @elseif (isset($item['children']))
                        @php $open = $isActive($item); @endphp
                        <li class="nav-item {{ $open ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $open ? 'active' : '' }}">
                                <i class="nav-icon {{ $item['icon'] }}"></i>
                                <p>{{ $item['label'] }} <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                @foreach ($item['children'] as $child)
                                    <li class="nav-item">
                                        <a href="{{ $url($child['route']) }}" class="nav-link {{ $isActive($child) ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>{{ $child['label'] }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ $url($item['route']) }}" class="nav-link {{ $isActive($item) ? 'active' : '' }}">
                                <i class="nav-icon {{ $item['icon'] }}"></i>
                                <p>{{ $item['label'] }}</p>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
