<div>
    @if($showError && $error)
        <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $error }}
            <button type="button" class="btn-close" wire:click="$set('error', null)"></button>
        </div>
    @endif

    <div wire:ignore class="custom-date-picker-wrapper">
        <div class="custom-date-picker d-flex align-items-stretch">
            @if($showNavButtons)
                <!-- Previous Day Button -->
                <button type="button"
                        class="btn btn-outline-secondary btn-sm date-nav-btn"
                        wire:click="previousDay"
                        style="border-radius: 0.375rem 0 0 0.375rem;">
                    <i class="bi bi-chevron-left"></i>
                </button>
            @endif

            <!-- Date Display Area -->
            <div class="date-display-area flex-grow-1 cursor-pointer border d-flex align-items-center"
                 id="dateDisplayArea_{{ $pickerId }}"
                 style="{{ $showNavButtons ? 'border-left: none !important; border-right: none !important;' : '' }}">
                <i class="bi bi-calendar me-2 text-muted"></i>
                <div class="flex-grow-1">
                    <div class="date-label text-muted" id="dateLabel_{{ $pickerId }}">Custom</div>
                    <div class="date-range fw-semibold" id="dateRangeDisplay_{{ $pickerId }}">{{ $placeholder }}</div>
                </div>
                <i class="bi bi-chevron-down text-muted ms-2"></i>
            </div>

            @if($showNavButtons)
                <!-- Next Day Button -->
                <button type="button"
                        class="btn btn-outline-secondary btn-sm date-nav-btn"
                        wire:click="nextDay"
                        style="border-radius: 0 0.375rem 0.375rem 0;">
                    <i class="bi bi-chevron-right"></i>
                </button>
            @endif

            <!-- Hidden input for daterangepicker -->
            <input
                id="dateRangePicker_{{ $pickerId }}"
                wire:model.live="value"
                type="text"
                class="d-none"
                autocomplete="off"
                readonly
            />
        </div>
    </div>
</div>

@once
@push('styles')
<style>
/* Custom Date Picker Styles */
.custom-date-picker {
    border-radius: 0.375rem;
    height: 38px;
}

.custom-date-picker .btn {
    height: 100%;
    border-color: var(--bs-border-color) !important;
    padding: 0.375rem 0.5rem;
}

.custom-date-picker .btn:hover:not(:disabled) {
    background-color: #6c757d;
    border-color: #6c757d !important;
    color: white;
}

.date-display-area {
    background-color: var(--bs-body-bg);
    transition: all 0.2s ease;
    height: 100%;
    user-select: none;
    padding: 0.3rem 0.75rem !important;
    border-color: var(--bs-border-color) !important;
    white-space: nowrap;
    overflow: hidden;
}

.date-display-area:hover {
    background-color: var(--bs-secondary-bg);
}

.date-display-area * {
    user-select: none;
}

.date-label {
    font-size: 0.65rem;
    line-height: 1.1;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.date-range {
    font-size: 0.813rem;
    line-height: 1.1;
    color: var(--bs-body-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.date-display-area .bi-calendar {
    font-size: 1rem;
}

.date-display-area .bi-chevron-down {
    font-size: 0.75rem;
}

/* Dark mode specific adjustments */
@media (prefers-color-scheme: dark) {
    .date-display-area {
        background-color: var(--bs-body-bg);
        color: var(--bs-body-color);
        border-color: var(--bs-border-color) !important;
    }

    .date-display-area:hover {
        background-color: var(--bs-secondary-bg);
    }

    .date-range {
        color: var(--bs-body-color);
    }
}

/* Bootstrap dark theme support */
[data-bs-theme="dark"] .date-display-area {
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: var(--bs-border-color) !important;
}

[data-bs-theme="dark"] .date-display-area:hover {
    background-color: var(--bs-secondary-bg);
}

[data-bs-theme="dark"] .date-range {
    color: var(--bs-body-color);
}

/* Daterangepicker Bootstrap Theme Styling */
.daterangepicker {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}

.daterangepicker .calendar-table {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
}

.daterangepicker .calendar-table th,
.daterangepicker .calendar-table td {
    color: var(--bs-body-color);
}

.daterangepicker .calendar-table td.active,
.daterangepicker .calendar-table td.active:hover {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: white;
}

.daterangepicker .calendar-table td.in-range {
    background-color: var(--bs-primary-bg-subtle);
    color: var(--bs-body-color);
}

.daterangepicker .calendar-table td:hover {
    background-color: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}

.daterangepicker .calendar-table td.off,
.daterangepicker .calendar-table td.off.in-range,
.daterangepicker .calendar-table td.off.start-date,
.daterangepicker .calendar-table td.off.end-date {
    background-color: transparent;
    color: var(--bs-secondary-color);
}

.daterangepicker .ranges li:hover {
    background-color: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}

.daterangepicker .ranges li.active {
    background-color: var(--bs-primary);
    color: white;
}

.daterangepicker select.monthselect,
.daterangepicker select.yearselect {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}

.daterangepicker .drp-buttons {
    border-top-color: var(--bs-border-color);
}

/* Dark mode specific adjustments for daterangepicker */
[data-bs-theme="dark"] .daterangepicker {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .daterangepicker .calendar-table {
    background-color: var(--bs-body-bg);
}

[data-bs-theme="dark"] .daterangepicker .ranges {
    background-color: var(--bs-body-bg);
    border-right-color: var(--bs-border-color);
}
</style>
@endpush
@endonce

@push('scripts')
    <script>
        (function() {
            const pickerId = '{{ $pickerId }}';
            const config = {
                maxSpan: @js($maxSpan),
                allowRanges: @js($allowRanges),
                presetRanges: @js($presetRanges),
                opens: @js($opens),
                drops: @js($drops),
                dateFormat: @js($dateFormat),
                minDate: @js($minDate),
                maxDate: @js($maxDate)
            };

            let initialized = false;
            let component = null;

            function initDateRangePicker() {
                if (initialized) return;
                initialized = true;

                // find the closest Livewire component for this picker (important for SPA)
                const inputEl = document.getElementById('dateRangePicker_' + pickerId);
                if (!inputEl) return;
                const nearestComponentEl = inputEl.closest('[wire\\:id]');
                if (!nearestComponentEl) return;

                component = Livewire.find(nearestComponentEl.getAttribute('wire:id'));
                if (!component) {
                    console.warn('DatePicker: Livewire component not found for', pickerId);
                    return;
                }

                const $displayArea = $('#dateDisplayArea_' + pickerId);
                const $dateLabel = $('#dateLabel_' + pickerId);
                const $dateRangeDisplay = $('#dateRangeDisplay_' + pickerId);

                // destroy any previous daterangepicker instance
                if ($displayArea.data('daterangepicker')) {
                    $displayArea.data('daterangepicker').remove();
                }

                // build preset ranges
                const ranges = {};
                if (config.presetRanges && config.allowRanges) {
                    if (config.presetRanges.includes('Today')) ranges['Today'] = [moment(), moment()];
                    if (config.presetRanges.includes('Yesterday')) ranges['Yesterday'] = [moment().subtract(1, 'day'), moment().subtract(1, 'day')];
                    // Use isoWeek to ensure Monday-Sunday weeks
                    if (config.presetRanges.includes('This Week')) ranges['This Week'] = [moment().startOf('isoWeek'), moment().endOf('isoWeek')];
                    if (config.presetRanges.includes('Last Week')) ranges['Last Week'] = [moment().subtract(1, 'week').startOf('isoWeek'), moment().subtract(1, 'week').endOf('isoWeek')];
                    if (config.presetRanges.includes('This Month')) ranges['This Month'] = [moment().startOf('month'), moment().endOf('month')];
                    if (config.presetRanges.includes('Last Month')) ranges['Last Month'] = [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')];
                    if (config.presetRanges.includes('Last 7 Days')) ranges['Last 7 Days'] = [moment().subtract(6, 'days'), moment()];
                    if (config.presetRanges.includes('Last 30 Days')) ranges['Last 30 Days'] = [moment().subtract(29, 'days'), moment()];
                    if (config.presetRanges.includes('Last 90 Days')) ranges['Last 90 Days'] = [moment().subtract(89, 'days'), moment()];
                }

                const pickerConfig = {
                    locale: { format: 'YYYY-MM-DD', cancelLabel: 'Clear', firstDay: 1 },
                    opens: config.opens,
                    drops: config.drops,
                    singleDatePicker: !config.allowRanges,
                    buttonClasses: 'btn',
                    applyButtonClasses: 'btn-primary',
                    cancelButtonClasses: 'btn-secondary',
                    maxSpan: config.maxSpan ? { days: config.maxSpan } : undefined,
                    ranges: Object.keys(ranges).length ? ranges : undefined,
                    minDate: config.minDate ? moment(config.minDate) : undefined,
                    maxDate: config.maxDate ? moment(config.maxDate) : undefined,
                    firstDay: 1 // Monday as first day of week
                };

                // initialize daterangepicker
                $displayArea.daterangepicker(pickerConfig)
                    .on('apply.daterangepicker', function(ev, picker) {
                        const startDate = picker.startDate.format('YYYY-MM-DD');
                        const endDate = picker.endDate.format('YYYY-MM-DD');
                        const dateValue = config.allowRanges && startDate !== endDate
                            ? `${startDate} - ${endDate}`
                            : startDate;

                        // ✅ correctly set the Livewire property
                        component?.set('value', dateValue);
                        updateDisplay(startDate, endDate);
                    });

                // helper: update visible text
                function updateDisplay(start, end) {
                    const s = moment(start), e = moment(end);
                    const label = Object.entries(ranges).find(([lbl, r]) =>
                        s.isSame(r[0], 'day') && e.isSame(r[1], 'day')
                    )?.[0] ?? 'Custom';
                    const text = s.isSame(e, 'day')
                        ? s.format(config.dateFormat)
                        : `${s.format(config.dateFormat)} - ${e.format(config.dateFormat)}`;
                    $dateLabel.text(label);
                    $dateRangeDisplay.text(text);
                }

                // initialize display with current Livewire value
                try {
                    const current = component?.get('value');
                    if (current) {
                        const [s, e] = current.split(' - ');
                        updateDisplay(s, e || s);
                    }
                } catch (err) {
                    console.warn('DatePicker init failed:', err);
                }

                // sync if Livewire updates the value externally
                Livewire.hook('morph.updated', () => {
                    try {
                        const current = component?.get('value');
                        if (current) {
                            const [s, e] = current.split(' - ');
                            updateDisplay(s, e || s);
                        }
                    } catch (_) {}
                });
            }

            // handle lifecycle for SPA mode
            document.addEventListener('livewire:load', initDateRangePicker);
            document.addEventListener('livewire:navigated', initDateRangePicker);
            document.addEventListener("DOMContentLoaded", initDateRangePicker);

            document.addEventListener('livewire:navigate', () => {
                initialized = false;
                const $displayArea = $('#dateDisplayArea_' + pickerId);
                if ($displayArea.data('daterangepicker')) {
                    $displayArea.data('daterangepicker').remove();
                }
                component = null;
            });
        })();
    </script>
@endpush

