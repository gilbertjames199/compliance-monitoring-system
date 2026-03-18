@php
    use Filament\Support\Enums\Width;

    $livewire ??= null;

    $renderHookScopes = $livewire?->getRenderHookScopes();
    $maxContentWidth ??= (filament()->getSimplePageMaxContentWidth() ?? Width::Large);

    if (is_string($maxContentWidth)) {
        $maxContentWidth = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
    }
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    <div class="fi-simple-layout">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        @if (($hasTopbar ?? true) && filament()->auth()->check())
            <div class="fi-simple-layout-header">
                @if (filament()->hasDatabaseNotifications())
                    @livewire(Filament\Livewire\DatabaseNotifications::class, [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                @if (filament()->hasUserMenu())
                    @livewire(Filament\Livewire\SimpleUserMenu::class)
                @endif
            </div>
        @endif

        <div class="fi-simple-main-ctn login-background">
            <main
                @class([
                    'fi-simple-main',
                    'px-0',
                    'py-0',
                    'm-0',
                    'rounded-none',
                    'border-none',
                    'bg-transparent',
                    'ring-0',
                    'shadow-none',
                    ($maxContentWidth instanceof Width) ? "fi-width-{$maxContentWidth->value}" : $maxContentWidth,
                ])
                id="ddo-login"
            >
                <div class="login-card">
                    <div class="login-brand-panel">
                        <div class="login-brand-content">
                            <div class="login-logo-glow"></div>
                            <div
                                style="background-image: url('../images/login-logo.png');"
                                class="login-logo"
                            ></div>
                            <div class="login-app-name">
                                {{ config('app.name') ?? 'System Name'}}
                            </div>
                            <div class="login-tagline">
                                {{ $tagline }}
                            </div>
                        </div>
                        <div class="login-brand-decoration"></div>
                    </div>
                    <div class="login-form-panel">
                        <div class="login-form-header">
                            <div class="login-welcome">Welcome back</div>
                            <div class="login-subtitle">Sign in to continue to your account</div>
                        </div>
                        {{$slot}}
                        @if ($registrationEnabled ?? false)
                            <div class="login-register-link">
                                <span>Don't have an account?</span>
                                <a href="{{ filament()->getRegistrationUrl() }}" class="login-register-anchor">Sign up</a>
                            </div>
                        @endif
                    </div>
                </div>
            </main>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
