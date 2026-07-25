<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard as EliveDashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            /*
            |--------------------------------------------------------------------------
            | Panel
            |--------------------------------------------------------------------------
            */
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)

            /*
            |--------------------------------------------------------------------------
            | Branding
            |--------------------------------------------------------------------------
            */
            ->brandName('eLive Card')
            ->brandLogo(asset('images/elive-card-logo.png'))
            ->darkModeBrandLogo(asset('images/elive-card-logo.png'))
            ->brandLogoHeight('2.7rem')
            ->favicon(asset('favicon.ico'))

            /*
            |--------------------------------------------------------------------------
            | Theme
            |--------------------------------------------------------------------------
            */
            ->viteTheme('resources/css/filament/admin/theme.css')

            /*
            |--------------------------------------------------------------------------
            | Brand Colours
            |--------------------------------------------------------------------------
            */
            ->colors([
                'primary' => Color::hex('#213B73'),
                'warning' => Color::hex('#FD9618'),
                'gray' => Color::Slate,
            ])

            /*
            |--------------------------------------------------------------------------
            | Layout
            |--------------------------------------------------------------------------
            */
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarWidth('18rem')
            ->collapsedSidebarWidth('5.25rem')
            ->sidebarCollapsibleOnDesktop()

            /*
            |--------------------------------------------------------------------------
            | Global Search
            |--------------------------------------------------------------------------
            */
            ->globalSearchKeyBindings([
                'command+k',
                'ctrl+k',
            ])
            ->globalSearchDebounce('400ms')
            ->globalSearchFieldKeyBindingSuffix()

            /*
            |--------------------------------------------------------------------------
            | Header Notifications
            |--------------------------------------------------------------------------
            */
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            /*
            |--------------------------------------------------------------------------
            | User Profile Menu
            |--------------------------------------------------------------------------
            */
            ->userMenuItems([
                MenuItem::make()
                    ->label('View Public Website')
                    ->url(fn (): string => url('/'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->openUrlInNewTab(),

                'logout' => MenuItem::make()
                    ->label('Sign Out')
                    ->icon('heroicon-o-arrow-left-on-rectangle'),
            ])

            /*
            |--------------------------------------------------------------------------
            | Navigation Groups
            |--------------------------------------------------------------------------
            */
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Event Management')
                    ->collapsible(false),

                NavigationGroup::make()
                    ->label('Attendance')
                    ->collapsible(false),

                NavigationGroup::make()
                    ->label('Communication')
                    ->collapsible(),

                NavigationGroup::make()
                    ->label('Reports')
                    ->collapsible(),

                NavigationGroup::make()
                    ->label('System Management')
                    ->collapsible(),
            ])

            /*
            |--------------------------------------------------------------------------
            | Resources
            |--------------------------------------------------------------------------
            */
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources',
            )

            /*
            |--------------------------------------------------------------------------
            | Pages
            |--------------------------------------------------------------------------
            */
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
            ->pages([
                EliveDashboard::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Widgets
            |--------------------------------------------------------------------------
            */
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )
            ->widgets([])

            /*
            |--------------------------------------------------------------------------
            | Middleware
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | Authentication
            |--------------------------------------------------------------------------
            */
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}