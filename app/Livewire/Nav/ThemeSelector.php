<?php

namespace App\Livewire\Nav;

use Livewire\Attributes\On;
use Livewire\Component;

class ThemeSelector extends Component
{
    public string $theme = 'light';

    public function mount()
    {
        // Bootstrap from session or fallback to whatever <html> started with
        $this->theme = session('theme', request()->header('X-Theme', 'light'));
    }


    public function setTheme(string $theme): void
    {
        $theme = in_array($theme, ['light','rai','dark','sandbox'], true) ? $theme : 'light';

        \Log::info("[ThemeDebug] setTheme called: {$theme}");

        $this->theme = $theme;
        //session(['theme' => $theme]);

        $this->dispatch('theme-changed', theme: $theme);
    }


    public function render()
    {
        return view('livewire.nav.theme-selector');
    }
}

