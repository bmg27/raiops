<div>
    <div class="card ">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bolder text-primary text-center text-md-start pb-2 pb-md-0">User Management </span>
        </div>
        <div class="card-header">

            <!-- Bootstrap Nav Tabs -->
            <ul class="nav nav-tabs flex-wrap">
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'users' ? 'active' : '' }}"
                       wire:click="$set('tab','users')"
                       style="cursor:pointer;">
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'roles' ? 'active' : '' }}"
                       wire:click="$set('tab','roles')"
                       style="cursor:pointer;">
                        Roles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'permissions' ? 'active' : '' }}"
                       wire:click="$set('tab','permissions')"
                       style="cursor:pointer;">
                        Permissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'menu_items' ? 'active' : '' }}"
                       wire:click="$set('tab','menu_items')"
                       style="cursor:pointer;">
                        Menu Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'organize_menu' ? 'active' : '' }}"
                       wire:click="$set('tab','organize_menu')"
                       style="cursor:pointer;">
                        Organize Menu
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="mt-3">
                @if($tab === 'users')
                    <livewire:permissions.users-index :userId="$userId"/>
                @elseif($tab === 'roles')
                    <livewire:permissions.roles-index/>
                @elseif($tab === 'permissions')
                    <livewire:permissions.permissions-index/>
                @elseif($tab === 'menu_items')
                    <livewire:permissions.menu-items-index/>
                @elseif($tab === 'organize_menu')
                    <livewire:permissions.menu-organizer/>
                @endif
            </div>
        </div>
    </div>
</div>
