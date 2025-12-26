<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RAI Back Office</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">


    <!-- THEME INITIALIZATION - Must be first -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'rai';
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'rai');
            }
        })();
    </script>

    <!-- THEME PROTECTION - Prevents Livewire from removing theme -->
    <script>
        (function () {
            let preservedTheme = localStorage.getItem('theme') || 'rai';

            // Prevent theme removal entirely
            const originalRemoveAttribute = Element.prototype.removeAttribute;
            Element.prototype.removeAttribute = function (name) {
                if (name === 'data-bs-theme' && this === document.documentElement) {
                    return; // Simply ignore the removal
                }
                return originalRemoveAttribute.call(this, name);
            };

            // Track theme updates
            const originalSetAttribute = Element.prototype.setAttribute;
            Element.prototype.setAttribute = function (name, value) {
                if (name === 'data-bs-theme' && this === document.documentElement) {
                    if (value && value !== 'null' && value !== preservedTheme) {
                        preservedTheme = value;
                        localStorage.setItem('theme', value);
                    }
                }
                return originalSetAttribute.call(this, name, value);
            };
        })();
    </script>

    <!-- CSS Assets - Use Vite if built, otherwise use static assets -->
    @if(app()->environment('testing') || !file_exists(public_path('build/manifest.json')))
        <link href="{{ asset('css/vendor.css') }}" rel="stylesheet">
        <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
    @else
        @vite(['resources/css/vendor.css', 'resources/css/styles.css'])
    @endif

    <!-- jquery -->
    <script src="{{ asset('js/vendor/jquery-3.7.1.slim.min.js') }}" data-navigate-once></script>

    <!-- bootstrap js -->
    <script src="{{ asset('js/vendor/bootstrap.bundle.min.js') }}" data-navigate-once></script>

    <style>
        .sticky-col {
            position: sticky;
            left: 0;
            z-index: 2;
            box-shadow: 8px 0 15px rgba(0, 0, 0, 0.5);
        }
    </style>

    @livewireStyles
    @stack('styles')
</head>
<body class="bg-theme-body">

<!-- left nav -->
@livewire('nav.sidebar-menu')
<!-- end left nav --->

<!-- outermost container -->
<div class="application">
    <!-- header -->
    <header
        class="application-header bg-header header d-print-none d-flex justify-content-center justify-content-lg-between align-items-center bg-theme text-white border-bottom">

        <!-- this empty div centers the search on desktop while keeping the profile at the end -->
        <div class="d-none d-lg-block"></div>

        <!-- avatar/profile dropdown -->
        <div class="d-none d-lg-block">
            <div class="dropdown me-3">
                <a class="d-block text-white text-decoration-none dropdown-toggle" href="javascript:;"
                   data-bs-toggle="dropdown">
                    <livewire:common.avatar :url="auth()->user()->profile_photo_url" />
                    <div class="d-none d-xl-inline-block mx-2">
                        <span class="profile-name">{{  auth()->user()->name }}</span>
                    </div>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.show') }}">
                            <i class="bi-person-circle me-2"></i>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                           onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                            <i class="bi-box-arrow-right me-2"></i>
                            Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- main container -->
    <div class="main p-4">
        <!-- application content -->
        {{ $slot }}
    </div>

    <!-- top left menu button on mobile -->
    <span class="menu-button bi-list d-print-none d-lg-none cursor-pointer fs-4 ms-4" id="menu-button-header">
        <span class="rai-brand-text fw-bold" id="rai-brand-text" style="letter-spacing:-0.125rem">RAIOPS</span>
    </span>
</div>

<!-- Scripts at bottom -->
<script src="{{ asset('js/vendor/moment.min.js') }}" data-navigate-once></script>
<script src="{{ asset('js/vendor/daterangepicker.min.js') }}" data-navigate-once></script>

<!-- JS Assets - Use Vite if built, otherwise skip (no static JS needed for now) -->
@if(!app()->environment('testing') && file_exists(public_path('build/manifest.json')))
    @vite(['resources/js/app.js'])
@endif

@livewireScripts
@stack('scripts')

<!-- Avatar Update Listener -->
<script>
document.addEventListener('avatar-updated', function(event) {
    // Find the header avatar image and update its src
    const headerAvatar = document.querySelector('header img.rounded-circle');
    if (headerAvatar && event.detail.avatarUrl) {
        headerAvatar.src = event.detail.avatarUrl;
    }
});
</script>

@if(config("app.env") !== "production" && config("app.env") !== "sandbox")
    <style>
        .env-banner {
            position: fixed;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1080;
            font-size: .7rem;
            font-weight: bold;
            text-align: center;
            padding: .5rem 0;
            color: var(--bs-warning);
            background-color: var(--bs-gray-900);
            border-top: .2rem solid var(--bs-warning);
            border-bottom: .2rem solid var(--bs-warning);
        }
    </style>
    <div class="env-banner">NON-PRODUCTION</div>
@endif

</body>
</html>

