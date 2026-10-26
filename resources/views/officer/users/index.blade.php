@extends('layouts.app')
@section('title', __('Watumiaji'))

@section('content')
    <x-page-header :title="__('Watumiaji na vyeo')" :subtitle="__('Kila kiongozi awe na akaunti yake. Cheo kinaamua kurasa anazoweza kuona.')" />

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card :title="__('Watumiaji')" :padded="false" class="xl:col-span-2">
            <x-table>
                <x-slot:head><tr><th class="px-5 py-3">{{ __('Jina') }}</th><th class="px-5 py-3">{{ __('Cheo') }}</th><th class="px-5 py-3">{{ __('Badilisha') }}</th></tr></x-slot:head>
                @foreach ($users as $user)
                    <tr x-data="{ edit: false }" class="align-top">
                        <td class="px-5 py-3"><p class="font-medium">{{ $user->name }}</p><p class="text-xs text-slate-500">{{ $user->email }}</p></td>
                        <td class="px-5 py-3"><x-badge :tone="$user->role === 'system_admin' ? 'violet' : 'sky'">{{ __(\App\Support\Roles::label($user->role)) }}</x-badge></td>
                        <td class="px-5 py-3">
                            <button type="button" @click="edit = !edit" class="text-sm font-medium text-emerald-700 hover:underline">{{ __('Hariri') }}</button>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('users.update', $user) }}" class="mt-3 w-60 space-y-2">
                                @csrf @method('PUT')
                                <select name="role" class="w-full rounded-lg border-0 px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300">
                                    @foreach ($roles as $key => $label) <option value="{{ $key }}" @selected($user->role === $key)>{{ __($label) }}</option> @endforeach
                                </select>
                                <input type="password" name="password" placeholder="{{ __('Nenosiri jipya (si lazima)') }}" class="w-full rounded-lg border-0 px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300">
                                <input type="password" name="password_confirmation" placeholder="{{ __('Rudia nenosiri') }}" class="w-full rounded-lg border-0 px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300">
                                <x-button size="sm" class="w-full">{{ __('Hifadhi') }}</x-button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card :title="__('Ongeza kiongozi')">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf
                <x-input :label="__('Jina kamili')" name="name" required />
                <x-input :label="__('Barua pepe')" name="email" type="email" required />
                <x-select :label="__('Cheo')" name="role" :options="$roles" value="secretary" />
                <x-input :label="__('Nenosiri')" name="password" type="password" required :hint="__('Angalau herufi 8')" />
                <x-input :label="__('Rudia nenosiri')" name="password_confirmation" type="password" required />
                <x-button class="w-full" icon="user-plus">{{ __('Ongeza') }}</x-button>
            </form>
            <div class="mt-5 space-y-1.5 rounded-xl bg-slate-50 p-4 text-xs text-slate-600">
                <p><span class="font-medium">{{ __('Katibu') }}:</span> {{ __('wanachama, michango, matangazo') }}</p>
                <p><span class="font-medium">{{ __('Mweka hazina') }}:</span> {{ __('ada, akiba, malipo, ripoti') }}</p>
                <p><span class="font-medium">{{ __('Mwenyekiti') }}:</span> {{ __('anaona vyote, hawezi kubadilisha') }}</p>
                <p><span class="font-medium">{{ __('Msimamizi') }}:</span> {{ __('vyote pamoja na mipangilio na nakala za akiba') }}</p>
            </div>
        </x-card>
    </div>
@endsection
