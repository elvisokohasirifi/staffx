<div class="nav-item dropdown pe-3">
    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
        <span class="avatar avatar-sm rounded-circle">
            <img class="avatar avatar-sm rounded-circle bg-transparent" src="{{ backpack_avatar_url(backpack_auth()->user()) }}"
                alt="{{ backpack_auth()->user()->name }}" onerror="this.style.display='none'"
                style="margin: 0;position: absolute;left: 0;z-index: 1;">
            <span class="avatar avatar-sm rounded-circle backpack-avatar-menu-container text-center">
                {{ backpack_user()->getAttribute('name') ? mb_substr(backpack_user()->name, 0, 1, 'UTF-8') : 'A' }}
            </span>
        </span>
        <div class="d-none d-xl-block ps-2">
            <div>{{ backpack_user()->name }}</div>
            <div class="mt-1 small text-muted">
                {{ backpack_user()?->isAdmin() ? 'Admin' : 'Staff' }}
                @if(session()->has('impersonator_id'))
                    · Impersonating
                @endif
            </div>
        </div>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
        @if(config('backpack.base.setup_my_account_routes'))
            <a href="{{ route('backpack.account.info') }}" class="dropdown-item"><i class="la la-user me-2"></i>{{ trans('backpack::base.my_account') }}</a>
            <div class="dropdown-divider"></div>
        @endif
        @if(session()->has('impersonator_id'))
            <form method="POST" action="{{ route('staff.stop-impersonating') }}">
                @csrf
                <button type="submit" class="dropdown-item"><i class="la la-undo me-2"></i>Stop Impersonating</button>
            </form>
            <div class="dropdown-divider"></div>
        @endif
        <a href="{{ backpack_url('logout') }}" class="dropdown-item"><i class="la la-lock me-2"></i>{{ trans('backpack::base.logout') }}</a>
    </div>
</div>
