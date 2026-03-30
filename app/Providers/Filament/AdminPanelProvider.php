<?php

namespace App\Providers\Filament;

use App\Filament\Auth\CustomLogin;
use App\Models\Office;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->brandName('Compliance Monitoring System')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->id('admin')
            ->path('/admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // ->login(CustomLogin::class)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->databaseNotifications()
            
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Indigo,
                'primary' => Color::Yellow,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
                'sidebar-bg' => '#1e293b',
            ])
            // ->font('Inter', provider: GoogleFontProvider::class)
            ->font('Poppins')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                \Javarex\DdoLogin\LoginDdoPlugin::make(),
                FilamentShieldPlugin::make(),
            ])
            ->login(CustomLogin::class)
            ->userMenuItems([
                'profile' => 
                fn (Action $action) => $action->label(fn(): Htmlable => new HtmlString('
                    <div>' . auth()->user()->name . '</div>
                    <div style="font-size: 0.875rem; color: gray;">' .
                        (
                            ($office = Office::where('department_code', auth()->user()->department_code)->first())
                                ? ($office->short_name ?? '-')
                                : '-'
                        ). '
                    </div>  

                '))
                ->disabled(),
                // Action::make()
                //     ->label('test')
                //     // ->label(fn() => auth()->user()->name . ' • ' . auth()->user()->getRoleNames()->first())
                //         // ->url(fn() => route('filament.admin.auth.profile'))
                //         ->icon('heroicon-o-user-circle'),
        ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->unsavedChangesAlerts()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // ->font(
            //     'Akronim',
            //     url: asset('css/fonts/fonts.css'),
            //     provider: LocalFontProvider::class,
            // )
            ->maxContentWidth('full')
            // ->sidebarWidth('16rem')
            ;
    }
}
