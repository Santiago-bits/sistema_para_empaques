@foreach (['success' => ['emerald', 'check'], 'error' => ['red', 'alert'], 'warning' => ['amber', 'alert'], 'info' => ['sky', 'info']] as $type => [$color, $icon])
    @if (session($type))
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm
                {{ $color === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : '' }}
                {{ $color === 'red' ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' : '' }}
                {{ $color === 'amber' ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200' : '' }}
                {{ $color === 'sky' ? 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200' : '' }}"
             role="{{ $type === 'error' ? 'alert' : 'status' }}">
            <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0"/>
            <div class="flex-1">{{ session($type) }}</div>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Cerrar"><x-icon name="x" class="size-4"/></button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! ($hideErrors ?? false))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" role="alert">
        <p class="font-semibold">Revisá los datos ingresados:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
