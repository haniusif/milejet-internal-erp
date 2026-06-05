<div class="flex items-center gap-1 mb-5 border-b border-slate-200 dark:border-slate-800">
    @php $tabs = [
        ['crm.index',     __('Pipeline'),  request()->routeIs('crm.index')],
        ['crm.customers', __('Customers'), request()->routeIs('crm.customers')],
    ]; @endphp
    @foreach ($tabs as [$routeName, $label, $active])
        <a href="{{ route($routeName) }}"
           class="px-4 h-10 inline-flex items-center text-sm font-medium border-b-2 -mb-px transition
                  {{ $active
                    ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300'
                    : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
