@if(\hexa_core\Models\Setting::isPackageEnabled('hexawebsystems/laravel-hexa-package-sapling'))
@if(auth()->check())

@once('ai-sidebar-header')
<p class="text-xs text-gray-600 uppercase tracking-wider pt-4 pb-1 px-3">AI</p>
@endonce

<a href="{{ route('sapling.index') }}"
   class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('raw-sapling*') || request()->is('sapling*') ? 'sidebar-active' : 'sidebar-hover' }}">
    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    Sapling
</a>

@endif
@endif
