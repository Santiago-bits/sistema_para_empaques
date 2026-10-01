@props(['color' => 'stone'])
@php
    $map = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-500/15 dark:text-slate-300',
        'stone' => 'bg-stone-100 text-stone-700 ring-stone-600/20 dark:bg-stone-500/15 dark:text-stone-300',
        'zinc' => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-500/15 dark:text-zinc-400',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/15 dark:text-blue-300',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300',
        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/15 dark:text-cyan-300',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/15 dark:text-teal-300',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-500/15 dark:text-orange-300',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/15 dark:text-violet-300',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/15 dark:text-indigo-300',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset '.($map[$color] ?? $map['stone'])]) }}>{{ $slot }}</span>
