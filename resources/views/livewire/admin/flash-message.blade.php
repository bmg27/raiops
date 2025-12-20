{{--
    Flash Message Component

    Usage:
    <livewire:admin.flash-message fade="yes" modal="true" />

    Parameters:
    - fade: "yes" or "no" - auto-hide after timeout
    - modal: "true" or "false" - display as modal instead of inline alert
    - noclass: "true" or "false" - remove row wrapper classes
--}}
<div>
    @if($modal ?? false)
        {{-- Modal Flash Messages --}}
        @if($showModal)
            <div class="modal fade show d-block" 
                 tabindex="-1" 
                 style="background:rgba(0,0,0,0.5);"
                 wire:click.self="closeModal"
                 wire:keydown.escape.window="closeModal">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header {{ $modalType === 'danger' ? 'border-danger' : 'border-success' }}">
                            <h5 class="modal-title d-flex align-items-center">
                                <i class="bi {{ $modalType === 'danger' ? 'bi-exclamation-triangle text-danger' : 'bi-check-circle text-success' }} me-2"></i>
                                <span>{{ $modalType === 'danger' ? 'Error' : 'Success' }}</span>
                            </h5>
                            <button type="button" class="btn-close" wire:click="closeModal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">{{ $modalMessage }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeModal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @elseif($error || $message)
        {{-- Regular Flash Messages --}}
        <div @class(['row g-2 align-items-center mb-3' => !$noclass])>

            {{-- Error --}}
            @if($error)
                <div
                    wire:key="error-{{ $messageKey }}"
                    x-data="{ show: true }"
                    x-init="
                        @if($fade)
                            setTimeout(() => show = false, 2000);
                        @endif
                    "
                    x-show="show"
                    x-transition
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >
                    {{ $error }}
                    <button type="button" class="btn-close" @click="show = false" aria-label="Close"></button>
                </div>
            @endif

            {{-- Message --}}
            @if($message)
                <div
                    wire:key="message-{{ $messageKey }}"
                    x-data="{ show: true }"
                    x-init="
                        @if($fade)
                            setTimeout(() => show = false, 2000);
                        @endif
                    "
                    x-show="show"
                    x-transition
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >
                    {{ $message }}
                    <button type="button" class="btn-close" @click="show = false" aria-label="Close"></button>
                </div>
            @endif

        </div>
    @endif
</div>

