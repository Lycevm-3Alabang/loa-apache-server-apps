<?php

namespace App\Console\Commands;

use App\Models\UserGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairI1Violations extends Command
{
    protected $signature = 'auth:repair-i1-violations';

    protected $description = 'Detect I1 invariant violations: users in tenant-scoped groups who lack the tenant pivot (report-only in v1)';

    public function handle(): int
    {
        $this->info('Scanning for I1 invariant violations…');
        $this->line('Invariant: user ∈ tenant-scoped group ⇒ user ∈ tenant pivot');
        $this->newLine();

        $violations = DB::table('user_user_group')
            ->join('user_groups', 'user_groups.id', '=', 'user_user_group.user_group_id')
            ->join('users', 'users.id', '=', 'user_user_group.user_id')
            ->whereNotNull('user_groups.tenant_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('user_tenants')
                    ->whereColumn('user_tenants.user_id', 'user_user_group.user_id')
                    ->whereColumn('user_tenants.tenant_id', 'user_groups.tenant_id');
            })
            ->select([
                'user_user_group.user_id',
                'users.email',
                'user_groups.name as group_name',
                'user_groups.tenant_id',
            ])
            ->get();

        if ($violations->isEmpty()) {
            $this->info('No I1 violations found.');
            return 0;
        }

        $this->error("Found {$violations->count()} I1 violation(s):");
        $this->newLine();

        $this->table(
            ['User ID', 'Email', 'Group', 'Tenant ID'],
            $violations->map(fn ($v) => [
                $v->user_id,
                $v->email,
                $v->group_name,
                $v->tenant_id,
            ])
        );

        $this->newLine();
        $this->warn('These users are in tenant-scoped groups but lack the tenant pivot.');
        $this->warn('In v1 this command is report-only. Run manually or via API to repair.');

        return 1;
    }
}
