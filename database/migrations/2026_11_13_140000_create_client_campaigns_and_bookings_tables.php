<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 40)->default('other');
            $table->string('status', 40)->default('draft');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'client_id', 'status'], 'ccamp_company_client_status');
            $table->index(['company_id', 'client_id', 'name'], 'ccamp_company_client_name');
        });

        Schema::create('client_bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->string('customer_name');
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('service')->nullable();
            $table->string('status', 40)->default('nouvelle');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'client_id', 'starts_at'], 'cbook_company_client_starts');
            $table->index(['company_id', 'client_id', 'status'], 'cbook_company_client_status');
        });

        Schema::create('client_booking_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('client_booking_id')->constrained('client_bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32)->default('comment');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_booking_id'], 'cbook_comments_company_booking');
        });

        Schema::table('client_prospects', function (Blueprint $table) {
            if (! Schema::hasColumn('client_prospects', 'campaign_id')) {
                $table->foreignUlid('campaign_id')->nullable()->after('campaign')->constrained('client_campaigns')->nullOnDelete();
            }
        });

        $this->backfillCampaigns();
    }

    private function backfillCampaigns(): void
    {
        if (! Schema::hasTable('client_prospects') || ! Schema::hasColumn('client_prospects', 'campaign_id')) {
            return;
        }

        $rows = DB::table('client_prospects')
            ->whereNull('deleted_at')
            ->whereNotNull('campaign')
            ->where('campaign', '!=', '')
            ->whereNull('campaign_id')
            ->select('company_id', 'client_id', 'campaign')
            ->distinct()
            ->get();

        $now = now();
        foreach ($rows as $row) {
            $existing = DB::table('client_campaigns')
                ->where('company_id', $row->company_id)
                ->where('client_id', $row->client_id)
                ->where('name', $row->campaign)
                ->whereNull('deleted_at')
                ->first();

            $id = $existing?->id ?? (string) Str::ulid();
            if (! $existing) {
                DB::table('client_campaigns')->insert([
                    'id' => $id,
                    'company_id' => $row->company_id,
                    'client_id' => $row->client_id,
                    'name' => $row->campaign,
                    'channel' => 'other',
                    'status' => 'completed',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('client_prospects')
                ->where('company_id', $row->company_id)
                ->where('client_id', $row->client_id)
                ->where('campaign', $row->campaign)
                ->whereNull('campaign_id')
                ->update(['campaign_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('client_prospects', function (Blueprint $table) {
            if (Schema::hasColumn('client_prospects', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
        });
        Schema::dropIfExists('client_booking_comments');
        Schema::dropIfExists('client_bookings');
        Schema::dropIfExists('client_campaigns');
    }
};
