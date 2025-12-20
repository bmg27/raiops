<?php

namespace App\Livewire\Common;

use Livewire\Component;

class Avatar extends Component
{
    public int $size = 32;
    public string $url;

    public function render()
    {
        return view('livewire.common.avatar');
    }
}

