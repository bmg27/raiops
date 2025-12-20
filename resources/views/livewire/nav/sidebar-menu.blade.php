<div data-nav-component class="{{ $this->getNavigationClasses() }}" aria-label="Sidebar Navigation">
    <!-- logo and menu button (desktop) -->
    <div
        class="d-flex w-100 justify-content-center justify-content-lg-start align-items-center bg-header border-bottom ps-1">
        <i
            id="menu-button-navigation"
            class="menu-button bi-list cursor-pointer fs-3 mt-1"
            role="button"
            tabindex="0"
            aria-expanded="{{ $navigationState === 'expanded' ? 'true' : 'false' }}"
            aria-controls="sidebarNavList"
            wire:click="toggleNav"
            wire:keydown.enter="toggleNav"
            wire:keydown.space.prevent="toggleNav"
            title="Toggle navigation"
        ></i>

        <a class="logo-details text-decoration-none" href="#">
            <img class="logo-text" src="{{ asset('images/logo.png') }}" alt="rainbo" onerror="this.style.display='none'">
        </a>
    </div>

    <!-- Navigation links -->
    <ul id="sidebarNavList" class="nav-links border-end">
        @foreach($menuItems as $menuItem)
            @if(($menuItem['type'] ?? null) === 'collapsible')
                @php $isOpen = in_array($menuItem['id'], $expandedSubmenus, true); @endphp
                <li class="{{ $isOpen ? 'expand-group' : '' }}">
                    <div class="link-icon">
                        <a href="#"
                           class="navigation-group-toggle cursor-pointer"
                           wire:click.prevent="toggleSubmenu({{ $menuItem['id'] }})"
                        >
                            <i class="bi bi-{{ $menuItem['icon'] }} "></i>
                            <span class="link-name text-nowrap">
                                {{ $menuItem['title'] }}
                            </span>
                        </a>

                        <i class="navigation-chevron bi bi-chevron-down"
                           wire:click.prevent="toggleSubmenu({{ $menuItem['id'] }})"></i>
                    </div>

                    <ul class="sub-menu">
                        <li>
                            <span class="link-name">
                                {{ $menuItem['title'] }}
                            </span>
                        </li>
                        @foreach($menuItem['children'] ?? [] as $child)
                            @if(($child['has_grandchildren'] ?? false) && !empty($child['grandchildren']))
                                {{-- Child with grandchildren (collapsible) --}}
                                <li class="grandparent-item">
                                    <a href="#"
                                       @click.prevent="$wire.toggleSubmenu({{ $child['child_id'] }})"
                                       class="navigation-item has-grandchildren {{ in_array($child['child_id'], $expandedSubmenus) ? 'expanded' : '' }}">
                                        <span>
                                            {{ $child['title'] }}
                                        </span>
                                        <i class="bi bi-chevron-down grandchild-chevron {{ in_array($child['child_id'], $expandedSubmenus) ? 'rotated' : '' }}"></i>
                                    </a>

                                    @php
                                        $isCollapsed = !$isMobile && $navigationState === 'collapsed';
                                    @endphp
                                    <ul class="grandchild-menu" @if(!$isCollapsed) style="display: {{ in_array($child['child_id'], $expandedSubmenus) ? 'block' : 'none' }};" @endif>
                                        @foreach($child['grandchildren'] as $grandchild)
                                            <li>
                                                <a href="{{ url($grandchild['url']) }}"
                                                   @click.prevent="$wire.setActiveChildId({{ $grandchild['grandchild_id'] }}).then(() => Livewire.navigate('{{ url($grandchild['url']) }}'))"
                                                   class="navigation-item grandchild-item {{ $grandchild['grandchild_id'] == $activeChildId ? 'active' : '' }}">
                                                    {{ $grandchild['title'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @else
                                {{-- Regular child without grandchildren --}}
                                <li>
                                    <a href="{{ url($child['url']) }}"
                                       @click.prevent="$wire.setActiveChildId({{ $child['child_id'] ?? -1 }}).then(() => Livewire.navigate('{{ url($child['url']) }}'))"
                                       class="navigation-item {{ ($child['child_id'] ?? -1) == $activeChildId ? 'active' : '' }}">
                                        {{ $child['title'] }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </li>
            @elseif(($menuItem['type'] ?? null) === 'link')
                <li>
                    <div class="link-icon">
                        <a href="{{ url($menuItem['url']) }}"
                           @click.prevent="$wire.setActiveChildId(-1).then(() => Livewire.navigate('{{ url($menuItem['url']) }}'))"
                           class="navigation-item {{ $activeChildId == -1 && request()->is(trim($menuItem['url'], '/')) ? 'active' : '' }}">
                            <i class="bi bi-{{ $menuItem['icon'] }}"></i>
                            <span class="link-name">
                                {{ $menuItem['title'] }}
                            </span>
                        </a>
                    </div>
                    <ul class="sub-menu">
                        <li>
                            <a class="link-name text-nowrap" href="{{ url($menuItem['url']) }}"
                               @click.prevent="$wire.setActiveChildId(-1).then(() => Livewire.navigate('{{ url($menuItem['url']) }}'))">
                                {{ $menuItem['title'] }}
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
        @endforeach
        <!-- Log out -->
        <li>
            <div class="link-icon">
                <a href="#" class="navigation-item"
                   onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="link-name text-nowrap">Log Out</span>
                </a>
            </div>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
            <ul class="sub-menu">
                <li>
                    <a href="#" class="link-name"
                       onclick="event.preventDefault();document.getElementById('logout-form').submit();"> Log Out </a>
                </li>
            </ul>
        </li>

        <!-- profile (mobile) -->
        <li class="d-lg-none">
            <div class="p-3">
                <a class="mobile-profile-link nav-link navigation-item d-flex justify-content-start align-items-center w-100 border-top border-bottom py-2"
                   href="#" aria-label="My Profile">
                    <div class="d-inline-block">
                        <img src="{{ asset('/images/avatar.png') }}" alt="avatar" onerror="this.style.display='none'">
                    </div>
                    <div class="d-inline-block pb-1">
                        <span class="me-1">{{ auth()->user()->name }}</span>
                        <i class="bi bi-arrow-right-short fs-3"></i>
                    </div>
                </a>
            </div>
        </li>
    </ul>
</div>
<script>
    function sidebarRebind() {
        if (window.sidebarRebinding) return;
        window.sidebarRebinding = true;

        setTimeout(() => window.sidebarRebinding = false, 100);

        const root = document.querySelector('[data-nav-component]');
        if (!root || !window.Livewire) {
            return;
        }
        const id = root.getAttribute('wire:id');
        const cmp = id ? window.Livewire.find(id) : null;
        if (!cmp) {
            return;
        }

        // Sync mobile state
        const nav = document.querySelector('.navigation');
        const isMobile = window.innerWidth < 992;
        if (nav && isMobile) {
            nav.classList.remove('open-on-load');
            nav.classList.add('close');
        }
        cmp.call('setIsMobile', isMobile);

        // Wire header hamburger
        const btn = document.getElementById('menu-button-header');
        if (btn && btn.dataset.navBound !== '1') {
            btn.dataset.navBound = '1';
            const handler = (e) => {
                e.preventDefault();
                const c2 = window.Livewire.find(id);
                if (c2) c2.call('toggleNav');
            };
            btn.addEventListener('click', handler);
            btn.addEventListener('touchstart', handler, {passive: false});
        }
    }

    document.addEventListener('livewire:load', sidebarRebind);
    document.addEventListener('livewire:navigated', sidebarRebind);

    window.addEventListener('resize', () => {
        clearTimeout(window.__navResizeT);
        window.__navResizeT = setTimeout(sidebarRebind, 120);
    });
</script>

