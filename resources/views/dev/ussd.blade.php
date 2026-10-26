@extends('layouts.base')

@section('title', __('Kiigaji cha USSD'))

@section('body')
<div class="min-h-full bg-slate-100 px-4 py-8" x-data="ussdSimulator()" x-cloak>
    <div class="mx-auto flex max-w-4xl flex-col items-center gap-8 lg:flex-row lg:items-start lg:justify-center">

        {{-- Phone --}}
        <div class="w-full max-w-[22rem] shrink-0 rounded-[2.5rem] bg-slate-900 p-3 shadow-2xl">
            <div class="relative flex h-[38rem] flex-col overflow-hidden rounded-[2rem] bg-gradient-to-b from-emerald-800 to-slate-900">
                <div class="flex items-center justify-between px-6 pt-3 text-xs text-white/80">
                    <span x-text="clock"></span>
                    <span>Simulator</span>
                </div>

                {{-- Dialer --}}
                <div class="flex flex-1 flex-col px-6 pb-6 pt-8 text-white">
                    <label class="text-xs uppercase tracking-wide text-emerald-200">{{ __('Namba ya simu') }}</label>
                    <input x-model="msisdn" inputmode="tel" class="mt-1 w-full border-0 border-b border-white/30 bg-transparent px-0 text-center text-2xl font-semibold tracking-wider text-white placeholder-white/40 focus:border-white focus:ring-0" placeholder="2557XXXXXXXX">

                    <p class="mt-8 text-center text-3xl font-light tracking-widest">*USSD#</p>

                    <div class="mt-auto flex justify-center">
                        <button type="button" @click="dial()" :disabled="busy || !msisdn"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500 shadow-lg transition hover:bg-emerald-400 disabled:opacity-50">
                            <x-icon name="phone" class="h-7 w-7" />
                        </button>
                    </div>
                </div>

                {{-- USSD dialog --}}
                <div x-show="screen" x-transition.opacity class="absolute inset-0 flex items-center justify-center bg-black/60 p-5">
                    <form @submit.prevent="reply()" class="w-full rounded-2xl bg-white p-5 text-slate-900 shadow-xl">
                        <p x-show="busy" class="py-6 text-center text-sm text-slate-500">{{ __('Inatuma ombi la USSD…') }}</p>
                        <div x-show="!busy">
                            <p class="whitespace-pre-line text-[15px] leading-relaxed" x-text="screen"></p>
                            <template x-if="!ended">
                                <input x-ref="answer" x-model="answer" inputmode="numeric" autocomplete="off"
                                    class="mt-4 w-full rounded-lg border-slate-300 text-base focus:border-emerald-600 focus:ring-emerald-600">
                            </template>
                            <div class="mt-4 flex justify-end gap-2">
                                <template x-if="!ended">
                                    <div class="flex gap-2">
                                        <button type="button" @click="cancel()" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">{{ __('Ghairi') }}</button>
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">{{ __('Tuma') }}</button>
                                    </div>
                                </template>
                                <template x-if="ended">
                                    <button type="button" @click="close()" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">{{ __('Sawa') }}</button>
                                </template>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Helper panel --}}
        <div class="w-full max-w-md space-y-6">
            <div>
                <div class="flex items-center justify-between gap-3"><h1 class="text-xl font-semibold">{{ __('Kiigaji cha USSD') }}</h1><x-locale-switch /></div>
                <p class="mt-1 text-sm text-slate-500">{{ __('Kwa majaribio ya ndani tu. Kinatumia menyu halisi ya USSD bila kupitia Beem.') }} {{ __('Lugha ya menyu ni ile ya mwanachama.') }}</p>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-700">{{ __('Chagua mwanachama') }}</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($members as $member)
                        <li>
                            <button type="button" @click="msisdn = '{{ $member->phone }}'"
                                class="flex w-full items-center justify-between gap-3 py-2 text-left text-sm hover:text-emerald-700"
                                :class="msisdn === '{{ $member->phone }}' && 'font-semibold text-emerald-700'">
                                <span>{{ $member->member_number }} · {{ $member->full_name }}</span>
                                <span class="text-xs text-slate-500">{{ $member->phone }}{{ $member->pin_hash ? '' : ' · '.__('hana PIN') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-slate-500">{{ __('Data ya majaribio: PIN ni 1234 kwa M001–M004.') }}</p>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200" x-show="log.length">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">{{ __('Historia ya kipindi') }}</h2>
                    <button type="button" @click="log = []" class="text-xs text-slate-500 hover:text-slate-700">{{ __('Futa') }}</button>
                </div>
                <ol class="mt-3 space-y-2 text-xs">
                    <template x-for="(entry, i) in log" :key="i">
                        <li class="rounded-lg px-3 py-2" :class="entry.from === 'me' ? 'bg-emerald-50 text-emerald-800' : 'whitespace-pre-line bg-slate-50 text-slate-700'" x-text="entry.text"></li>
                    </template>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
    function ussdSimulator() {
        return {
            msisdn: @json($members->first()?->phone ?? ''),
            sessionId: null,
            screen: '',
            answer: '',
            ended: false,
            busy: false,
            log: [],
            clock: new Date().toLocaleTimeString('sw-TZ', { hour: '2-digit', minute: '2-digit' }),

            dial() {
                // crypto.randomUUID needs HTTPS, which a phone on the LAN won't have.
                this.sessionId = 'SIM-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
                this.log.push({ from: 'me', text: @js(__('Piga USSD kutoka')) + ' ' + this.msisdn });
                this.send('initiate', '0');
            },

            reply() {
                if (this.busy || this.ended) return;
                this.log.push({ from: 'me', text: this.answer || @js(__('(tupu)')) });
                this.send('continue', this.answer);
            },

            cancel() {
                this.send('terminate', '0');
                this.close();
            },

            close() {
                this.screen = '';
                this.ended = false;
                this.sessionId = null;
            },

            async send(command, response) {
                this.busy = true;
                this.screen = this.screen || ' ';
                try {
                    const res = await fetch(@json(route('ussd-simulator', absolute: false)), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ command, msisdn: this.msisdn, session_id: this.sessionId, response }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
                    this.screen = data.payload.request;
                    this.ended = data.command === 'terminate';
                } catch (e) {
                    this.screen = @js(__('Hitilafu:')) + ' ' + e.message;
                    this.ended = true;
                }
                if (command !== 'terminate') this.log.push({ from: 'ussd', text: this.screen });
                this.answer = '';
                this.busy = false;
                this.$nextTick(() => this.$refs.answer?.focus());
            },
        };
    }
</script>
@endsection
