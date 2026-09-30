<nav class="main-header navbar navbar-expand navbar-white navbar-light no-print">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-md-inline-block">
            <span class="nav-link navbar-business"><i class="fas fa-store-alt mr-1 text-teal"></i> {{ settings('business_name') }}</span>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto align-items-center">
        <li class="nav-item d-none d-lg-inline-block navbar-quick">
            @can('sales.create')
                <a href="{{ route('sales.create') }}" class="btn btn-sm btn-teal"><i class="fas fa-file-invoice"></i> New Invoice</a>
            @endcan
            @can('purchases.create')
                <a href="{{ route('purchases.create') }}" class="btn btn-sm btn-light-primary"><i class="fas fa-truck-loading"></i> New Purchase</a>
            @endcan
            @can('payments.create')
                <a href="{{ route('payments.create', ['type' => 'customer']) }}" class="btn btn-sm btn-light-primary"><i class="fas fa-hand-holding-usd"></i> Receive Payment</a>
            @endcan
        </li>

        @include('layouts.partials.notifications')

        <li class="nav-item d-none d-sm-inline-block">
            <span class="nav-link text-muted small"><i class="far fa-calendar-alt mr-1"></i>{{ format_date(now()) }}</span>
        </li>

        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mr-1" style="width:30px;height:30px;font-size:.8rem;font-weight:600">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <div class="px-3 py-2">
                    <div class="font-weight-600">{{ auth()->user()->name }}</div>
                    <small class="text-muted">{{ auth()->user()->getRoleNames()->implode(', ') ?: 'No role' }}</small>
                </div>
                <div class="dropdown-divider"></div>
                <a href="{{ route('profile.edit') }}" class="dropdown-item"><i class="fas fa-user-cog"></i> My Profile</a>
                @can('settings.manage')
                    <a href="{{ route('settings.edit') }}" class="dropdown-item"><i class="fas fa-cog"></i> Business Settings</a>
                @endcan
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" class="no-lock">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt text-danger"></i> Sign out</button>
                </form>
            </div>
        </li>
    </ul>
</nav>
