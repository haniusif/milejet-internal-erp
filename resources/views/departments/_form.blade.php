@php
    $val = function ($f, $def = '') use ($department) {
        if (!$department) return old($f, $def);
        $map = ['parent_id' => 'odoo_parent_id', 'manager_id' => 'odoo_manager_id'];
        $key = $map[$f] ?? $f;
        return old($f, $department->$key ?? $def);
    };
@endphp

<div>
    <label class="block text-sm font-medium mb-1">{{ __('Department name') }} *</label>
    <input type="text" name="name" required value="{{ $val('name') }}" class="w-full border rounded px-3 py-2">
</div>
<div>
    <label class="block text-sm font-medium mb-1">{{ __('Parent department') }}</label>
    <select name="parent_id" class="w-full border rounded px-3 py-2 bg-white">
        <option value="">{{ __('— None —') }}</option>
        @foreach ($parents as $p)
            <option value="{{ $p->odoo_id }}" {{ (int) $val('parent_id') === $p->odoo_id ? 'selected' : '' }}>{{ $p->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm font-medium mb-1">{{ __('Department manager') }}</label>
    <select name="manager_id" class="w-full border rounded px-3 py-2 bg-white">
        <option value="">{{ __('— Select —') }}</option>
        @foreach ($managers as $m)
            <option value="{{ $m->odoo_id }}" {{ (int) $val('manager_id') === $m->odoo_id ? 'selected' : '' }}>{{ $m->name }}</option>
        @endforeach
    </select>
</div>
