<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ActivationService;
use App\Services\AuthorizationService;
use App\Services\IdentityService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantMemberImportController extends UserImportController
{
    protected const MAX_ROWS = 5000;
    protected const FIELD_MAX_LENGTH = 255;

    private ?Tenant $tenant = null;

    private array $usersByEmail = [];
    private array $memberUserIds = [];
    private array $currentGroupsByUser = [];
    private ?array $groupsByName = null;

    public function __construct(
        private readonly IdentityService $identity,
        private readonly AuthorizationService $authorization,
        private readonly TenantService $tenants,
        private readonly ActivationService $activation,
    ) {
    }

    public function discard(Request $request, ?Tenant $tenant = null)
    {
        session()->forget([
            'tenant_member_import_rows',
            'tenant_member_import_file',
            'tenant_member_import_failed_rows',
        ]);

        if ($tenant) {
            return redirect()
                ->route('admin.tenants.show', $tenant)
                ->with('status', 'Pending member import discarded.');
        }

        return redirect()->route('admin.tenants')->with('status', 'Pending member import discarded.');
    }

    public function showForm(?Tenant $tenant = null): View
    {
        $this->tenant = $tenant;

        return view('admin.tenants.member-import', [
            'tenant' => $tenant,
            'groups' => $tenant->userGroups()->orderBy('name')->get(),
        ]);
    }

    public function preview(Request $request, ?Tenant $tenant = null)
    {
        $this->tenant = $tenant;

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator);
        }

        $csvData = $this->parseCsv($request->file('file'));

        if ($csvData['error']) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => $csvData['error'],
                ], 422);
            }

            return response()->view('admin.tenants.member-import', [
                'tenant' => $tenant,
                'groups' => $tenant->userGroups()->orderBy('name')->get(),
                'error' => $csvData['error'],
            ], 422);
        }

        if (count($csvData['rows']) > self::MAX_ROWS) {
            $message = 'File exceeds the maximum of ' . self::MAX_ROWS . ' data rows. Split it into smaller files.';

            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 422);
            }

            return response()->view('admin.tenants.member-import', [
                'tenant' => $tenant,
                'groups' => $tenant->userGroups()->orderBy('name')->get(),
                'error' => $message,
            ], 422);
        }

        $rows = $this->validateRows($csvData['rows']);

        session(['tenant_member_import_rows' => $rows]);        session(['tenant_member_import_file' => $request->file('file')?->getClientOriginalName() ?? '']);

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'status' => 'preview',
                'rows' => $rows,
                'summary' => $this->buildSummary($rows),
                'headers' => $csvData['headers'],
            ]);
        }

        return view('admin.tenants.member-import-preview', [
            'tenant' => $tenant,
            'rows' => $rows,
            'headers' => $csvData['headers'],
            'summary' => $this->buildSummary($rows),
        ]);
    }

    public function process(Request $request, ?Tenant $tenant = null)
    {
        $this->tenant = $tenant;

        @set_time_limit(0);

        $rows = session('tenant_member_import_rows', []);

        if (empty($rows)) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No import data found. Please upload a CSV file first.',
                ], 422);
            }

            return back()->with('error', 'No import data found. Please upload a CSV file first.');
        }

        if ($tenant->status !== 'active') {
            $message = 'This tenant is not active. Import is disabled.';

            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        $removedIndices = $request->input('removed_rows', []);

        if (is_string($removedIndices)) {
            $removedIndices = json_decode($removedIndices, true) ?? [];
        }

        $removedIndices = is_array($removedIndices) ? array_map('intval', $removedIndices) : [];

        $filtered = collect($rows)
            ->filter(fn ($row, $index) => !in_array($index, $removedIndices, true))
            ->filter(fn ($row) => in_array($row['status'], ['ready', 'ready_existing']))
            ->values();

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $total = $filtered->count();
            $cursor = min(max((int) $request->input('cursor', 0), 0), $total);
            $batch = $filtered->slice($cursor, static::BATCH_SIZE)->values();

            $results = $this->executeBatch($batch);

            $processedCursor = $cursor + $batch->count();
            $done = $total === 0 || $processedCursor >= $total;

            if (!empty($results['failed_rows'])) {
                $existingFailed = collect(session('tenant_member_import_failed_rows', []));
                $incoming = collect($results['failed_rows'])->map(fn ($row) => [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'user_group' => $row['user_group'],
                    'remarks' => $row['remarks'],
                ]);
                session(['tenant_member_import_failed_rows' => $existingFailed->merge($incoming)->values()->all()]);
            }

            if ($done) {
                session()->forget('tenant_member_import_rows');
                session()->forget('tenant_member_import_file');
            }

            return response()->json([
                'status' => 'applied',
                'done' => $done,
                'next_cursor' => $processedCursor,
                'total' => $total,
                'processed' => $results['successful'],
                'failed' => $results['failed'],
                'failed_rows' => collect($results['failed_rows'])->map(fn ($row) => [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'user_group' => $row['user_group'],
                    'remarks' => $row['remarks'],
                ])->values(),
            ]);
        }

        $results = $this->executeImport($filtered);

        session()->forget('tenant_member_import_rows');
        session()->forget('tenant_member_import_file');

        return view('admin.tenants.member-import-results', [
            'tenant' => $tenant,
            'summary' => $results,
            'results' => $filtered,
        ]);
    }

    public function downloadFailed(Request $request, ?Tenant $tenant = null): StreamedResponse
    {
        $this->tenant = $tenant;

        $failedRows = session('tenant_member_import_failed_rows', []);

        $filename = 'tenant-' . ($this->tenant?->slug ?? 'unknown') . '-members-import-failed-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($failedRows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'email', 'user_group', 'REMARKS']);

            foreach ($failedRows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['user_group'],
                    $row['remarks'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function parseCsv($file): array
    {
        $content = file_get_contents($file->getRealPath());

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = array_filter(explode("\n", $content), fn ($line) => trim($line) !== '');
        $lines = array_values($lines);

        if (empty($lines)) {
            return ['error' => 'File is empty', 'rows' => [], 'headers' => []];
        }

        $headers = array_map(
            fn ($h) => strtolower(str_replace(['-', ' '], '_', trim((string) $h))),
            str_getcsv($lines[0])
        );

        $expected = ['name', 'email', 'user_group'];

        $normalizedHeaders = array_map(
            fn ($h) => str_replace('-', '_', $h),
            $headers,
        );

        $canonicalHeaders = $normalizedHeaders;

        if (in_array('groups', $canonicalHeaders, true) && !in_array('user_group', $canonicalHeaders, true)) {
            $canonicalHeaders = array_map(
                fn ($h) => $h === 'groups' ? 'user_group' : $h,
                $canonicalHeaders,
            );
        }

        if ($canonicalHeaders !== $expected) {
            $missing = array_diff($expected, $canonicalHeaders);
            $extra = array_diff($canonicalHeaders, $expected);

            return [
                'error' => 'Invalid headers. Expected: name,email,user_group (or name,email,groups). ' .
                           (!empty($missing) ? 'Missing: ' . implode(',', $missing) . '. ' : '') .
                           (!empty($extra) ? 'Extra: ' . implode(',', $extra) . '. ' : '') .
                           ($canonicalHeaders !== $expected && empty($missing) && empty($extra) ? 'Wrong order. ' : '') .
                           'Headers must be exactly: name,email,user_group',
                'rows' => [],
                'headers' => $headers,
            ];
        }

        $rows = [];
        $rowNumber = 1;

        for ($i = 1; $i < count($lines); $i++) {
            $fields = array_map(fn ($f) => trim((string) $f), str_getcsv($lines[$i]));

            while (count($fields) > 3 && ($fields[count($fields) - 1] ?? '') === '') {
                array_pop($fields);
            }

            $isBlankRow = count(array_filter($fields, fn ($f) => $f !== '')) === 0;

            if ($isBlankRow || count($fields) !== 3) {
                if (!$isBlankRow) {
                    $rows[] = [
                        'row_number' => $rowNumber,
                        'name' => $fields[0] ?? '',
                        'email' => $fields[1] ?? '',
                        'tenant_app' => $this->tenant->slug,
                        'user_group' => $this->normalizeGroupField($fields[2] ?? ''),
                        'status' => 'error',
                        'remarks' => count($fields) < 3
                            ? 'Invalid column count - missing field(s)'
                            : 'Invalid column count - too many non-empty fields',
                    ];
                    $rowNumber++;
                }
                continue;
            }

            $groupsRaw = $this->normalizeGroupField($fields[2]);
            $groupNames = $this->parseGroupList($groupsRaw);

            $rows[] = [
                'row_number' => $rowNumber,
                'name' => $fields[0],
                'email' => $fields[1],
                'tenant_app' => $this->tenant->slug,
                'user_group' => $groupsRaw,
                'group_names' => $groupNames,
                'status' => 'pending',
                'remarks' => '',
            ];
            $rowNumber++;
        }

        return ['error' => null, 'rows' => $rows, 'headers' => $expected];
    }

    private function normalizeGroupField(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function parseGroupList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    protected function validateRows(array $rows): array
    {
        $rows = parent::validateRows($rows);

        $tenantGroups = $this->tenant->userGroups()->pluck('name')->map(fn ($n) => strtolower($n))->all();

        foreach ($rows as $index => $row) {
            $tooLong = [];

            if (mb_strlen($row['name']) > self::FIELD_MAX_LENGTH) {
                $tooLong[] = 'name is too long (max ' . self::FIELD_MAX_LENGTH . ')';
            }

            if (mb_strlen($row['email']) > self::FIELD_MAX_LENGTH) {
                $tooLong[] = 'email is too long (max ' . self::FIELD_MAX_LENGTH . ')';
            }

            if (mb_strlen($row['user_group']) > self::FIELD_MAX_LENGTH) {
                $tooLong[] = 'user_group is too long (max ' . self::FIELD_MAX_LENGTH . ')';
            }

            $invalidGroups = [];
            $groupNames = $row['group_names'] ?? ($row['user_group'] ? [$row['user_group']] : []);

            foreach ($groupNames as $gName) {
                if (!in_array(strtolower($gName), $tenantGroups, true)) {
                    $invalidGroups[] = $gName;
                }
            }

            if (!empty($invalidGroups)) {
                $tooLong[] = 'group not found in tenant: ' . implode(', ', $invalidGroups);
            }

            if (!empty($tooLong)) {
                $rows[$index]['remarks'] = trim($row['remarks'] . (!empty($row['remarks']) ? '; ' : '') . implode('; ', $tooLong), '; ');
                $rows[$index]['status'] = 'error';
            }
        }

        return $rows;
    }

    protected function executeImport(Collection $rows): array
    {
        $results = ['successful' => 0, 'failed' => 0, 'failed_rows' => []];

        foreach ($rows->chunk(static::BATCH_SIZE) as $batch) {
            $batchResults = $this->executeBatch($batch);

            $results['successful'] += $batchResults['successful'];
            $results['failed'] += $batchResults['failed'];
            $results['failed_rows'] = array_merge($results['failed_rows'], $batchResults['failed_rows']);
        }

        return $results;
    }

    protected function executeBatch(Collection $rows): array
    {
        $results = ['successful' => 0, 'failed' => 0, 'failed_rows' => []];

        if ($rows->isEmpty()) {
            return $results;
        }

        $this->primeLookups($rows);

        DB::transaction(function () use ($rows, &$results) {
            foreach ($rows as $row) {
                try {
                    $this->processRow($row);
                    $results['successful']++;
                } catch (\Throwable $e) {
                    $results['failed']++;
                    $results['failed_rows'][] = [
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'user_group' => $row['user_group'],
                        'remarks' => $e->getMessage(),
                    ];
                }
            }
        });

        return $results;
    }

    private function primeLookups(Collection $rows): void
    {
        $emails = collect($rows)
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->usersByEmail = User::whereIn('email', $emails)
            ->get()
            ->keyBy(fn (User $u) => strtolower($u->email))
            ->all();

        $userIds = array_map(fn (User $u) => $u->id, $this->usersByEmail);

        $this->memberUserIds = DB::table('user_tenants')
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->flip()
            ->all();

        $this->currentGroupsByUser = DB::table('user_user_group as uug')
            ->join('user_groups as ug', 'ug.id', '=', 'uug.user_group_id')
            ->where('ug.tenant_id', $this->tenant->id)
            ->whereIn('uug.user_id', $userIds)
            ->get(['uug.user_id', 'uug.user_group_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('user_group_id')->map(fn ($v) => (int) $v)->values()->all())
            ->all();

        $this->groupsByName = $this->tenant->userGroups()
            ->orderBy('name')
            ->pluck('id', 'name')
            ->all();
    }

    protected function processRow(array $row): void
    {
        $emailKey = strtolower(trim($row['email']));
        $groupNames = $row['group_names'] ?? ($row['user_group'] ? [$row['user_group']] : []);

        $groupIds = [];
        foreach ($groupNames as $gName) {
            $gid = $this->groupsByName[$gName] ?? null;
            if ($gid === null) {
                throw new \Exception("group '{$gName}' does not exist in this tenant");
            }
            $groupIds[] = (int) $gid;
        }

        $user = $this->usersByEmail[$emailKey] ?? null;
        $isNew = false;

        if (!$user) {
            $user = $this->identity->register($row['email'], '', $row['name']);
            $user->update(['status' => 'pending']);
            $isNew = true;

            $rawToken = $this->activation->createActivation($user);

            Mail::queue('emails.activate-account', [
                'user' => $user,
                'token' => $rawToken,
            ], function ($m) use ($user) {
                $m->to($user->email)
                    ->subject('Activate your LOA Platform account');
            });

            $this->usersByEmail[$emailKey] = $user;
        } elseif ($user->status === 'disabled') {
            throw new \Exception('Account is disabled');
        }

        $user->tenants()->syncWithoutDetaching([$this->tenant->id]);
        $this->memberUserIds[$user->id] = true;

        if (!empty($groupIds)) {
            $currentGroupIds = collect($this->currentGroupsByUser[$user->id] ?? []);
            $newGroupIds = collect($groupIds)->reject(fn ($id) => $currentGroupIds->contains($id))->values()->all();

            if (!empty($newGroupIds)) {
                $user->userGroups()->syncWithoutDetaching($newGroupIds);
                $this->currentGroupsByUser[$user->id] = array_unique(array_merge($currentGroupIds->all(), $newGroupIds));
            }
        }
    }
}
