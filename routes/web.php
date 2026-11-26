<?php

use App\Http\Controllers\Settings\McpSettingsController;
use App\Http\Controllers\Settings\PortalVisibilitySettingsController;
use App\Http\Controllers\Admin\AdminMcpSettingsController;
use App\Http\Controllers\Mcp\AdminMcpController;
use App\Http\Controllers\Mcp\CompanyMcpController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\CompanyNoticeController as AdminCompanyNoticeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeveloperDocumentationController;
use App\Http\Controllers\Admin\EmailMarketingTemplateModelController as AdminEmailMarketingTemplateModelController;
use App\Http\Controllers\Admin\InvoicePdfTemplateLibraryController as AdminInvoicePdfTemplateLibraryController;
use App\Http\Controllers\Admin\MarketplaceModuleController as AdminMarketplaceModuleController;
use App\Http\Controllers\Admin\MarketplaceOrderController as AdminMarketplaceOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PaymentGatewaySettingsController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PlatformAppearanceController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\PasswordController as AdminPasswordController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ThemeLibraryController as AdminThemeLibraryController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BillingCheckoutController;
use App\Http\Controllers\BillingPlanController;
use App\Http\Controllers\GrowthAdvisorController;
use App\Http\Controllers\Admin\WorkspaceSubscriptionOrderController as AdminWorkspaceSubscriptionOrderController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientAccountRequestReviewController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientFollowUpController;
use App\Http\Controllers\ClientPortalAccountController;
use App\Http\Controllers\ClientProspectController;
use App\Http\Controllers\ClientCampaignController;
use App\Http\Controllers\ClientBookingController;
use App\Http\Controllers\ClientQuickStoreController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\EmailMarketing\AudienceController as EmailMarketingAudienceController;
use App\Http\Controllers\EmailMarketing\CampaignController as EmailMarketingCampaignController;
use App\Http\Controllers\EmailMarketing\DashboardController as EmailMarketingDashboardController;
use App\Http\Controllers\EmailMarketing\EmailMarketingCampaignContentAiController;
use App\Http\Controllers\EmailMarketing\EmailMarketingPreviewEmailController;
use App\Http\Controllers\EmailMarketing\EmailMarketingTemplateAiController;
use App\Http\Controllers\EmailMarketing\EmailOpenTrackingController;
use App\Http\Controllers\EmailMarketing\SequenceController as EmailMarketingSequenceController;
use App\Http\Controllers\EmailMarketing\TemplateController as EmailMarketingTemplateController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormFieldController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\Hr\DashboardController as HrDashboardController;
use App\Http\Controllers\Hr\DepartmentController as HrDepartmentController;
use App\Http\Controllers\Hr\EmployeeController as HrEmployeeController;
use App\Http\Controllers\Hr\LeaveController as HrLeaveController;
use App\Http\Controllers\Hr\PayrollController as HrPayrollController;
use App\Http\Controllers\Hr\SettingsController as HrSettingsController;
use App\Http\Controllers\Hr\AdvanceController as HrAdvanceController;
use App\Http\Controllers\Hr\SelfServiceController as HrSelfServiceController;
use App\Http\Controllers\Commerce\ProductAiController as CommerceProductAiController;
use App\Http\Controllers\Commerce\ProductController as CommerceProductController;
use App\Http\Controllers\Commerce\OrderController as CommerceOrderController;
use App\Http\Controllers\Commerce\DeliveryController as CommerceDeliveryController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\RealEstate\OfferController as RealEstateOfferController;
use App\Http\Controllers\RealEstate\DemandController as RealEstateDemandController;
use App\Http\Controllers\RealEstate\ContractController as RealEstateContractController;
use App\Http\Controllers\Settings\ShopifyIntegrationController;
use App\Http\Controllers\Settings\WooCommerceIntegrationController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\InvoiceAiController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceFlouciController;
use App\Http\Controllers\InvoicePaymentIntentController;
use App\Http\Controllers\InvoicePayPalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\EmbedAssetController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Marketing\NovaPublicDemoController;
use App\Http\Controllers\MarketingHubController;
use App\Http\Controllers\MarketplaceCartController;
use App\Http\Controllers\MarketplaceCheckoutController;
use App\Http\Controllers\ModuleActionController;
use App\Http\Controllers\ModulePageController;
use App\Http\Controllers\NegotiationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\Portal\CalendarController as PortalCalendarController;
use App\Http\Controllers\Portal\ClientAccountRequestController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\InvoiceController as PortalInvoiceController;
use App\Http\Controllers\Portal\InvoicePaymentController as PortalInvoicePaymentController;
use App\Http\Controllers\Portal\PaymentController as PortalPaymentController;
use App\Http\Controllers\Portal\ProjectController as PortalProjectController;
use App\Http\Controllers\Portal\ContractController as PortalContractController;
use App\Http\Controllers\Portal\ProposalController as PortalProposalController;
use App\Http\Controllers\Portal\ProspectController as PortalProspectController;
use App\Http\Controllers\Portal\CampaignController as PortalCampaignController;
use App\Http\Controllers\Portal\BookingController as PortalBookingController;
use App\Http\Controllers\Portal\AssistantController as PortalAssistantController;
use App\Http\Controllers\Portal\QuoteRequestController;
use App\Http\Controllers\ContractAiController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ProjectStudyController;
use App\Http\Controllers\ProjectStudyAiController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\ProductTourController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectAiController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileVaultController;
use App\Http\Controllers\ProjectInstallmentController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProposalAiController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\Provider\DashboardController as ProviderDashboardController;
use App\Http\Controllers\Provider\PartnershipController as ProviderPartnershipController;
use App\Http\Controllers\Provider\ProjectController as ProviderProjectController;
use App\Http\Controllers\Provider\ProposalController as ProviderProposalController;
use App\Http\Controllers\Provider\RemittanceRequestController as ProviderRemittanceRequestController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ProviderPartnershipCompanyController;
use App\Http\Controllers\ProviderRemittanceInboxController;
use App\Http\Controllers\ProviderRemittanceReviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\BillingTaxSettingsController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\CalendarSchedulingController;
use App\Http\Controllers\Settings\DashboardLayoutController;
use App\Http\Controllers\Settings\GoogleCalendarController;
use App\Http\Controllers\Settings\InvoiceDocumentController;
use App\Http\Controllers\Settings\MarketingIntegrationsController;
use App\Http\Controllers\Settings\ModulesSettingsController;
use App\Http\Controllers\Settings\NavigationSettingsController;
use App\Http\Controllers\Settings\NotionIntegrationController;
use App\Http\Controllers\Settings\OdooIntegrationController;
use App\Http\Controllers\Settings\PerfexIntegrationController;
use App\Http\Controllers\Settings\QuickBooksIntegrationController;
use App\Http\Controllers\Settings\TrelloIntegrationController;
use App\Http\Controllers\Settings\ThirdPartyIntegrationsController;
use App\Http\Controllers\Settings\PaymentGatewaySettingsController as WorkspacePaymentGatewaySettingsController;
use App\Http\Controllers\Settings\ProviderCommissionSettingsController;
use App\Http\Controllers\Settings\ProviderRecruitmentSettingsController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SmtpController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\Settings\TwoFactorController;
use App\Http\Controllers\Settings\UiPresetController;
use App\Http\Controllers\Settings\WidgetEmbedController;
use App\Http\Controllers\Settings\WorkspaceAiAgentController;
use App\Http\Controllers\Settings\WorkspaceApiConnectController;
use App\Http\Controllers\Settings\WorkspaceContactController;
use App\Http\Controllers\Settings\WorkspaceCurrencyController;
use App\Http\Controllers\Settings\WorkspaceLocaleController;
use App\Http\Controllers\Settings\WorkspaceNovaVoiceController;
use App\Http\Controllers\Settings\WorkspaceHubController;
use App\Http\Controllers\SharedFileController;
use App\Http\Controllers\StripeBillingPortalController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\Webhooks\FlouciWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\ShopifyWebhookController;
use App\Http\Controllers\Webhooks\WooCommerceWebhookController;
use App\Http\Controllers\Vault\PortalVaultController;
use App\Http\Controllers\Vault\ProviderVaultController;
use App\Http\Controllers\Vault\VaultController;
use App\Http\Controllers\Vault\VaultPublicShareController;
use App\Http\Controllers\Vault\VaultShareLinkController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', StripeWebhookController::class)->name('webhooks.stripe');
Route::post('/webhooks/flouci', FlouciWebhookController::class)->name('webhooks.flouci');
Route::post('/webhooks/shopify', ShopifyWebhookController::class)->name('webhooks.shopify');
Route::post('/webhooks/woocommerce', WooCommerceWebhookController::class)->name('webhooks.woocommerce');

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update');

Route::get('/', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if ($user) {
        return flowdesk_redirect_after_auth($user, $request);
    }

    return redirect()->route('login');
})->name('home');
Route::get('/embed/widget.js', [EmbedAssetController::class, 'widget'])->name('embed.widget');
Route::get('/embed/marketing-tracker.js', [EmbedAssetController::class, 'marketingTracker'])->name('embed.marketing-tracker');

// Legacy marketing URLs → login
foreach ([
    '/features',
    '/framework',
    '/about',
    '/pricing',
    '/contact',
    '/terms',
    '/privacy',
    '/cookies',
    '/legal',
    '/cgv',
    '/mentions-legales',
] as $legacyMarketingPath) {
    Route::permanentRedirect($legacyMarketingPath, '/login');
}

Route::get('/modules', [MarketingController::class, 'modules'])->name('marketing.modules');
Route::get('/modules/{slug}/{page?}', [ModulePageController::class, 'show'])
    ->where('page', '[a-zA-Z0-9_\-\/]+')
    ->name('modules.show');
Route::get('/cart', [MarketplaceCartController::class, 'index'])->name('marketing.cart');
Route::post('/cart/add/{marketplaceModule}', [MarketplaceCartController::class, 'add'])->name('marketing.cart.add');
Route::post('/cart/remove', [MarketplaceCartController::class, 'remove'])->name('marketing.cart.remove');
Route::post('/cart/currency', [MarketplaceCartController::class, 'updateCurrency'])->name('marketing.cart.currency');
Route::get('/checkout', [MarketplaceCheckoutController::class, 'show'])->name('marketing.checkout');
Route::post('/checkout', [MarketplaceCheckoutController::class, 'store'])->name('marketing.checkout.store');
Route::get('/checkout/success', [MarketplaceCheckoutController::class, 'success'])->name('marketing.checkout.success');
Route::get('/checkout/cancel/{order}', [MarketplaceCheckoutController::class, 'cancel'])->name('marketing.checkout.cancel');
Route::get('/checkout/pending/{order}', [MarketplaceCheckoutController::class, 'pending'])->name('marketing.checkout.pending');
Route::get('/orders/{order}/modules/{module}/download', [MarketplaceCheckoutController::class, 'download'])
    ->name('marketing.order.download')
    ->middleware('signed');
Route::get('/sitemap.xml', [MarketingController::class, 'sitemap'])->name('marketing.sitemap');

Route::prefix('demo/nova')->name('demo.nova.')->group(function () {
    Route::get('/status', [NovaPublicDemoController::class, 'status'])->middleware('throttle:60,1')->name('status');
    Route::post('/chat', [NovaPublicDemoController::class, 'chat'])->middleware('throttle:20,1')->name('chat');
    Route::post('/speak', [NovaPublicDemoController::class, 'speak'])->middleware('throttle:30,1')->name('speak');
    Route::post('/live/session', [NovaPublicDemoController::class, 'liveSession'])->middleware('throttle:10,1')->name('live.session');
    Route::post('/live/turn', [NovaPublicDemoController::class, 'liveTurn'])->middleware('throttle:20,1')->name('live.turn');
});

Route::get('/e/o/{token}', [EmailOpenTrackingController::class, 'pixel'])
    ->name('email-marketing.tracking.open')
    ->middleware('throttle:120,1');

// Public vault file sharing (with or without password).
Route::get('/share/f/{token}', [SharedFileController::class, 'show'])
    ->name('share.file.show')
    ->middleware('throttle:60,1');
Route::post('/share/f/{token}', [SharedFileController::class, 'download'])
    ->name('share.file.download')
    ->middleware('throttle:20,1');

// Public Coffre-fort share links (password optional, throttled).
Route::get('/v/s/{token}', [VaultPublicShareController::class, 'show'])->name('vault-share.show')->middleware('throttle:60,1');
Route::post('/v/s/{token}', [VaultPublicShareController::class, 'unlock'])->name('vault-share.unlock')->middleware('throttle:20,1');
Route::get('/v/s/{token}/download', [VaultPublicShareController::class, 'download'])->name('vault-share.download')->middleware('throttle:30,1');
Route::get('/v/s/{token}/preview', [VaultPublicShareController::class, 'preview'])->name('vault-share.preview')->middleware('throttle:60,1');

Route::middleware('guest')->group(function () {
    Route::get('/partner/{slug}', [PartnerRegistrationController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('partner.register.show');
    Route::post('/partner/{slug}', [PartnerRegistrationController::class, 'store'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->middleware('throttle:10,1')
        ->name('partner.register.store');
});


Route::match(['GET', 'POST', 'DELETE'], '/mcp', [CompanyMcpController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('mcp');
// OAuth 2.1 for the workspace MCP (ChatGPT / Claude custom connectors) — t71u.
Route::get('/.well-known/oauth-protected-resource', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'protectedResource'])->name('mcp.oauth.prm');
Route::get('/.well-known/oauth-protected-resource/mcp', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'protectedResource'])->name('mcp.oauth.prm.mcp');
Route::get('/.well-known/oauth-authorization-server', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'authorizationServer'])->name('mcp.oauth.asm');
Route::get('/.well-known/oauth-authorization-server/mcp', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'authorizationServer'])->name('mcp.oauth.asm.mcp');
Route::post('/mcp/oauth/register', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'register'])->middleware('throttle:20,1')->name('mcp.oauth.register');
Route::get('/mcp/oauth/authorize', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'authorize'])->middleware('throttle:60,1')->name('mcp.oauth.authorize');
Route::post('/mcp-oauth/approve', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'approve'])->middleware(['auth', 'throttle:30,1'])->name('mcp.oauth.approve');
Route::post('/mcp/oauth/token', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'token'])->middleware('throttle:60,1')->name('mcp.oauth.token');
Route::post('/mcp/oauth/revoke', [\App\Http\Controllers\Mcp\McpOAuthController::class, 'revoke'])->middleware('throttle:60,1')->name('mcp.oauth.revoke');
Route::match(['GET', 'POST', 'DELETE'], '/admin/mcp', [AdminMcpController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('admin.mcp');

Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('themes', [AdminThemeLibraryController::class, 'index'])->name('themes.index');
    Route::post('themes', [AdminThemeLibraryController::class, 'store'])->name('themes.store');
    Route::delete('themes/{key}', [AdminThemeLibraryController::class, 'destroy'])->name('themes.destroy');
    Route::get('invoice-pdf-templates', [AdminInvoicePdfTemplateLibraryController::class, 'index'])->name('invoice-pdf-templates.index');
    Route::post('invoice-pdf-templates', [AdminInvoicePdfTemplateLibraryController::class, 'store'])->name('invoice-pdf-templates.store');
    Route::delete('invoice-pdf-templates/{key}', [AdminInvoicePdfTemplateLibraryController::class, 'destroy'])
        ->where('key', '[a-z0-9_]+')
        ->name('invoice-pdf-templates.destroy');
    Route::resource('email-template-models', AdminEmailMarketingTemplateModelController::class)
        ->parameters(['email-template-models' => 'emailMarketingTemplateModel'])
        ->except(['show']);
    Route::get('platform-appearance', [PlatformAppearanceController::class, 'edit'])->name('platform-appearance.edit');
    Route::put('platform-appearance', [PlatformAppearanceController::class, 'update'])->name('platform-appearance.update');
    Route::resource('companies', AdminCompanyController::class)->only(['index', 'show', 'create', 'store', 'destroy']);
    Route::put('companies/{company}/status', [AdminCompanyController::class, 'updateStatus'])->name('companies.status');
    Route::put('companies/{company}/plan', [AdminCompanyController::class, 'updatePlan'])->name('companies.plan');
    Route::put('companies/{company}/nova-voice', [AdminCompanyController::class, 'updateNovaVoice'])->name('companies.nova-voice');
    Route::post('companies/{company}/notice', [AdminCompanyNoticeController::class, 'store'])->name('companies.notice');
    Route::post('companies/{company}/welcome', [AdminCompanyController::class, 'resendWelcome'])->middleware('throttle:6,1')->name('companies.welcome');
    Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update', 'destroy']);
    Route::resource('plans', AdminPlanController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::put('plans/{plan}/limits', [AdminPlanController::class, 'updateLimits'])->name('plans.limits.update');
    Route::resource('marketplace-modules', AdminMarketplaceModuleController::class)
        ->parameters(['marketplace-modules' => 'marketplaceModule'])
        ->except(['show']);
    Route::get('marketplace-orders', [AdminMarketplaceOrderController::class, 'index'])->name('marketplace-orders.index');
    Route::get('marketplace-orders/{marketplaceOrder}', [AdminMarketplaceOrderController::class, 'show'])->name('marketplace-orders.show');
    Route::put('marketplace-orders/{marketplaceOrder}/status', [AdminMarketplaceOrderController::class, 'updateStatus'])->name('marketplace-orders.status');
    Route::get('subscription-orders', [AdminWorkspaceSubscriptionOrderController::class, 'index'])->name('subscription-orders.index');
    Route::get('subscription-orders/{subscriptionOrder}', [AdminWorkspaceSubscriptionOrderController::class, 'show'])->name('subscription-orders.show');
    Route::put('subscription-orders/{subscriptionOrder}/status', [AdminWorkspaceSubscriptionOrderController::class, 'updateStatus'])->name('subscription-orders.status');
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('payment-gateways', [PaymentGatewaySettingsController::class, 'edit'])->name('payment-gateways.edit');
    Route::put('payment-gateways', [PaymentGatewaySettingsController::class, 'update'])->name('payment-gateways.update');
    Route::get('settings', [PlatformSettingsController::class, 'edit'])->name('platform-settings.edit');
    Route::get('mcp-settings', [AdminMcpSettingsController::class, 'show'])->name('mcp-settings.show');
    Route::post('mcp-settings', [AdminMcpSettingsController::class, 'store'])->middleware('throttle:10,1')->name('mcp-settings.store');
    Route::delete('mcp-settings/{mcpToken}', [AdminMcpSettingsController::class, 'destroy'])->name('mcp-settings.destroy');

    Route::put('settings', [PlatformSettingsController::class, 'update'])->name('platform-settings.update');
    Route::get('settings/export-sql', [PlatformSettingsController::class, 'exportSql'])->name('platform-settings.export-sql');
    Route::get('developer-docs', [DeveloperDocumentationController::class, 'index'])->name('developer-docs.index');
    Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [AdminPasswordController::class, 'update'])->name('profile.password');
    Route::delete('profile', [AdminProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'tenant.match'])->group(function () {
    // Shared tenant features (safe for workspace staff, clients, and providers)
    Route::post('/product-tour/dismiss', [ProductTourController::class, 'dismiss'])
        ->middleware('throttle:20,1')
        ->name('product-tour.dismiss');
    Route::post('/product-tour/reset', [ProductTourController::class, 'reset'])
        ->middleware('throttle:20,1')
        ->name('product-tour.reset');
    Route::post('/clients/quick-store', ClientQuickStoreController::class)->name('clients.quick-store');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/widget/bootstrap', [ChatController::class, 'widgetBootstrap'])->name('chat.widget.bootstrap');
    Route::get('/chat/clients/{client}/open', [ChatController::class, 'openClient'])->name('chat.open.client');
    Route::get('/chat/providers/{provider}/open', [ChatController::class, 'openProvider'])->name('chat.open.provider');
    Route::get('/chat/{thread}/messages/full', [ChatController::class, 'messagesFull'])->name('chat.messages.full');
    Route::get('/chat/{thread}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{thread}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::get('/chat/{thread}/messages/poll', [ChatController::class, 'messagesJson'])->name('chat.messages.poll');

    Route::get('/tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [SupportTicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('/tickets/{ticket}/replies', [SupportTicketController::class, 'storeReply'])->name('tickets.replies.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'tenant.match', 'workspace.staff'])->group(function () {
    // Company workspace root
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::middleware(['plan.feature:calendar'])->group(function () {
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/preview', [CalendarController::class, 'preview'])->name('calendar.preview');
        Route::post('/calendar/events', [CalendarEventController::class, 'store'])->name('calendar.events.store');
        Route::delete('/calendar/events/{event}', [CalendarEventController::class, 'destroy'])->name('calendar.events.destroy');
        Route::post('/calendar/sync/google', [CalendarEventController::class, 'syncGoogle'])->name('calendar.sync.google');
    });

    Route::post('/profile/embed-token', [ProfileController::class, 'regenerateEmbedToken'])
        ->middleware('plan.feature:widgets')
        ->name('profile.embed-token.regenerate');

    Route::middleware(['plan.feature:reports'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::middleware(['plan.feature:ai_credits'])->group(function () {
            Route::post('/reports/ai-counsel', [ReportController::class, 'aiCounsel'])->name('reports.ai-counsel');
            Route::post('/reports/ai-counsel/pdf', [ReportController::class, 'exportCounselPdf'])->name('reports.ai-counsel.pdf');
        });
    });

    Route::middleware(['plan.feature:analytics'])->group(function () {
        Route::get('/analytics', AnalyticsController::class)->name('analytics.index');
    });

    Route::middleware(['plan.feature:marketing_hub'])->group(function () {
        Route::get('/marketing', [MarketingHubController::class, 'index'])->name('marketing.hub');
        Route::patch('/marketing', [MarketingHubController::class, 'update'])->name('marketing.hub.update');
    });

    Route::middleware(['plan.feature:email_marketing'])->prefix('email-marketing')->name('email-marketing.')->group(function () {
        Route::get('/', EmailMarketingDashboardController::class)->name('index');
        Route::post('/campaigns/preview-email', EmailMarketingPreviewEmailController::class)->name('campaigns.preview-email');
        Route::post('/campaigns/{campaign}/send', [EmailMarketingCampaignController::class, 'send'])->name('campaigns.send');
        Route::post('/campaigns/{campaign}/sample', [EmailMarketingCampaignController::class, 'sendSample'])->name('campaigns.sample');
        Route::resource('campaigns', EmailMarketingCampaignController::class)->only([
            'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
        ]);
        Route::middleware(['plan.feature:ai_credits'])->group(function () {
            Route::post('/templates/ai', EmailMarketingTemplateAiController::class)->name('templates.ai');
            Route::post('/campaigns/content/ai', EmailMarketingCampaignContentAiController::class)->name('campaigns.content-ai');
        });
        Route::post('/templates/from-model/{slug}', [EmailMarketingTemplateController::class, 'storeFromModel'])
            ->where('slug', '[a-z0-9_]+')
            ->name('templates.from-model');
        Route::resource('templates', EmailMarketingTemplateController::class)->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]);
        Route::resource('audiences', EmailMarketingAudienceController::class)->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]);
        Route::get('/sequences', [EmailMarketingSequenceController::class, 'index'])->name('sequences.index');
    });

    Route::middleware(['plan.feature:hr'])->prefix('hr')->name('hr.')->group(function () {
        Route::get('/', HrDashboardController::class)->name('index');
        Route::get('/departments', [HrDepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [HrDepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [HrDepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [HrDepartmentController::class, 'destroy'])->name('departments.destroy');
        Route::post('/employees/sync-team', [HrEmployeeController::class, 'syncTeam'])->name('employees.sync-team');
        Route::resource('employees', HrEmployeeController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::get('/leave', [HrLeaveController::class, 'index'])->name('leave.index');
        Route::post('/leave', [HrLeaveController::class, 'store'])->name('leave.store');
        Route::post('/leave/{leaveRequest}/approve', [HrLeaveController::class, 'approve'])->name('leave.approve');
        Route::post('/leave/{leaveRequest}/reject', [HrLeaveController::class, 'reject'])->name('leave.reject');
        Route::get('/payroll', [HrPayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll', [HrPayrollController::class, 'store'])->name('payroll.store');
        Route::get('/payroll/payslips/{payslip}/pdf', [HrPayrollController::class, 'downloadPayslip'])->name('payroll.payslips.pdf');
        Route::get('/payroll/{payrollRun}', [HrPayrollController::class, 'show'])->name('payroll.show');
        Route::post('/payroll/{payrollRun}/generate', [HrPayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/{payrollRun}/finalize', [HrPayrollController::class, 'finalize'])->name('payroll.finalize');
        Route::post('/payroll/{payrollRun}/mark-paid', [HrPayrollController::class, 'markPaid'])->name('payroll.mark-paid');

        Route::get('/settings', [HrSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/rules', [HrSettingsController::class, 'storeRule'])->name('settings.rules.store');
        Route::put('/settings/rules/{rule}', [HrSettingsController::class, 'updateRule'])->name('settings.rules.update');
        Route::delete('/settings/rules/{rule}', [HrSettingsController::class, 'destroyRule'])->name('settings.rules.destroy');

        Route::get('/advances', [HrAdvanceController::class, 'index'])->name('advances.index');
        Route::post('/advances', [HrAdvanceController::class, 'store'])->name('advances.store');
        Route::post('/advances/{advance}/approve', [HrAdvanceController::class, 'approve'])->name('advances.approve');
        Route::post('/advances/{advance}/reject', [HrAdvanceController::class, 'reject'])->name('advances.reject');

        Route::get('/me/leave', [HrSelfServiceController::class, 'leave'])->name('me.leave');
        Route::post('/me/leave', [HrSelfServiceController::class, 'storeLeave'])->name('me.leave.store');
        Route::get('/me/advances', [HrSelfServiceController::class, 'advances'])->name('me.advances');
        Route::post('/me/advances', [HrSelfServiceController::class, 'storeAdvance'])->name('me.advances.store');
        Route::get('/me/payslips', [HrSelfServiceController::class, 'payslips'])->name('me.payslips');
        Route::get('/me/payslips/{payslip}/pdf', [HrSelfServiceController::class, 'downloadPayslip'])->name('me.payslips.pdf');
    });

    // Catalog for quotes/invoices — available without the full ecommerce module.
    Route::prefix('commerce')->name('commerce.')->group(function () {
        Route::get('/products', [CommerceProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [CommerceProductController::class, 'create'])->name('products.create');
        Route::post('/products/ai-copy', [CommerceProductAiController::class, 'suggestCopy'])->name('products.ai-copy');
        Route::post('/products/ai-catalog', [CommerceProductAiController::class, 'generateCatalog'])->name('products.ai-catalog');
        Route::post('/products', [CommerceProductController::class, 'store'])->name('products.store');
        Route::delete('/products/bulk', [CommerceProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
        Route::get('/products/{product}', [CommerceProductController::class, 'show'])->name('products.show');
        Route::delete('/products/{product}', [CommerceProductController::class, 'destroy'])->name('products.destroy');
        Route::put('/products/{product}', [CommerceProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/variants', [CommerceProductController::class, 'storeVariant'])->name('products.variants.store');
        Route::post('/variants/{variant}/stock', [CommerceProductController::class, 'adjustStock'])->name('variants.stock');
    });

    Route::middleware(['plan.feature:ecommerce'])->prefix('commerce')->name('commerce.')->group(function () {
        Route::get('/orders', [CommerceOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [CommerceOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [CommerceOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [CommerceOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/invoice', [CommerceOrderController::class, 'invoice'])->name('orders.invoice');
        Route::get('/deliveries', [CommerceDeliveryController::class, 'index'])->name('deliveries.index');
        Route::put('/deliveries/{delivery}', [CommerceDeliveryController::class, 'update'])->name('deliveries.update');
    });

    Route::middleware(['plan.feature:pos'])->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/open', [PosController::class, 'open'])->name('open');
        Route::post('/sell', [PosController::class, 'sell'])->name('sell');
        Route::post('/{posSession}/close', [PosController::class, 'close'])->name('close');
    });

    Route::middleware(['plan.feature:real_estate'])->prefix('real-estate')->name('real-estate.')->group(function () {
        Route::get('/offers', [RealEstateOfferController::class, 'index'])->name('offers.index');
        Route::get('/offers/create', [RealEstateOfferController::class, 'create'])->name('offers.create');
        Route::post('/offers', [RealEstateOfferController::class, 'store'])->name('offers.store');
        Route::get('/offers/{offer}', [RealEstateOfferController::class, 'show'])->name('offers.show');
        Route::put('/offers/{offer}', [RealEstateOfferController::class, 'update'])->name('offers.update');
        Route::post('/offers/{offer}/images', [RealEstateOfferController::class, 'storeImage'])->name('offers.images.store');
        Route::delete('/images/{image}', [RealEstateOfferController::class, 'destroyImage'])->name('images.destroy');
        Route::get('/demands', [RealEstateDemandController::class, 'index'])->name('demands.index');
        Route::get('/demands/create', [RealEstateDemandController::class, 'create'])->name('demands.create');
        Route::post('/demands', [RealEstateDemandController::class, 'store'])->name('demands.store');
        Route::get('/contracts', [RealEstateContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/create', [RealEstateContractController::class, 'create'])->name('contracts.create');
        Route::post('/contracts', [RealEstateContractController::class, 'store'])->name('contracts.store');
    });

    Route::get('/billing', BillingController::class)->name('billing.index');
    Route::post('/billing/plans', [BillingPlanController::class, 'store'])->name('billing.plans.store');
    Route::get('/billing/checkout/{order}', [BillingCheckoutController::class, 'show'])->name('billing.checkout.show');
    Route::post('/billing/checkout/{order}/pay', [BillingCheckoutController::class, 'pay'])->name('billing.checkout.pay');
    Route::post('/billing/checkout/{order}/notify-admin', [BillingCheckoutController::class, 'notifyAdmin'])
        ->middleware('throttle:5,1')
        ->name('billing.checkout.notify');
    Route::get('/billing/checkout/{order}/success', [BillingCheckoutController::class, 'success'])->name('billing.checkout.success');
    Route::get('/billing/checkout/{order}/paypal/return', [BillingCheckoutController::class, 'paypalReturn'])->name('billing.checkout.paypal.return');
    Route::get('/billing/checkout/{order}/flouci/return', [BillingCheckoutController::class, 'flouciReturn'])->name('billing.checkout.flouci.return');
    Route::get('/billing/checkout/{order}/pending', [BillingCheckoutController::class, 'pending'])->name('billing.checkout.pending');
    Route::post('/billing/stripe-portal', StripeBillingPortalController::class)->name('billing.stripe-portal');

    Route::get('/assistant/voice-status', [WorkspaceNovaVoiceController::class, 'status'])->middleware('throttle:60,1')->name('assistant.voice-status');

    Route::middleware(['plan.feature:ai_credits'])->group(function () {
        Route::get('/assistant', [AiAssistantController::class, 'index'])->name('assistant.index');
        Route::get('/assistant/summary', [AiAssistantController::class, 'summary'])->name('assistant.summary');
        Route::post('/assistant/chat', [AiAssistantController::class, 'chat'])->name('assistant.chat');
        Route::post('/assistant/speak', [AiAssistantController::class, 'speak'])->name('assistant.speak')->middleware('nova.voice');
        // AI chat panel read-aloud: only the platform admin switch applies (not the company top-bar Nova switch).
        Route::post('/assistant/chat-speak', [AiAssistantController::class, 'speak'])->name('assistant.chat-speak')->middleware(['nova.voice:chat', 'throttle:30,1']);
        Route::post('/assistant/briefing', [AiAssistantController::class, 'briefing'])->name('assistant.briefing')->middleware('nova.voice');
        Route::post('/assistant/suggest', [AiAssistantController::class, 'suggest'])->name('assistant.suggest');
        Route::post('/assistant/voice-intent', [AiAssistantController::class, 'voiceIntent'])->name('assistant.voice-intent')->middleware('nova.voice');
        Route::post('/assistant/voice-workflow', [AiAssistantController::class, 'voiceWorkflow'])->name('assistant.voice-workflow')->middleware('nova.voice');
        Route::post('/assistant/live/session', [AiAssistantController::class, 'liveSession'])->name('assistant.live.session')->middleware('nova.voice');
        Route::post('/assistant/live/tools', [AiAssistantController::class, 'liveTools'])->name('assistant.live.tools')->middleware('nova.voice');
        Route::post('/assistant/live/turn', [AiAssistantController::class, 'liveTurn'])->name('assistant.live.turn')->middleware('nova.voice');
        Route::post('/assistant/proposal-prefill', [AiAssistantController::class, 'proposalPrefill'])->name('assistant.proposal-prefill');
        Route::post('/assistant/proposal-client-context', [AiAssistantController::class, 'proposalClientContext'])->name('assistant.proposal-client-context');
        Route::get('/growth-advisor', GrowthAdvisorController::class)->name('growth-advisor.index');
    });

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');

    Route::get('/prospects', [ProspectController::class, 'index'])->name('prospects.index');
    Route::get('/prospects/create', [ProspectController::class, 'create'])->name('prospects.create');
    Route::post('/prospects', [ProspectController::class, 'store'])->name('prospects.store');
    Route::get('/import', [DataImportController::class, 'index'])->name('import.index');
    Route::get('/import/{type}/example.csv', [DataImportController::class, 'example'])->name('import.example')->whereIn('type', \App\Services\DataImportService::TYPES);
    Route::get('/import/{type}', [DataImportController::class, 'previewForm'])->name('import.preview.form')->whereIn('type', \App\Services\DataImportService::TYPES);
    Route::post('/import/{type}/preview', [DataImportController::class, 'preview'])->name('import.preview')->whereIn('type', \App\Services\DataImportService::TYPES);
    Route::post('/import/{type}', [DataImportController::class, 'store'])->name('import.store')->whereIn('type', \App\Services\DataImportService::TYPES);

    Route::get('/prospects/import', [ProspectController::class, 'importForm'])->name('prospects.import');
    Route::post('/prospects/import/preview', [ProspectController::class, 'importPreview'])->name('prospects.import.preview');
    Route::post('/prospects/import', [ProspectController::class, 'importStore'])->name('prospects.import.store');
    Route::get('/prospects/{prospect}', [ProspectController::class, 'show'])->name('prospects.show');
    Route::get('/prospects/{prospect}/edit', [ProspectController::class, 'edit'])->name('prospects.edit');
    Route::put('/prospects/{prospect}', [ProspectController::class, 'update'])->name('prospects.update');
    Route::delete('/prospects/{prospect}', [ProspectController::class, 'destroy'])->name('prospects.destroy');
    Route::post('/prospects/{prospect}/verify', [ProspectController::class, 'verify'])->name('prospects.verify');
    Route::post('/prospects/{prospect}/convert', [ProspectController::class, 'convert'])->name('prospects.convert');
    Route::post('/prospects/{prospect}/calls', [ProspectController::class, 'storeCall'])->name('prospects.calls.store');
    Route::post('/prospects/{prospect}/feedbacks', [ProspectController::class, 'storeFeedback'])->name('prospects.feedbacks.store');
    Route::post('/prospects/{prospect}/meetings', [ProspectController::class, 'storeMeeting'])->name('prospects.meetings.store');
    Route::post('/prospects/{prospect}/quote', [ProspectController::class, 'prepareQuote'])->name('prospects.quote.prepare');
    Route::post('/prospects/{prospect}/mail', [ProspectController::class, 'storeMail'])->name('prospects.mail.store');
    Route::post('/prospects/{prospect}/reminders', [ProspectController::class, 'storeReminder'])->name('prospects.reminders.store');
    Route::post('/prospects/{prospect}/ai-catch', [ProspectController::class, 'generateAiCatch'])->name('prospects.ai-catch');
    Route::get('/clients/account-requests', [ClientAccountRequestReviewController::class, 'index'])->name('clients.account-requests.index');
    Route::post('/clients/account-requests/{clientAccountRequest}/approve', [ClientAccountRequestReviewController::class, 'approve'])->name('clients.account-requests.approve');
    Route::post('/clients/account-requests/{clientAccountRequest}/reject', [ClientAccountRequestReviewController::class, 'reject'])->name('clients.account-requests.reject');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::post('/clients/{client}/portal', [ClientPortalAccountController::class, 'store'])->name('clients.portal.store');
    Route::post('/clients/{client}/portal/password', [ClientPortalAccountController::class, 'resetPassword'])->name('clients.portal.password');
    Route::post('/clients/{client}/portal/disable', [ClientPortalAccountController::class, 'disable'])->name('clients.portal.disable');
    Route::post('/clients/{client}/portal/enable', [ClientPortalAccountController::class, 'enable'])->name('clients.portal.enable');
    Route::post('/clients/{client}/portal/send-access', [ClientPortalAccountController::class, 'sendAccessLink'])->middleware('throttle:10,1')->name('clients.portal.send-access');
    Route::put('/clients/{client}/portal/visibility', [PortalVisibilitySettingsController::class, 'updateClient'])->name('clients.portal.visibility');
    Route::post('/clients/{client}/prospects', [ClientProspectController::class, 'store'])->name('clients.prospects.store');
    Route::post('/clients/{client}/prospects/import', [ClientProspectController::class, 'import'])->name('clients.prospects.import');
    Route::put('/clients/{client}/prospects/{prospect}', [ClientProspectController::class, 'update'])->name('clients.prospects.update');
    Route::delete('/clients/{client}/prospects/{prospect}', [ClientProspectController::class, 'destroy'])->name('clients.prospects.destroy');
    Route::post('/clients/{client}/campaigns', [ClientCampaignController::class, 'store'])->name('clients.campaigns.store');
    Route::put('/clients/{client}/campaigns/{campaign}', [ClientCampaignController::class, 'update'])->name('clients.campaigns.update');
    Route::delete('/clients/{client}/campaigns/{campaign}', [ClientCampaignController::class, 'destroy'])->name('clients.campaigns.destroy');
    Route::post('/clients/{client}/bookings', [ClientBookingController::class, 'store'])->name('clients.bookings.store');
    Route::post('/clients/{client}/bookings/import', [ClientBookingController::class, 'import'])->name('clients.bookings.import');
    Route::put('/clients/{client}/bookings/{booking}', [ClientBookingController::class, 'update'])->name('clients.bookings.update');
    Route::delete('/clients/{client}/bookings/{booking}', [ClientBookingController::class, 'destroy'])->name('clients.bookings.destroy');
    Route::post('/clients/{client}/meetings', [ClientFollowUpController::class, 'storeMeeting'])->name('clients.meetings.store');
    Route::post('/clients/{client}/reminders', [ClientFollowUpController::class, 'storeReminder'])->name('clients.reminders.store');
    Route::post('/clients/{client}/notes', [ClientFollowUpController::class, 'storeNote'])->name('clients.notes.store');
    Route::post('/clients/{client}/tasks', [ClientFollowUpController::class, 'storeTask'])->name('clients.tasks.store');
    Route::post('/clients/{client}/feedbacks', [ClientFollowUpController::class, 'storeFeedback'])->name('clients.feedbacks.store');
    Route::post('/clients/{client}/meetings/{event}/summary/generate', [ClientFollowUpController::class, 'generateMeetingSummary'])->name('clients.meetings.summary.generate');
    Route::post('/clients/{client}/meetings/{event}/summary/import-meet', [ClientFollowUpController::class, 'importMeetTranscriptSummary'])->name('clients.meetings.summary.import-meet');
    Route::patch('/clients/{client}/meetings/{event}/summary', [ClientFollowUpController::class, 'updateMeetingSummary'])->name('clients.meetings.summary');
    Route::post('/clients/{client}/meetings/{event}/invite', [ClientFollowUpController::class, 'sendMeetingInvite'])->name('clients.meetings.invite');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    Route::resource('inquiries', InquiryController::class)->except(['edit']);
    Route::middleware(['plan.feature:projects'])->group(function () {
        Route::post('inquiries/{inquiry}/convert-project', [InquiryController::class, 'convertToProject'])->name('inquiries.convert-project');
    });

    // Coffre-fort (fichiers partagés)
    Route::get('vault', [VaultController::class, 'index'])->name('vault.index');
    Route::post('vault', [VaultController::class, 'store'])->name('vault.store');
    Route::get('vault/{file}/download', [VaultController::class, 'download'])->name('vault.download');
    Route::get('vault/{file}/preview', [VaultController::class, 'preview'])->name('vault.preview');
    Route::delete('vault/{file}', [VaultController::class, 'destroy'])->name('vault.destroy');
    Route::put('vault/{file}', [VaultController::class, 'update'])->name('vault.update');
    Route::get('vault-trash', [VaultController::class, 'trash'])->name('vault.trash');
    Route::post('vault-trash/{file}/restore', [VaultController::class, 'restore'])->name('vault.restore');
    Route::delete('vault-trash/{file}', [VaultController::class, 'forceDestroy'])->name('vault.force-destroy');
    Route::post('vault/{file}/links', [VaultShareLinkController::class, 'store'])->name('vault.links.store');
    Route::delete('vault/{file}/links/{share}', [VaultShareLinkController::class, 'destroy'])->name('vault.links.destroy');
    Route::post('vault/{file}/links/{share}/email', [VaultShareLinkController::class, 'email'])->name('vault.links.email');

    Route::middleware(['plan.feature:projects'])->group(function () {
        Route::middleware(['plan.feature:ai_credits'])->group(function () {
            Route::post('projects/ai/example-workspace', [ProjectAiController::class, 'createExampleWorkspace'])->name('projects.ai.example-workspace');
        });
        Route::get('projects/import', [ProjectController::class, 'importForm'])->name('projects.import');
        Route::get('projects/import/example.csv', [ProjectController::class, 'importExample'])->name('projects.import.example');
        Route::post('projects/import', [ProjectController::class, 'importStore'])->name('projects.import.store');
        Route::post('projects/{project}/notion-sync', [ProjectController::class, 'syncNotion'])->name('projects.notion-sync');
        Route::post('projects/{project}/trello-sync', [ProjectController::class, 'syncTrello'])->name('projects.trello-sync');
        Route::resource('projects', ProjectController::class);
        Route::post('projects/{project}/files', [ProjectController::class, 'storeFile'])->name('projects.files.store');
        Route::delete('projects/{project}/files/{file}', [ProjectController::class, 'destroyFile'])->name('projects.files.destroy');
        Route::get('projects/{project}/vault/{file}/download', [ProjectFileVaultController::class, 'download'])->name('projects.vault.download');
        Route::post('projects/{project}/vault/{file}/shares', [ProjectFileVaultController::class, 'storeShare'])->name('projects.vault.shares.store');
        Route::delete('projects/{project}/vault/{file}/shares/{share}', [ProjectFileVaultController::class, 'destroyShare'])->name('projects.vault.shares.destroy');
        Route::middleware(['plan.feature:ai_credits'])->group(function () {
            Route::post('projects/{project}/ai/generate-workflow', [ProjectAiController::class, 'generateWorkflow'])->name('projects.ai.generate-workflow');
        });
        Route::patch('projects/{project}/team', [ProjectController::class, 'updateTeam'])->name('projects.team');
        Route::post('projects/{project}/installments', [ProjectInstallmentController::class, 'store'])->name('projects.installments.store');
        Route::patch('projects/{project}/installments/{installment}', [ProjectInstallmentController::class, 'update'])->name('projects.installments.update');
        Route::delete('projects/{project}/installments/{installment}', [ProjectInstallmentController::class, 'destroy'])->name('projects.installments.destroy');
        Route::get('projects/{project}/tasks/kanban', [ProjectTaskController::class, 'kanban'])->name('projects.tasks.kanban');
        Route::get('projects/{project}/tasks/gantt', [ProjectTaskController::class, 'gantt'])->name('projects.tasks.gantt');
        Route::post('projects/{project}/tasks/reorder', [ProjectTaskController::class, 'reorder'])->name('projects.tasks.reorder');
        Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])->name('projects.tasks.update');
        Route::post('projects/{project}/tasks/{task}/tracking/start', [ProjectTaskController::class, 'trackingStart'])->name('projects.tasks.tracking.start');
        Route::post('projects/{project}/tasks/{task}/tracking/pause', [ProjectTaskController::class, 'trackingPause'])->name('projects.tasks.tracking.pause');
        Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::post('projects/{project}/tasks/{task}/files', [ProjectTaskController::class, 'storeFile'])->name('projects.tasks.files.store');
        Route::post('projects/{project}/tasks/{task}/comments', [ProjectTaskController::class, 'storeComment'])->name('projects.tasks.comments.store');
        Route::delete('projects/{project}/tasks/{task}/files/{file}', [ProjectTaskController::class, 'destroyFile'])->name('projects.tasks.files.destroy');
    });

    Route::post('invoices/ai-line-items', [InvoiceAiController::class, 'suggestDraft'])->name('invoices.ai-line-items.draft');
    Route::post('ai/sidebar-draft', [\App\Http\Controllers\AiSidebarDraftController::class, 'store'])->middleware('throttle:12,1')->name('ai.sidebar-draft');
    Route::get('ai/chat', [\App\Http\Controllers\AiChatPanelController::class, 'show'])->middleware('throttle:60,1')->name('ai.chat.show');
    Route::post('ai/chat/new', [\App\Http\Controllers\AiChatPanelController::class, 'reset'])->middleware('throttle:20,1')->name('ai.chat.new');
    Route::post('ai/chat', [\App\Http\Controllers\AiChatPanelController::class, 'send'])->middleware('throttle:12,1')->name('ai.chat.send');
    Route::post('invoices/ai-line-items/scan', [InvoiceAiController::class, 'scanDraft'])->name('invoices.ai-line-items.scan.draft');
    Route::post('invoices/{invoice}/ai-line-items', [InvoiceAiController::class, 'suggestLineItems'])->name('invoices.ai-line-items');
    Route::post('invoices/{invoice}/ai-line-items/scan', [InvoiceAiController::class, 'scanLineItems'])->name('invoices.ai-line-items.scan');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/preview-panel', [InvoiceController::class, 'previewPanel'])->name('invoices.preview-panel');
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::patch('invoices/{invoice}/payments/{payment}', [PaymentController::class, 'update'])->name('invoices.payments.update');
    Route::post('invoices/{invoice}/payment-intent', InvoicePaymentIntentController::class)->name('invoices.payment-intent');
    Route::post('invoices/{invoice}/paypal-order', InvoicePayPalController::class)->name('invoices.paypal-order');
    Route::post('invoices/{invoice}/flouci-payment', InvoiceFlouciController::class)->name('invoices.flouci-payment');
    Route::match(['post', 'put'], 'invoices/draft-preview', [\App\Http\Controllers\DocumentDraftPreviewController::class, 'invoice'])->middleware('throttle:30,1')->name('invoices.draft-preview');
    Route::resource('invoices', InvoiceController::class);

    Route::match(['post', 'put'], '/proposals/draft-preview', [\App\Http\Controllers\DocumentDraftPreviewController::class, 'proposal'])->middleware('throttle:30,1')->name('proposals.draft-preview');
    Route::post('/proposals/ai-line-items', [ProposalAiController::class, 'suggestDraft'])->name('proposals.ai-line-items.draft');
    Route::post('/proposals/ai-line-items/scan', [ProposalAiController::class, 'scanDraft'])->name('proposals.ai-line-items.scan.draft');
    Route::post('/proposals/{proposal}/ai-line-items', [ProposalAiController::class, 'suggestLineItems'])->name('proposals.ai-line-items');
    Route::post('/proposals/{proposal}/ai-line-items/scan', [ProposalAiController::class, 'scanLineItems'])->name('proposals.ai-line-items.scan');
    Route::get('/proposals/{proposal}/pdf', [ProposalController::class, 'pdf'])->name('proposals.pdf');
    Route::post('/proposals/{proposal}/send', [ProposalController::class, 'send'])->name('proposals.send');
    Route::post('/proposals/{proposal}/accept', [ProposalController::class, 'accept'])->name('proposals.accept');
    Route::get('/proposals/create', [ProposalController::class, 'create'])->name('proposals.create');
    Route::post('/proposals', [ProposalController::class, 'store'])->name('proposals.store');
    Route::get('/proposals', [ProposalController::class, 'index'])->name('proposals.index');
    Route::get('/proposals/{proposal}/edit', [ProposalController::class, 'edit'])->name('proposals.edit');
    Route::put('/proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
    Route::delete('/proposals/{proposal}', [ProposalController::class, 'destroy'])->name('proposals.destroy');
    Route::get('/proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
    Route::post('/proposals/{proposal}/invoice', [InvoiceController::class, 'fromProposal'])->name('proposals.invoice');
    Route::post('/proposals/{proposal}/negotiations', [NegotiationController::class, 'store'])->name('proposals.negotiations.store');
    Route::post('/negotiations/{negotiation}/accept', [NegotiationController::class, 'accept'])->name('negotiations.accept');
    Route::post('/negotiations/{negotiation}/reject', [NegotiationController::class, 'reject'])->name('negotiations.reject');

    Route::get('/contract-templates', [ContractTemplateController::class, 'index'])->name('contract-templates.index');
    Route::get('/contract-templates/create', [ContractTemplateController::class, 'create'])->name('contract-templates.create');
    Route::post('/contract-templates', [ContractTemplateController::class, 'store'])->name('contract-templates.store');
    Route::get('/contract-templates/{contractTemplate}/edit', [ContractTemplateController::class, 'edit'])->name('contract-templates.edit');
    Route::put('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'update'])->name('contract-templates.update');
    Route::delete('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'destroy'])->name('contract-templates.destroy');

    Route::post('/contracts/ai/generate', [ContractAiController::class, 'generate'])->name('contracts.ai.generate');
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::get('/contracts/samples/{type}/{locale}', [ContractController::class, 'sample'])->name('contracts.samples');
    Route::post('/contracts/preview-template', [ContractController::class, 'previewTemplate'])->name('contracts.preview-template');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');
    Route::post('/contracts/{contract}/send', [ContractController::class, 'send'])->name('contracts.send');
    Route::post('/contracts/{contract}/resend', [ContractController::class, 'resend'])->name('contracts.resend');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');

    Route::post('/project-studies/ai/draft', [ProjectStudyAiController::class, 'draft'])->name('project-studies.ai.draft');
    Route::get('/project-studies', [ProjectStudyController::class, 'index'])->name('project-studies.index');
    Route::get('/project-studies/create', [ProjectStudyController::class, 'create'])->name('project-studies.create');
    Route::post('/project-studies', [ProjectStudyController::class, 'store'])->name('project-studies.store');
    Route::post('/project-studies/{projectStudy}/generate', [ProjectStudyController::class, 'generateAi'])->name('project-studies.generate');
    Route::post('/project-studies/{projectStudy}/proposal', [ProjectStudyController::class, 'storeProposalFromStudy'])->name('project-studies.proposal');
    Route::get('/project-studies/{projectStudy}/pdf', [ProjectStudyController::class, 'pdf'])->name('project-studies.pdf');
    Route::get('/project-studies/{projectStudy}', [ProjectStudyController::class, 'show'])->name('project-studies.show');
    Route::get('/project-studies/{projectStudy}/edit', [ProjectStudyController::class, 'edit'])->name('project-studies.edit');
    Route::put('/project-studies/{projectStudy}', [ProjectStudyController::class, 'update'])->name('project-studies.update');
    Route::delete('/project-studies/{projectStudy}', [ProjectStudyController::class, 'destroy'])->name('project-studies.destroy');

    Route::middleware(['plan.feature:providers'])->group(function () {
        Route::get('providers/remittance-requests', [ProviderRemittanceInboxController::class, 'index'])->name('providers.remittance-requests.index');
        Route::resource('providers', ProviderController::class)->except(['show']);
        Route::post('providers/{provider}/remittance-requests/{remittanceRequest}/approve', [ProviderRemittanceReviewController::class, 'approve'])->name('providers.remittance-requests.approve');
        Route::post('providers/{provider}/remittance-requests/{remittanceRequest}/reject', [ProviderRemittanceReviewController::class, 'reject'])->name('providers.remittance-requests.reject');
        Route::middleware(['role:company_admin'])->group(function () {
            Route::get('providers/{provider}/partnership', [ProviderPartnershipCompanyController::class, 'show'])->name('providers.partnership.show');
            Route::get('providers/{provider}/partnership/contract', [ProviderPartnershipCompanyController::class, 'contract'])->name('providers.partnership.contract');
            Route::get('providers/{provider}/partnership/signature', [ProviderPartnershipCompanyController::class, 'signature'])->name('providers.partnership.signature');
            Route::post('providers/{provider}/partnership/sign', [ProviderPartnershipCompanyController::class, 'sign'])->name('providers.partnership.sign');
        });
    });

    Route::middleware(['plan.feature:forms'])->group(function () {
        Route::post('forms/{form}/fields/reorder', [FormFieldController::class, 'reorder'])->name('forms.fields.reorder');
        Route::patch('forms/{form}/fields/{field}', [FormFieldController::class, 'update'])->name('forms.fields.update');
        Route::post('forms/{form}/fields', [FormFieldController::class, 'store'])->name('forms.fields.store');
        Route::delete('forms/{form}/fields/{field}', [FormFieldController::class, 'destroy'])->name('forms.fields.destroy');
        Route::post('forms/{form}/bump-version', [FormController::class, 'bumpVersion'])->name('forms.bump-version');
        Route::get('forms/{form}/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');
        Route::get('form-submissions/{submission}', [FormSubmissionController::class, 'show'])->name('forms.submissions.show');
        Route::resource('forms', FormController::class)->except(['show']);
    });
    Route::middleware(['plan.feature:projects'])->group(function () {
        Route::post('form-submissions/{submission}/convert-project', [FormSubmissionController::class, 'convertToProject'])->name('form-submissions.convert-project');
    });

    Route::middleware(['plan.feature:modules'])->group(function () {
        Route::post('/modules/{slug}/actions', [ModuleActionController::class, 'handle'])->name('modules.actions');
        Route::get('/settings/modules', [ModulesSettingsController::class, 'index'])->name('settings.modules');
        Route::post('/settings/modules/install', [ModulesSettingsController::class, 'install'])->name('settings.modules.install');
        Route::post('/settings/modules/free/{marketplaceModule}/activate', [ModulesSettingsController::class, 'activateFree'])->name('settings.modules.free.activate');
        Route::post('/settings/modules/purchased/{marketplaceModule}/install', [ModulesSettingsController::class, 'installPurchased'])->name('settings.modules.purchased.install');
        Route::get('/settings/modules/purchased/{marketplaceModule}/download', [ModulesSettingsController::class, 'downloadPurchased'])->name('settings.modules.purchased.download');
        Route::delete('/settings/modules/purchased/{marketplaceOrderItem}', [ModulesSettingsController::class, 'destroyPurchased'])->name('settings.modules.purchased.destroy');
        Route::patch('/settings/modules/{module}/toggle', [ModulesSettingsController::class, 'toggle'])->name('settings.modules.toggle');
        Route::delete('/settings/modules/{module}', [ModulesSettingsController::class, 'destroy'])->name('settings.modules.destroy');

    });

    Route::get('/settings', WorkspaceHubController::class)->name('settings.workspace');

    Route::get('/settings/appearance', [AppearanceController::class, 'edit'])->name('settings.appearance');
    Route::put('/settings/appearance', [AppearanceController::class, 'update'])->name('settings.appearance.update');

    Route::get('/settings/branding', [BrandingController::class, 'edit'])->name('settings.branding');
    Route::put('/settings/branding', [BrandingController::class, 'update'])->name('settings.branding.update');

    Route::get('/settings/workspace-currency', [WorkspaceCurrencyController::class, 'edit'])->name('settings.workspace-currency');
    Route::put('/settings/workspace-currency', [WorkspaceCurrencyController::class, 'update'])->name('settings.workspace-currency.update');

    Route::get('/settings/workspace-locale', [WorkspaceLocaleController::class, 'edit'])->name('settings.workspace-locale');
    Route::put('/settings/workspace-locale', [WorkspaceLocaleController::class, 'update'])->name('settings.workspace-locale.update');

    Route::get('/settings/workspace-contact', [WorkspaceContactController::class, 'edit'])->name('settings.workspace-contact');
    Route::put('/settings/workspace-contact', [WorkspaceContactController::class, 'update'])->name('settings.workspace-contact.update');

    Route::get('/settings/nova-voice', [WorkspaceNovaVoiceController::class, 'edit'])->name('settings.nova-voice');
    Route::put('/settings/nova-voice', [WorkspaceNovaVoiceController::class, 'update'])->name('settings.nova-voice.update');
    Route::put('/settings/nova-voice/toggle', [WorkspaceNovaVoiceController::class, 'toggle'])->name('settings.nova-voice.toggle');

    Route::middleware(['plan.feature:providers'])->group(function () {
        Route::get('/settings/provider-commissions', [ProviderCommissionSettingsController::class, 'edit'])->name('settings.provider-commissions');
        Route::put('/settings/provider-commissions', [ProviderCommissionSettingsController::class, 'update'])->name('settings.provider-commissions.update');
        Route::middleware(['role:company_admin'])->group(function () {
            Route::get('/settings/provider-recruitment', [ProviderRecruitmentSettingsController::class, 'edit'])->name('settings.provider-recruitment');
            Route::get('/settings/provider-recruitment/sample-terms/{locale}', [ProviderRecruitmentSettingsController::class, 'sampleTerms'])->name('settings.provider-recruitment.sample-terms');
            Route::put('/settings/provider-recruitment', [ProviderRecruitmentSettingsController::class, 'update'])->name('settings.provider-recruitment.update');
        });
    });

    Route::middleware(['plan.feature:widgets'])->group(function () {
        Route::get('/settings/widget-embed', [WidgetEmbedController::class, 'show'])->name('settings.widget-embed');
        Route::post('/settings/widget-embed/regenerate-token', [WidgetEmbedController::class, 'regenerateToken'])->name('settings.widget-embed.regenerate-token');
    });

    Route::get('/settings/api-connect', [WorkspaceApiConnectController::class, 'show'])->name('settings.api-connect');
    Route::get('/settings/mcp', [McpSettingsController::class, 'show'])->name('settings.mcp');
    Route::post('/settings/mcp', [McpSettingsController::class, 'store'])->middleware('throttle:10,1')->name('settings.mcp.store');
    Route::delete('/settings/mcp/{mcpToken}', [McpSettingsController::class, 'destroy'])->name('settings.mcp.destroy');
    Route::get('/settings/portal-visibility', [PortalVisibilitySettingsController::class, 'edit'])->name('settings.portal-visibility');
    Route::put('/settings/portal-visibility', [PortalVisibilitySettingsController::class, 'update'])->name('settings.portal-visibility.update');

    Route::post('/settings/api-connect/regenerate-token', [WorkspaceApiConnectController::class, 'regenerateToken'])->name('settings.api-connect.regenerate-token');

    Route::middleware(['plan.feature:workspace_ai_agent'])->group(function () {
        Route::get('/settings/ai-agent', [WorkspaceAiAgentController::class, 'edit'])->name('settings.ai-agent');
        Route::put('/settings/ai-agent', [WorkspaceAiAgentController::class, 'update'])->name('settings.ai-agent.update');
    });

    Route::get('/settings/smtp', [SmtpController::class, 'edit'])->name('settings.smtp');
    Route::put('/settings/smtp', [SmtpController::class, 'update'])->name('settings.smtp.update');

    Route::get('/settings/integrations', ThirdPartyIntegrationsController::class)->name('settings.integrations');

    Route::get('/settings/perfex', [PerfexIntegrationController::class, 'edit'])->name('settings.perfex');
    Route::put('/settings/perfex', [PerfexIntegrationController::class, 'update'])->name('settings.perfex.update');
    Route::post('/settings/perfex/test', [PerfexIntegrationController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('settings.perfex.test');
    Route::post('/settings/perfex/sync', [PerfexIntegrationController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('settings.perfex.sync');
    Route::post('/settings/perfex/disconnect', [PerfexIntegrationController::class, 'disconnect'])->name('settings.perfex.disconnect');

    Route::get('/settings/trello', [TrelloIntegrationController::class, 'edit'])->name('settings.trello');
    Route::put('/settings/trello', [TrelloIntegrationController::class, 'update'])->name('settings.trello.update');
    Route::post('/settings/trello/test', [TrelloIntegrationController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('settings.trello.test');
    Route::post('/settings/trello/sync', [TrelloIntegrationController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('settings.trello.sync');
    Route::post('/settings/trello/disconnect', [TrelloIntegrationController::class, 'disconnect'])->name('settings.trello.disconnect');

    Route::get('/settings/notion', [NotionIntegrationController::class, 'edit'])->name('settings.notion');
    Route::put('/settings/notion', [NotionIntegrationController::class, 'update'])->name('settings.notion.update');
    Route::post('/settings/notion/test', [NotionIntegrationController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('settings.notion.test');
    Route::post('/settings/notion/sync', [NotionIntegrationController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('settings.notion.sync');
    Route::post('/settings/notion/disconnect', [NotionIntegrationController::class, 'disconnect'])->name('settings.notion.disconnect');

    Route::get('/settings/quickbooks', [QuickBooksIntegrationController::class, 'edit'])->name('settings.quickbooks');
    Route::put('/settings/quickbooks', [QuickBooksIntegrationController::class, 'update'])->name('settings.quickbooks.update');
    Route::post('/settings/quickbooks/test', [QuickBooksIntegrationController::class, 'test'])
        ->middleware('throttle:10,1')
        ->name('settings.quickbooks.test');
    Route::post('/settings/quickbooks/sync', [QuickBooksIntegrationController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('settings.quickbooks.sync');
    Route::post('/settings/quickbooks/disconnect', [QuickBooksIntegrationController::class, 'disconnect'])->name('settings.quickbooks.disconnect');

    Route::middleware(['plan.feature:odoo'])->group(function () {
        Route::get('/settings/odoo', [OdooIntegrationController::class, 'edit'])->name('settings.odoo');
        Route::put('/settings/odoo', [OdooIntegrationController::class, 'update'])->name('settings.odoo.update');
        Route::post('/settings/odoo/test', [OdooIntegrationController::class, 'test'])
            ->middleware('throttle:10,1')
            ->name('settings.odoo.test');
        Route::post('/settings/odoo/sync', [OdooIntegrationController::class, 'sync'])
            ->middleware('throttle:6,1')
            ->name('settings.odoo.sync');
        Route::post('/settings/odoo/disconnect', [OdooIntegrationController::class, 'disconnect'])->name('settings.odoo.disconnect');
    });

    Route::middleware(['plan.feature:shopify'])->group(function () {
        Route::get('/settings/shopify', [ShopifyIntegrationController::class, 'edit'])->name('settings.shopify');
        Route::put('/settings/shopify', [ShopifyIntegrationController::class, 'update'])->name('settings.shopify.update');
        Route::post('/settings/shopify/sync', [ShopifyIntegrationController::class, 'sync'])
            ->middleware('throttle:6,1')
            ->name('settings.shopify.sync');
    });

    Route::middleware(['plan.feature:woocommerce'])->group(function () {
        Route::get('/settings/woocommerce', [WooCommerceIntegrationController::class, 'edit'])->name('settings.woocommerce');
        Route::put('/settings/woocommerce', [WooCommerceIntegrationController::class, 'update'])->name('settings.woocommerce.update');
        Route::post('/settings/woocommerce/sync', [WooCommerceIntegrationController::class, 'sync'])
            ->middleware('throttle:6,1')
            ->name('settings.woocommerce.sync');
    });

    Route::middleware(['plan.feature:email_marketing'])->group(function () {
        Route::get('/settings/marketing-integrations', [MarketingIntegrationsController::class, 'edit'])->name('settings.marketing-integrations');
        Route::put('/settings/marketing-integrations', [MarketingIntegrationsController::class, 'update'])->name('settings.marketing-integrations.update');
        Route::post('/settings/marketing-integrations/mailchimp-test', [MarketingIntegrationsController::class, 'testMailchimp'])
            ->name('settings.marketing-integrations.mailchimp-test');
        Route::post('/settings/marketing-integrations/sms-test', [MarketingIntegrationsController::class, 'testTwilio'])
            ->middleware('throttle:5,1')
            ->name('settings.marketing-integrations.sms-test');
    });

    Route::middleware(['plan.feature:calendar'])->group(function () {
        Route::get('/settings/calendar-scheduling', [CalendarSchedulingController::class, 'edit'])->name('settings.calendar-scheduling');
        Route::put('/settings/calendar-scheduling', [CalendarSchedulingController::class, 'update'])->name('settings.calendar-scheduling.update');
    });

    Route::middleware(['plan.feature:projects'])->group(function () {
        Route::get('/settings/google-calendar', [GoogleCalendarController::class, 'edit'])->name('settings.google-calendar');
        Route::get('/settings/google-calendar/redirect', [GoogleCalendarController::class, 'redirect'])->name('settings.google-calendar.redirect');
        Route::get('/settings/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('settings.google-calendar.callback');
        Route::post('/settings/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('settings.google-calendar.disconnect');
        Route::post('/settings/google-calendar/default', [GoogleCalendarController::class, 'setDefault'])->name('settings.google-calendar.default');
        Route::post('/settings/google-calendar/zoom', [GoogleCalendarController::class, 'updateZoom'])->name('settings.google-calendar.zoom');
    });

    Route::get('/settings/invoice-documents', [InvoiceDocumentController::class, 'edit'])->name('settings.invoice-documents');
    Route::put('/settings/invoice-documents', [InvoiceDocumentController::class, 'update'])->name('settings.invoice-documents.update');

    Route::get('/settings/billing-tax', [BillingTaxSettingsController::class, 'edit'])->name('settings.billing-tax');
    Route::put('/settings/billing-tax', [BillingTaxSettingsController::class, 'update'])->name('settings.billing-tax.update');

    Route::get('/settings/payment-gateways', [WorkspacePaymentGatewaySettingsController::class, 'edit'])->name('settings.payment-gateways');
    Route::put('/settings/payment-gateways', [WorkspacePaymentGatewaySettingsController::class, 'update'])->name('settings.payment-gateways.update');

    Route::get('/payments/{payment}/receipt', PaymentReceiptController::class)->name('payments.receipt');

    Route::get('/settings/security', [SecurityController::class, 'edit'])->name('settings.security');
    Route::put('/settings/security', [SecurityController::class, 'update'])->name('settings.security.update');

    Route::get('/settings/team', [TeamController::class, 'index'])->name('settings.team');
    Route::post('/settings/team', [TeamController::class, 'store'])->name('settings.team.store');
    Route::put('/settings/team/{user}', [TeamController::class, 'update'])->name('settings.team.update');
    Route::delete('/settings/team/{user}', [TeamController::class, 'destroy'])->name('settings.team.destroy');

    Route::get('/settings/two-factor', [TwoFactorController::class, 'show'])->name('settings.two-factor');
    Route::post('/settings/two-factor/prepare', [TwoFactorController::class, 'prepare'])->name('settings.two-factor.prepare');
    Route::post('/settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('settings.two-factor.confirm');
    Route::delete('/settings/two-factor', [TwoFactorController::class, 'destroy'])->name('settings.two-factor.destroy');

    Route::get('/settings/dashboard', [DashboardLayoutController::class, 'edit'])->name('settings.dashboard');
    Route::put('/settings/dashboard', [DashboardLayoutController::class, 'update'])->name('settings.dashboard.update');
    Route::get('/settings/navigation', [NavigationSettingsController::class, 'edit'])->name('settings.navigation');
    Route::put('/settings/navigation', [NavigationSettingsController::class, 'update'])->name('settings.navigation.update');
    Route::post('/settings/ui-presets', [UiPresetController::class, 'store'])->name('settings.ui-presets.store');
    Route::put('/settings/ui-presets/{preset}/activate', [UiPresetController::class, 'activate'])->name('settings.ui-presets.activate');
    Route::delete('/settings/ui-presets/{preset}', [UiPresetController::class, 'destroy'])->name('settings.ui-presets.destroy');
});

Route::middleware(['auth', 'verified', 'tenant.match', 'role:client'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', PortalDashboardController::class)->name('dashboard');
        Route::get('calendar', [PortalCalendarController::class, 'index'])->name('calendar');
        Route::get('calendar/preview', [PortalCalendarController::class, 'preview'])->name('calendar.preview');
        Route::get('projects', [PortalProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [PortalProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/kanban', [PortalProjectController::class, 'kanban'])->name('projects.kanban');
        Route::get('projects/{project}/gantt', [PortalProjectController::class, 'gantt'])->name('projects.gantt');
        Route::post('projects/{project}/tasks/{task}/comments', [PortalProjectController::class, 'storeTaskComment'])->name('projects.tasks.comments.store');
        Route::post('projects/{project}/confirm-price', [PortalProjectController::class, 'confirmPrice'])->name('projects.confirm-price');
        Route::get('proposals', [PortalProposalController::class, 'index'])->name('proposals.index');
        Route::get('proposals/{proposal}', [PortalProposalController::class, 'show'])->name('proposals.show');
        Route::get('proposals/{proposal}/pdf', [PortalProposalController::class, 'pdf'])->name('proposals.pdf');
        Route::post('proposals/{proposal}/accept', [PortalProposalController::class, 'accept'])->name('proposals.accept');
        Route::get('contracts', [PortalContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}', [PortalContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/pdf', [PortalContractController::class, 'pdf'])->name('contracts.pdf');
        Route::post('contracts/{contract}/sign', [PortalContractController::class, 'sign'])->name('contracts.sign');
        Route::get('invoices/{invoice}', [PortalInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [PortalInvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/payment-intent', [PortalInvoicePaymentController::class, 'stripeIntent'])->name('invoices.payment-intent');
        Route::post('invoices/{invoice}/paypal-order', [PortalInvoicePaymentController::class, 'paypalOrder'])->name('invoices.paypal-order');
        Route::post('invoices/{invoice}/flouci-payment', [PortalInvoicePaymentController::class, 'flouciPayment'])->name('invoices.flouci-payment');
        Route::get('invoices/{invoice}/paypal/return', [PortalInvoicePaymentController::class, 'paypalReturn'])->name('invoices.paypal.return');
        Route::post('invoices/{invoice}/bank-transfer', [PortalInvoicePaymentController::class, 'bankTransfer'])->name('invoices.bank-transfer');
        Route::get('payments/{payment}/receipt', PaymentReceiptController::class)->name('payments.receipt');
        Route::get('payments', [PortalPaymentController::class, 'index'])->name('payments.index');
        Route::get('quote-requests', [QuoteRequestController::class, 'index'])->name('quote-requests.index');
        Route::get('quote-requests/create', [QuoteRequestController::class, 'create'])->name('quote-requests.create');
        Route::post('quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');
        Route::get('quote-requests/{inquiry}', [QuoteRequestController::class, 'show'])->name('quote-requests.show');
        Route::get('prospects', [PortalProspectController::class, 'index'])->name('prospects.index');
        Route::get('prospects/appointments', [PortalProspectController::class, 'appointments'])->name('prospects.appointments');
        Route::post('prospects/{prospect}/status', [PortalProspectController::class, 'updateStatus'])->name('prospects.status');
        Route::post('prospects/{prospect}/comments', [PortalProspectController::class, 'comment'])->name('prospects.comment');
        Route::post('prospects', [PortalProspectController::class, 'store'])->name('prospects.store');
        Route::get('prospects/{prospect}', [PortalProspectController::class, 'show'])->name('prospects.show');
        Route::put('prospects/{prospect}', [PortalProspectController::class, 'update'])->name('prospects.update');
        Route::post('prospects/{prospect}/appointment', [PortalProspectController::class, 'updateAppointment'])->name('prospects.appointment');
        Route::post('calendar/rdv', [PortalCalendarController::class, 'storeRdv'])->name('calendar.rdv');
        Route::get('calendar/events', [PortalCalendarController::class, 'events'])->name('calendar.events');
        Route::get('campaigns', [PortalCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('bookings', [PortalBookingController::class, 'index'])->name('bookings.index');
        Route::post('bookings/{booking}/status', [PortalBookingController::class, 'updateStatus'])->name('bookings.status');
        Route::post('bookings/{booking}/comments', [PortalBookingController::class, 'comment'])->name('bookings.comment');
        Route::get('assistant', [PortalAssistantController::class, 'index'])->name('assistant.index');
        Route::post('assistant/chat', [PortalAssistantController::class, 'chat'])->name('assistant.chat');
        Route::get('invite-colleague', [ClientAccountRequestController::class, 'create'])->name('client-account-requests.create');
        Route::post('invite-colleague', [ClientAccountRequestController::class, 'store'])->name('client-account-requests.store');
        Route::get('vault', [PortalVaultController::class, 'index'])->name('vault.index');
        Route::post('vault', [PortalVaultController::class, 'store'])->name('vault.store');
        Route::get('vault/{file}/download', [PortalVaultController::class, 'download'])->name('vault.download');
        Route::get('vault/{file}/preview', [PortalVaultController::class, 'preview'])->name('vault.preview');
        Route::delete('vault/{file}', [PortalVaultController::class, 'destroy'])->name('vault.destroy');
        Route::post('invite-colleague/{clientAccountRequest}/add-to-chat', [ClientAccountRequestController::class, 'addToChat'])->name('client-account-requests.add-to-chat');
    });

Route::middleware(['auth', 'verified', 'tenant.match', 'role:business_provider'])
    ->prefix('provider')
    ->name('provider.')
    ->group(function () {
        Route::get('partnership', [ProviderPartnershipController::class, 'show'])->name('partnership.show');
        Route::get('partnership/contract', [ProviderPartnershipController::class, 'contract'])->name('partnership.contract');
        Route::post('partnership/sign', [ProviderPartnershipController::class, 'sign'])->name('partnership.sign');

        Route::get('/', ProviderDashboardController::class)->name('dashboard');
        Route::get('remittance-requests', [ProviderRemittanceRequestController::class, 'index'])->name('remittance-requests.index');
        Route::post('remittance-requests', [ProviderRemittanceRequestController::class, 'store'])->name('remittance-requests.store');

        Route::middleware('provider.partnership.active')->group(function () {
            Route::resource('projects', ProviderProjectController::class);
            Route::get('projects/{project}/proposals/create', [ProviderProposalController::class, 'create'])->name('projects.proposals.create');
            Route::post('projects/{project}/proposals', [ProviderProposalController::class, 'store'])->name('projects.proposals.store');
            Route::get('vault', [ProviderVaultController::class, 'index'])->name('vault.index');
            Route::post('vault', [ProviderVaultController::class, 'store'])->name('vault.store');
            Route::get('vault/{file}/download', [ProviderVaultController::class, 'download'])->name('vault.download');
            Route::get('vault/{file}/preview', [ProviderVaultController::class, 'preview'])->name('vault.preview');
            Route::delete('vault/{file}', [ProviderVaultController::class, 'destroy'])->name('vault.destroy');
        });
    });

require __DIR__.'/auth.php';
