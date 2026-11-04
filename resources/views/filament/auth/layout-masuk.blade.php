{{-- Layout halaman masuk panel (App\Filament\Pages\Auth\Masuk); gaya dan panel merek dibagi dengan layouts.masuk. --}}
@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @include('auth.partials.gaya-masuk')

    <div class="sr-masuk">
        @include('auth.partials.panel-merek')

        <div class="sr-masuk-pane">
            <div class="sr-masuk-bar">
                @include('auth.partials.merek', ['ponsel' => true])
            </div>

            <div class="sr-masuk-isi">
                <main id="fi-main-content" tabindex="-1" class="sr-masuk-kartu">
                    {{ $slot }}
                </main>
            </div>

            <p class="sr-masuk-kaki"><a href="{{ route('beranda') }}">&larr; Kembali ke beranda</a></p>

            <div class="sr-masuk-versi">
                {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
            </div>
        </div>
    </div>

    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
</x-filament-panels::layout.base>
