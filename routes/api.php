<?php

use App\Http\Controllers\Api\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Api\Client\NovaController as ClientNovaController;
use App\Http\Controllers\Api\Client\PortalController as ClientPortalController;
use App\Http\Controllers\Api\Company\AuthController as CompanyAuthController;
use App\Http\Controllers\Api\Company\DevicePushTokenController as CompanyDevicePushTokenController;
use App\Http\Controllers\Api\Company\DocumentController as CompanyDocumentController;
use App\Http\Controllers\Api\Company\NovaController as CompanyNovaController;
use App\Http\Controllers\Api\Company\WorkspaceController as CompanyWorkspaceController;
use App\Http\Controllers\Api\EmbedFormController;
use App\Http\Controllers\Api\EmbedTrackController;
use App\Http\Controllers\Api\Workspace\ClientApiController;
use App\Http\Controllers\Api\Workspace\ImportApiController;
use App\Http\Controllers\Api\Workspace\InvoiceApiController;
use App\Http\Controllers\Api\Workspace\ProjectApiController;
use App\Http\Controllers\Api\Workspace\WorkspaceMeController;
use App\Http\Middleware\AuthenticateCompanyApiToken;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:120,1'])->group(function () {
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::prefix('v1/company')->middleware(['throttle:60,1'])->group(function () {
    Route::post('login', [CompanyAuthController::class, 'login']);
    Route::post('register', [CompanyAuthController::class, 'register']);
    Route::get('oauth/providers', [CompanyAuthController::class, 'oauthProviders']);

    Route::middleware(['auth:sanctum', 'company.api'])->group(function () {
        Route::get('me', [CompanyAuthController::class, 'me']);
        Route::put('locale', [CompanyAuthController::class, 'updateLocale']);
        Route::post('logout', [CompanyAuthController::class, 'logout']);

        Route::post('push-tokens', [CompanyDevicePushTokenController::class, 'store']);
        Route::delete('push-tokens', [CompanyDevicePushTokenController::class, 'destroy']);

        Route::post('nova/chat', [CompanyNovaController::class, 'chat']);
        Route::post('nova/speak', [CompanyNovaController::class, 'speak']);
        Route::post('nova/feedback', [CompanyNovaController::class, 'feedback']);
        Route::get('nova/history', [CompanyNovaController::class, 'history']);
        Route::delete('nova/history', [CompanyNovaController::class, 'clearHistory']);
        Route::get('nova/conversations/{conversationId}', [CompanyNovaController::class, 'conversation']);
        Route::delete('nova/conversations/{conversationId}', [CompanyNovaController::class, 'destroyConversation']);
        Route::get('nova/voice-commands', [CompanyNovaController::class, 'voiceCommands']);

        Route::get('calendar', [CompanyNovaController::class, 'calendar']);
        Route::post('calendar/events', [CompanyNovaController::class, 'storeEvent']);
        Route::delete('calendar/events/{event}', [CompanyNovaController::class, 'destroyEvent']);

        Route::get('dashboard', [CompanyWorkspaceController::class, 'dashboard']);
        Route::get('clients', [CompanyWorkspaceController::class, 'clients']);
        Route::get('clients/{client}', [CompanyWorkspaceController::class, 'client']);
        Route::put('clients/{client}', [CompanyWorkspaceController::class, 'updateClient']);
        Route::delete('clients/{client}', [CompanyWorkspaceController::class, 'destroyClient']);
        Route::post('clients/{client}/email/draft', [CompanyWorkspaceController::class, 'draftClientEmail']);
        Route::post('clients/{client}/email/send', [CompanyWorkspaceController::class, 'sendClientEmail']);
        Route::post('clients/{client}/invoices', [CompanyDocumentController::class, 'storeClientInvoice']);
        Route::post('clients/{client}/proposals', [CompanyDocumentController::class, 'storeClientProposal']);
        Route::post('clients/{client}/reminders', [CompanyDocumentController::class, 'storeClientReminder']);
        Route::post('clients/{client}/meetings', [CompanyDocumentController::class, 'storeClientMeeting']);
        Route::post('invoices/{invoice}/send', [CompanyDocumentController::class, 'sendInvoice']);
        Route::get('invoices/{invoice}/pdf', [CompanyDocumentController::class, 'invoicePdf']);
        Route::post('proposals/{proposal}/send', [CompanyDocumentController::class, 'sendProposal']);
        Route::get('proposals/{proposal}/pdf', [CompanyDocumentController::class, 'proposalPdf']);
        Route::get('projects', [CompanyWorkspaceController::class, 'projects']);
        Route::get('projects/{project}', [CompanyWorkspaceController::class, 'project']);
        Route::delete('projects/{project}', [CompanyWorkspaceController::class, 'destroyProject']);
        Route::put('projects/{project}/tasks/{task}', [CompanyWorkspaceController::class, 'updateTask']);
        Route::put('projects/{project}/team', [CompanyWorkspaceController::class, 'updateProjectTeam']);
        Route::get('proposals', [CompanyWorkspaceController::class, 'proposals']);
        Route::get('proposals/{proposal}', [CompanyWorkspaceController::class, 'proposal']);
        Route::get('invoices', [CompanyWorkspaceController::class, 'invoices']);
        Route::get('invoices/{invoice}', [CompanyWorkspaceController::class, 'invoice']);
        Route::get('messages', [CompanyWorkspaceController::class, 'messages']);
        Route::get('notifications', [CompanyWorkspaceController::class, 'notifications']);
    });
});

Route::prefix('v1/client')->middleware(['throttle:60,1'])->group(function () {
    Route::post('login', [ClientAuthController::class, 'login']);
    Route::get('oauth/providers', [ClientAuthController::class, 'oauthProviders']);

    Route::middleware(['auth:sanctum', 'client.api'])->group(function () {
        Route::get('me', [ClientAuthController::class, 'me']);
        Route::put('locale', [ClientAuthController::class, 'updateLocale']);
        Route::post('logout', [ClientAuthController::class, 'logout']);

        Route::post('nova/chat', [ClientNovaController::class, 'chat']);
        Route::get('nova/history', [ClientNovaController::class, 'history']);
        Route::get('nova/conversations/{conversationId}', [ClientNovaController::class, 'conversation']);

        Route::get('calendar', [ClientNovaController::class, 'calendar']);
        Route::post('calendar/events', [ClientNovaController::class, 'storeEvent']);

        Route::get('dashboard', [ClientPortalController::class, 'dashboard']);
        Route::get('projects', [ClientPortalController::class, 'projects']);
        Route::get('projects/{project}', [ClientPortalController::class, 'project']);
        Route::get('proposals', [ClientPortalController::class, 'proposals']);
        Route::get('proposals/{proposal}', [ClientPortalController::class, 'proposal']);
        Route::get('invoices', [ClientPortalController::class, 'invoices']);
        Route::get('invoices/{invoice}', [ClientPortalController::class, 'invoice']);
        Route::get('messages', [ClientPortalController::class, 'messages']);
        Route::get('notifications', [ClientPortalController::class, 'notifications']);
    });
});

Route::middleware([ResolveTenant::class, 'throttle:180,1'])
    ->prefix('v1/embed')
    ->group(function () {
        Route::options('track', [EmbedTrackController::class, 'options']);
        Route::options('forms/{form}', [EmbedFormController::class, 'optionsShow']);
        Route::options('forms/{form}/submissions', [EmbedFormController::class, 'optionsStore']);
    });

Route::middleware([ResolveTenant::class, AuthenticateCompanyApiToken::class, 'throttle:180,1'])
    ->prefix('v1/embed')
    ->group(function () {
        Route::post('track', [EmbedTrackController::class, 'store']);
        Route::get('forms/{form}', [EmbedFormController::class, 'show']);
        Route::post('forms/{form}/submissions', [EmbedFormController::class, 'store']);
    });

Route::middleware([ResolveTenant::class, AuthenticateCompanyApiToken::class, 'throttle:120,1'])
    ->prefix('v1/workspace')
    ->group(function () {
        Route::get('/', WorkspaceMeController::class);
        Route::get('clients', [ClientApiController::class, 'index']);
        Route::post('clients', [ClientApiController::class, 'store']);
        Route::get('clients/{client}', [ClientApiController::class, 'show']);
        Route::get('projects', [ProjectApiController::class, 'index']);
        Route::post('projects', [ProjectApiController::class, 'store']);
        Route::get('projects/{project}', [ProjectApiController::class, 'show']);
        Route::get('invoices', [InvoiceApiController::class, 'index']);
        Route::post('invoices', [InvoiceApiController::class, 'store']);
        Route::get('invoices/{invoice}', [InvoiceApiController::class, 'show']);
        Route::post('import', ImportApiController::class);
    });
