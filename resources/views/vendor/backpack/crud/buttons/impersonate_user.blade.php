@if(backpack_user()?->canImpersonateUsers() && $entry->getKey() !== backpack_user()?->getKey())
    <form method="POST" action="{{ route('staff.impersonate', $entry->getKey()) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-link">
            <i class="la la-user-secret"></i> Impersonate
        </button>
    </form>
@endif
