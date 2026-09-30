{{-- Navbar notification bell + panel (items loaded over AJAX by APP.notifications in app.js) --}}
@php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
<li class="nav-item dropdown notif-menu" id="notif_menu"
    data-url="{{ route('notifications.index') }}"
    data-read-all="{{ route('notifications.read-all') }}"
    data-read="{{ route('notifications.read', '__ID__') }}">
    <a class="nav-link" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Notifications">
        <i class="far fa-bell"></i>
        <span class="badge navbar-badge notif-count {{ $unreadCount ? '' : 'd-none' }}">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-right notif-panel" onclick="event.stopPropagation()">
        <div class="notif-head">
            <h6 class="mb-0">Notifications</h6>
            <a href="#" class="notif-read-all">Mark all as read</a>
        </div>
        <div class="notif-tabs">
            <a href="#" class="active" data-filter="unread">Unread (<span class="notif-unread-num">{{ $unreadCount }}</span>)</a>
            <a href="#" data-filter="all">All</a>
        </div>
        <div class="notif-list">
            <div class="notif-empty"><i class="fas fa-circle-notch fa-spin"></i></div>
        </div>
    </div>
</li>
