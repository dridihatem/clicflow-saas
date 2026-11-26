<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ClientContract;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStudy;
use App\Models\Proposal;
use App\Models\Prospect;
use App\Models\Provider;
use App\Models\SupportTicket;
use App\Models\WorkspaceCalendarEvent;
use App\Observers\OdooSyncObserver;
use App\Observers\ProjectObserver;
use App\Observers\WorkspaceAuditObserver;
use App\Models\User;
use App\View\Composers\FlowdeskBreadcrumbComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped('currentCompany', fn (): ?\App\Models\Company => null);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Project::observe(ProjectObserver::class);
        Client::observe(OdooSyncObserver::class);
        Project::observe(OdooSyncObserver::class);
        Invoice::observe(OdooSyncObserver::class);
        Proposal::observe(OdooSyncObserver::class);

        foreach ([
            Client::class,
            Project::class,
            Proposal::class,
            Invoice::class,
            Inquiry::class,
            Prospect::class,
            Provider::class,
            Payment::class,
            Form::class,
            FormSubmission::class,
            ProjectStudy::class,
            WorkspaceCalendarEvent::class,
            SupportTicket::class,
            ClientContract::class,
        ] as $model) {
            $model::observe(WorkspaceAuditObserver::class);
        }

        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if ($user instanceof User) {
                $user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        View::composer(
            [
                'themes.default.layouts.sidebar',
                'themes.default.layouts.topbar',
                'themes.default.layouts.minimal',
                'components.admin-layout',
            ],
            FlowdeskBreadcrumbComposer::class
        );

    }
}
