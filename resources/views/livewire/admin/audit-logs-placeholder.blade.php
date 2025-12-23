<div>
    <x-page-header title="Audit Logs" />

    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-hourglass-split text-muted" style="font-size: 4rem;"></i>
            <h4 class="mt-4">Coming in Phase 4</h4>
            <p class="text-muted mb-4">
                The Audit Logs viewer will display all administrative actions across RAIOPS,
                including tenant management, impersonation sessions, and configuration changes.
            </p>
            <div class="alert alert-info d-inline-block">
                <i class="bi bi-info-circle me-2"></i>
                Audit logging is already active in the background via <code>AuditLog::log()</code>
            </div>
        </div>
    </div>

    {{-- Preview of what's being logged --}}
    <div class="card mt-4">
        <div class="card-header">
            <i class="bi bi-clock-history me-2"></i>
            Recent Audit Activity (Preview)
        </div>
        <div class="card-body">
            @php
                $recentLogs = \App\Models\AuditLog::with('user')
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get();
            @endphp
            
            @if($recentLogs->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Action</th>
                                <th>Model</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLogs as $log)
                                <tr>
                                    <td>
                                        <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $log->action }}</span>
                                    </td>
                                    <td>
                                        {{ $log->model_type }}
                                        @if($log->model_id)
                                            <small class="text-muted">#{{ $log->model_id }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $log->user?->name ?? 'System' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted mb-0">No audit logs recorded yet.</p>
            @endif
        </div>
    </div>
</div>

