<div class="theme-selector-livewire">
    <div class="d-flex justify-content-around p-2">
        <a href="javascript:;" wire:click="setTheme('light')"
           data-theme="light"
           class="btn {{ $theme==='light' ? 'btn-secondary' : 'btn-outline-secondary' }} mx-1"
           aria-pressed="{{ $theme==='light' ? 'true' : 'false' }}">
            <i class="bi-sun-fill"></i>
        </a>

        <a href="javascript:;" wire:click="setTheme('rai')"
           data-theme="rai"
           class="btn {{ $theme==='rai' ? 'btn-secondary' : 'btn-outline-secondary' }} mx-1"
           aria-pressed="{{ $theme==='rai' ? 'true' : 'false' }}">
            <i class="bi-rocket-takeoff-fill"></i>
        </a>

        <a href="javascript:;" wire:click="setTheme('dark')"
           data-theme="dark"
           class="btn {{ $theme==='dark' ? 'btn-secondary' : 'btn-outline-secondary' }} mx-1"
           aria-pressed="{{ $theme==='dark' ? 'true' : 'false' }}">
            <i class="bi-moon-stars-fill"></i>
        </a>

        {{--<a href="javascript:;" wire:click="setTheme('sandbox')"
           data-theme="sandbox"
           class="btn {{ $theme==='sandbox' ? 'btn-secondary' : 'btn-outline-secondary' }} mx-1"
           aria-pressed="{{ $theme==='sandbox' ? 'true' : 'false' }}">
            <i class="bi-code-slash"></i>
        </a>--}}
    </div>

    @push('scripts')
        <script>
            (function() {
                // Ensure we only run once
                if (window.themeManagerInitialized) {
                    //console.log('[ThemeDebug] Theme manager already initialized, skipping');
                    return;
                }
                window.themeManagerInitialized = true;

                let isUpdating = false; // Prevent recursive updates

                // 1) When user clicks a theme in this session
                window.addEventListener('theme-changed', (e) => {
                    if (isUpdating) return;
                    isUpdating = true;

                    //console.log('[ThemeDebug] theme-changed fired with:', e.detail.theme);
                    const theme = e.detail.theme || 'light';
                    const html = document.documentElement;
                    const current = html.getAttribute('data-bs-theme');

                    // Only update if different
                    if (current !== theme) {
                        html.classList.add('theme-switching');
                        html.setAttribute('data-bs-theme', theme);
                        try { localStorage.setItem('theme', theme); } catch (ex) {}
                        requestAnimationFrame(() => html.classList.remove('theme-switching'));
                    }

                    refreshButtons(theme);
                    isUpdating = false;
                });

                // 2) On first render, align Livewire with the *actual* theme
                (function initialSync(){
                    const t = localStorage.getItem('theme') || 'light';
                    //console.log('[ThemeDebug] initialSync localStorage theme:', t);
                    refreshButtons(t);
                })();

                // 3) After wire:navigate - SINGLE EVENT LISTENER
                function handleNavigated() {
                    if (isUpdating) return;

                    const stored = localStorage.getItem("theme") || "light";
                    const current = document.documentElement.getAttribute("data-bs-theme");

                    // Only change if actually different
                    if (current !== stored) {
                        //console.log("[ThemeDebug] correcting theme to:", stored);
                        document.documentElement.setAttribute("data-bs-theme", stored);
                    } else {
                        //console.log("[ThemeDebug] theme already correct:", stored);
                    }

                    refreshButtons(stored);
                }

                // Remove any existing listener before adding new one
                document.removeEventListener("livewire:navigated", handleNavigated);
                document.addEventListener("livewire:navigated", handleNavigated);

                function refreshButtons(theme) {
                    if (isUpdating) return;

                    //console.log('[ThemeDebug] refreshButtons called with:', theme);
                    document.querySelectorAll('.theme-selector-livewire [data-theme]').forEach(btn => {
                        const isActive = btn.getAttribute('data-theme') === theme;
                        btn.classList.toggle('btn-secondary', isActive);
                        btn.classList.toggle('btn-outline-secondary', !isActive);
                        btn.setAttribute('aria-pressed', String(isActive));
                    });
                }

                //console.log('[ThemeDebug] Single theme manager initialized');
            })();
        </script>
    @endpush
</div>

