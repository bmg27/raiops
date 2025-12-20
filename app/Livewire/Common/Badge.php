<?php

namespace App\Livewire\Common;

use Livewire\Component;

class Badge extends Component
{
    public string $text = '';
    public string $color = 'primary';
    public string $size = 'sm';

    public function mount(string $text = '', string $color = 'primary', string $size = 'sm')
    {
        $this->text = $text;
        $this->color = $color;
        $this->size = $size;
    }

    public function render()
    {
        return view('livewire.common.badge');
    }
}
