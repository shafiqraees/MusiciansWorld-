@php($sections = \App\Http\Controllers\Admin\SettingsController::SECTIONS)
<nav class="a-subnav" aria-label="Settings sections">
  <a class="{{ request()->routeIs('admin.settings') ? 'is-active' : '' }}" href="{{ route('admin.settings') }}"><svg><use href="#ai-dashboard"></use></svg>Overview</a>
  @foreach($sections as $key => $section)
    @php($route = 'admin.settings.'.$key)
    @if(($key === 'availability' && auth()->user()->hasPermission('bookings.manage')) || ($key !== 'availability' && auth()->user()->hasPermission('settings.manage')))
      <a class="{{ request()->routeIs($route) ? 'is-active' : '' }}" href="{{ route($route) }}"><svg><use href="#ai-{{ $section['icon'] }}"></use></svg>{{ $section['label'] }}</a>
    @endif
  @endforeach
  <a class="{{ request()->routeIs('admin.memberships') ? 'is-active' : '' }}" href="{{ route('admin.memberships') }}"><svg><use href="#ai-badge"></use></svg>Membership plans</a>
  <a class="{{ request()->routeIs('admin.roles') ? 'is-active' : '' }}" href="{{ route('admin.roles') }}"><svg><use href="#ai-shield"></use></svg>Roles & permissions</a>
</nav>
