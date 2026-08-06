
<x-backpack::menu-item title="Dashboard" icon="la la-home" :link="backpack_url('dashboard')" />
<x-backpack::menu-item title="Tasks" icon="la la-calendar-check" :link="backpack_url('tasks')" />
@if(backpack_user()?->isAdmin())
    <x-backpack::menu-item title="Staff" icon="la la-users" :link="backpack_url('staff')" />
    <x-backpack::menu-item title="Summary" icon="la la-chart-pie" :link="route('summary.index')" />
    @if(backpack_user()?->email === 'elvisokohasirifi@gmail.com')
        <x-backpack::menu-item title="Laravel Logs" icon="la la-file-alt" :link="route('log.index')" />
        <x-backpack::menu-item title="Backups" icon="la la-hdd-o" :link="route('backup.index')" />
        <x-backpack::menu-item title="Activity Logs" icon="la la-stream" :link="backpack_url('activity-log')" />
    @endif
@endif
