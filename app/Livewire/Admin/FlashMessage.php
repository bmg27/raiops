<?php
namespace App\Livewire\Admin;

use Livewire\Attributes\On;
use Livewire\Component;

class FlashMessage extends Component
{
    public $fade = false;
    public $noclass = false;
    public $modal = false;

    public $error;
    public $message;
    public $errors = [];
    public $messageKey = 0; // Force re-render by changing this key
    
    // Modal properties
    public $showModal = false;
    public $modalType = '';
    public $modalMessage = '';

    public function mount()
    {
        // Normalize string props into booleans
        $this->fade = filter_var($this->fade, FILTER_VALIDATE_BOOLEAN);
        $this->noclass = filter_var($this->noclass, FILTER_VALIDATE_BOOLEAN);
        $this->modal = filter_var($this->modal, FILTER_VALIDATE_BOOLEAN);

        // Pick up Laravel flashes (page load)
        $this->error = session('error');
        $this->message = session('message');

        // Ensure errors is an array
        if (!is_array($this->errors)) {
            $this->errors = [];
        }
    }

    #[On('notify')]
    public function showNotification($type = 'success', $message = '', $modal = false)
    {
        if ($this->modal) {
            // Show as modal
            $this->modalType = $type;
            $this->modalMessage = $message;
            $this->showModal = true;
            
            // Auto-hide if fade is enabled
            if ($this->fade) {
                $this->dispatch('hideModalAfterDelay');
            }
        } else {
            // Show as regular flash message
            if ($type === 'danger') {
                $this->error = $message;
                $this->message = null;
            } else {
                $this->message = $message;
                $this->error = null;
            }
            
            // Increment key to force re-render
            $this->messageKey++;
        }
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->modalType = '';
        $this->modalMessage = '';
    }
    
    #[On('hideModalAfterDelay')]
    public function hideModalAfterDelay()
    {
        sleep(3);
        $this->closeModal();
    }
    
    // Method to show modal directly (can be called from other components)
    #[On('showModal')]
    public function showModal($type = 'success', $message = '')
    {
        $this->modalType = $type;
        $this->modalMessage = $message;
        $this->showModal = true;
        
        // Auto-hide if fade is enabled
        if ($this->fade) {
            $this->dispatch('hideModalAfterDelay');
        }
    }

    public function render()
    {
        return view('livewire.admin.flash-message', [
            'error'   => $this->error,
            'message' => $this->message,
            'noclass' => $this->noclass,
            'fade'    => $this->fade,
            'modal'   => $this->modal,
            'showModal' => $this->showModal,
            'modalType' => $this->modalType,
            'modalMessage' => $this->modalMessage,
            'messageKey' => $this->messageKey,
        ]);

    }
}

