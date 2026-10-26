@extends('layouts.app')

@section('title', 'Ingia')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="mx-auto max-w-sm space-y-4 rounded-lg bg-white p-6 shadow">
        @csrf
        <h1 class="text-xl font-semibold">Ingia</h1>
        <label class="block">
            <span class="text-sm">Barua pepe</span>
            <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border-gray-300 px-3 py-2 ring-1 ring-gray-300">
        </label>
        <label class="block">
            <span class="text-sm">Nenosiri</span>
            <input type="password" name="password" required class="mt-1 w-full rounded px-3 py-2 ring-1 ring-gray-300">
        </label>
        @error('email')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
        <button class="w-full rounded bg-emerald-700 py-2 text-white">Ingia</button>
    </form>
@endsection
