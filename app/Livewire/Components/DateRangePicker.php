<?php

namespace App\Livewire\Components;

use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Attributes\Modelable;

class DateRangePicker extends Component
{
    #[Modelable]
    public $value = '';
    public $maxSpan = null;
    public $allowRanges = true;
    public $showNavButtons = true;
    public $presetRanges = ['Today', 'Yesterday', 'This Week', 'Last Week', 'This Month', 'Last Month', 'Last 90 Days'];
    public $opens = 'left';
    public $drops = 'down';
    public $placeholder = 'Select date range';
    public $dateFormat = 'MMM D, YYYY';
    public $defaultDate = 'yesterday';
    public $minDate = null;
    public $maxDate = null;
    public $error = null;
    public $showError = true;

    // Unique ID for multiple instances
    public $pickerId;

    public function mount(
        $value = null,
        $maxSpan = null,
        $allowRanges = true,
        $showNavButtons = true,
        $presetRanges = null,
        $opens = 'left',
        $drops = 'down',
        $placeholder = 'Select date range',
        $dateFormat = 'MMM D, YYYY',
        $defaultDate = 'yesterday',
        $minDate = null,
        $maxDate = null,
        $showError = true
    ) {
        $this->pickerId = 'datePicker_' . uniqid();
        $this->value = $value;
        $this->maxSpan = $maxSpan;
        $this->allowRanges = $allowRanges;
        $this->showNavButtons = $showNavButtons;
        $this->presetRanges = $presetRanges ?? $this->presetRanges;
        $this->opens = $opens;
        $this->drops = $drops;
        $this->placeholder = $placeholder;
        $this->dateFormat = $dateFormat;
        $this->defaultDate = $defaultDate;
        $this->minDate = $minDate;
        $this->maxDate = $maxDate;
        $this->showError = $showError;

        // Set initial value if not provided
        if (empty($this->value)) {
            $this->value = $this->getDefaultDateValue();
        }
    }

    protected function getDefaultDateValue()
    {
        $date = match($this->defaultDate) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            'this_week_start' => Carbon::now()->startOfWeek(Carbon::MONDAY),
            'this_month_start' => Carbon::now()->startOfMonth(),
            default => Carbon::yesterday(),
        };

        if ($this->allowRanges && in_array($this->defaultDate, ['this_week', 'this_month'])) {
            $endDate = match($this->defaultDate) {
                'this_week' => Carbon::now()->startOfWeek(Carbon::MONDAY)->endOfWeek(),
                'this_month' => Carbon::now()->endOfMonth(),
                default => $date,
            };
            return $date->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d');
        }

        return $date->format('Y-m-d');
    }

    public function updatedValue($value)
    {
        if ($this->validateDateValue($value)) {
            $this->dispatch('dateChanged', date: $value);
        }
    }

    protected function validateDateValue($value)
    {
        $this->error = null;

        if (empty($value)) {
            return true;
        }

        // Parse the date(s)
        $dates = explode(' - ', $value);
        $startDate = $dates[0] ?? null;
        $endDate = $dates[1] ?? $startDate;

        // Format validation
        if (!$this->isValidDateFormat($startDate) || !$this->isValidDateFormat($endDate)) {
            $this->error = "Invalid date format. Please use YYYY-MM-DD.";
            return false;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Range validation
        if ($start->gt($end)) {
            $this->error = "Start date cannot be after end date.";
            return false;
        }

        // Check if ranges are allowed
        if (!$this->allowRanges && $start->ne($end)) {
            $this->error = "Date ranges are not allowed. Please select a single date.";
            return false;
        }

        // Max span validation
        if ($this->maxSpan !== null) {
            $daysDiff = $end->diffInDays($start);
            if ($daysDiff > $this->maxSpan) {
                $this->error = "Date range cannot exceed {$this->maxSpan} days.";
                return false;
            }
        }

        // Min date validation
        if ($this->minDate) {
            $minDate = Carbon::parse($this->minDate);
            if ($start->lt($minDate)) {
                $this->error = "Date cannot be before " . $minDate->format('Y-m-d') . ".";
                return false;
            }
        }

        // Max date validation
        if ($this->maxDate) {
            $maxDate = Carbon::parse($this->maxDate);
            if ($end->gt($maxDate)) {
                $this->error = "Date cannot be after " . $maxDate->format('Y-m-d') . ".";
                return false;
            }
        }

        return true;
    }

    protected function isValidDateFormat($date)
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Public methods that can be called from parent components

    public function setDate($date)
    {
        $this->value = $date;
        $this->validateDateValue($date);
    }


    public function clearDate()
    {
        $this->value = '';
        $this->error = null;
        $this->dispatch('dateChanged', date: '');
    }

    #[On('refresh-date-picker')]
    public function refreshPicker()
    {
        $this->dispatch('refreshDatePicker', pickerId: $this->pickerId);
    }

    public function previousDay()
    {
        $dates = explode(' - ', $this->value);
        $start = Carbon::parse($dates[0])->subDay();
        $end = isset($dates[1]) ? Carbon::parse($dates[1])->subDay() : $start;

        $this->value = $start->format('Y-m-d') . ($this->allowRanges && isset($dates[1]) ? ' - ' . $end->format('Y-m-d') : '');
        $this->dispatch('dateChanged', date: $this->value);
    }

    public function nextDay()
    {
        $dates = explode(' - ', $this->value);
        $start = Carbon::parse($dates[0])->addDay();
        $end = isset($dates[1]) ? Carbon::parse($dates[1])->addDay() : $start;

        $this->value = $start->format('Y-m-d') . ($this->allowRanges && isset($dates[1]) ? ' - ' . $end->format('Y-m-d') : '');
        $this->dispatch('dateChanged', date: $this->value);
    }

    public function render()
    {
        return view('livewire.components.date-range-picker');
    }
}

