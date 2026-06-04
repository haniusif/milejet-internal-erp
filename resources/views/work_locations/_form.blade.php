@php
    $val = fn ($f, $def = '') => old($f, $location->$f ?? $def);
@endphp

<div>
    <label class="block text-sm font-medium mb-1">{{ __('Office name') }} *</label>
    <input type="text" name="name" required value="{{ $val('name') }}" class="w-full border rounded px-3 py-2">
</div>
<div>
    <label class="block text-sm font-medium mb-1">{{ __('Type') }} *</label>
    <select name="location_type" class="w-full border rounded px-3 py-2 bg-white">
        @foreach (['office' => __('Office'), 'home' => __('Home'), 'other' => __('Other')] as $value => $label)
            <option value="{{ $value }}" {{ $val('location_type', 'office') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">{{ __('Latitude') }}</label>
        <input type="number" step="any" min="-90" max="90" name="latitude"
               value="{{ $val('latitude') }}" placeholder="24.7136" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">{{ __('Longitude') }}</label>
        <input type="number" step="any" min="-180" max="180" name="longitude"
               value="{{ $val('longitude') }}" placeholder="46.6753" class="w-full border rounded px-3 py-2">
    </div>
</div>
<div>
    <label class="block text-sm font-medium mb-1">{{ __('Geofence radius (m)') }}</label>
    <input type="number" min="10" max="100000" name="geofence_radius"
           value="{{ $val('geofence_radius') }}"
           placeholder="{{ __('Default (:radius m)', ['radius' => config('attendance.geofence_radius')]) }}"
           class="w-full border rounded px-3 py-2">
    <p class="text-xs text-slate-400 mt-1">{{ __('Leave coordinates empty to fall back to the global office geofence.') }}</p>
</div>
