<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes a company (workspace) and everything that belongs to it.
 *
 * Almost every tenant table has company_id -> companies ON DELETE CASCADE
 * (clients, projects, tasks, files, proposals, invoices, payments, subscriptions,
 * HR, commerce, marketing, chat, ...), so force-deleting the company row removes
 * that data. users.company_id and marketplace_orders.company_id are SET NULL,
 * so those are deleted explicitly first. Stored files are removed after commit.
 */
class CompanyPurgeService
{
    public function __construct(private UserDeletionService $users) {}

    /**
     * @return array{users:int, projects:int, clients:int}
     */
    public function purge(Company $company): array
    {
        $companyId = $company->id;
        $files = $this->collectFiles($companyId);

        $stats = DB::transaction(function () use ($company, $companyId): array {
            $userRows = User::query()->where('company_id', $companyId)->get(['id', 'email']);
            $stats = [
                'users' => $userRows->count(),
                'projects' => DB::table('projects')->where('company_id', $companyId)->count(),
                'clients' => DB::table('clients')->where('company_id', $companyId)->count(),
            ];

            $this->users->deleteMany($userRows->pluck('id')->all(), $userRows->pluck('email')->all());

            if (Schema::hasTable('marketplace_orders')) {
                DB::table('marketplace_orders')->where('company_id', $companyId)->delete(); // items cascade
            }

            // Safety net for any company_id table without an FK cascade.
            $db = DB::getDatabaseName();
            $tables = collect(DB::select(
                'select c.table_name as t from information_schema.columns c
                 where c.table_schema = ? and c.column_name = ? and c.table_name not in (?, ?)
                 and not exists (
                   select 1 from information_schema.key_column_usage k
                   where k.table_schema = c.table_schema and k.table_name = c.table_name
                   and k.column_name = ? and k.referenced_table_name = ?)',
                [$db, 'company_id', 'users', 'marketplace_orders', 'company_id', 'companies']
            ))->pluck('t');
            foreach ($tables as $table) {
                DB::table($table)->where('company_id', $companyId)->delete();
            }

            $company->forceDelete(); // FK cascades remove the remaining tenant data

            return $stats;
        });

        $this->deleteFiles($companyId, $files);

        return $stats;
    }

    /**
     * @return array{paths: list<array{0:string,1:string}>, dirs: list<array{0:string,1:string}>}
     */
    private function collectFiles(string|int $companyId): array
    {
        $paths = [];
        $dirs = [
            ['public', 'tenants/'.$companyId],
            ['public', 'real-estate/'.$companyId],
            ['local', 'payment-receipts/'.$companyId],
            ['tenant', (string) $companyId],
        ];

        foreach (['project_files', 'project_task_files'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->where('company_id', $companyId)->get(['disk', 'path', 'thumb_path']) as $row) {
                $disk = $row->disk ?: 'public';
                foreach ([$row->path, $row->thumb_path] as $p) {
                    if ($p) {
                        $paths[] = [$disk, $p];
                    }
                }
            }
        }

        foreach (DB::table('projects')->where('company_id', $companyId)->pluck('id') as $projectId) {
            $dirs[] = ['public', 'project-files/'.$projectId];
            $dirs[] = ['local', 'vault/project-files/'.$projectId];
            $dirs[] = ['public', 'project-task-files/'.$projectId];
        }

        return ['paths' => $paths, 'dirs' => $dirs];
    }

    private function deleteFiles(string|int $companyId, array $files): void
    {
        if ((string) $companyId === '') {
            return;
        }
        foreach ($files['paths'] as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable) {
                // ignore missing disks/files
            }
        }
        foreach ($files['dirs'] as [$disk, $dir]) {
            if (trim($dir, '/') === '' || str_ends_with(trim($dir, '/'), '/')) {
                continue;
            }
            try {
                Storage::disk($disk)->deleteDirectory($dir);
            } catch (\Throwable) {
                // ignore
            }
        }
    }
}
