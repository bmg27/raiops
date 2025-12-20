<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
        <h2 class="h4 fw-bold mb-0">{{ $title }}</h2>
        @isset($helpUrl)
            <button 
                type="button" 
                class="btn btn-link btn-sm p-0 text-muted d-flex align-items-center" 
                onclick="loadHelpModal('{{ $helpUrl }}'{{ isset($helpData) ? ', ' . $helpData : '' }})"
                title="View Help Documentation"
                style="line-height: 1; text-decoration: none;">
                <i class="bi bi-question-circle" style="font-size: 1.2rem;"></i>
            </button>
        @endisset
    </div>
    @isset($actions)
        <div class="d-flex gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>

