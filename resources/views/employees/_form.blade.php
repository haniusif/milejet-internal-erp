@php
    $val = function ($f, $def = '') use ($employee) {
        if (!$employee) return old($f, $def);
        $map = [
            'department_id' => 'odoo_department_id',
            'parent_id'     => 'odoo_parent_id',
        ];
        $key = $map[$f] ?? $f;
        return old($f, $employee->$key ?? $def);
    };
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">الاسم *</label>
        <input type="text" name="name" required value="{{ $val('name') }}"
               class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">المسمى الوظيفي</label>
        <input type="text" name="job_title" value="{{ $val('job_title') }}"
               class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">القسم</label>
        <select name="department_id" class="w-full border rounded px-3 py-2 bg-white">
            <option value="">— اختر —</option>
            @foreach ($departments as $d)
                <option value="{{ $d->odoo_id }}" {{ (int) $val('department_id') === $d->odoo_id ? 'selected' : '' }}>
                    {{ $d->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">المدير المباشر</label>
        <select name="parent_id" class="w-full border rounded px-3 py-2 bg-white">
            <option value="">— اختر —</option>
            @foreach ($managers as $m)
                <option value="{{ $m->odoo_id }}" {{ (int) $val('parent_id') === $m->odoo_id ? 'selected' : '' }}>
                    {{ $m->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">البريد</label>
        <input type="email" name="work_email" value="{{ $val('work_email') }}"
               class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">هاتف العمل</label>
        <input type="text" name="work_phone" value="{{ $val('work_phone') }}"
               class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">جوال</label>
        <input type="text" name="mobile_phone" value="{{ $val('mobile_phone') }}"
               class="w-full border rounded px-3 py-2">
    </div>
</div>
