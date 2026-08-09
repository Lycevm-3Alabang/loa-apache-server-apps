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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly IdentityService $identity,
        private readonly AuthorizationService $authorization,
        private readonly TenantService $tenants,
        private readonly ActivationService $activation,
    ) {
    }

    public function showForm(): View
    {
        return view('admin.users.import');
    }

    #[OA\Post(
        path: "/api/v1/admin/users/import/preview",
        tags: ["Admin", "Users"],
        summary: "Preview CSV upload for bulk user import (dry-run validation)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: "file",
                            type: "string",
                            format: "binary",
                            description: "CSV file with headers: name,email,tenant_app,user_group"
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "CSV validated successfully, preview returned",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "preview"),
                        new OA\Property(property: "rows", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "summary", type: "object"),
                        new OA\Property(property: "headers", type: "array", items: new OA\Items(type: "string")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Forbidden - requires users.manage permission", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(ref: "#/components/schemas/ValidationErrors")),
        ]
    )]
    public function preview(Request $request)
    {
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

            return response()->view('admin.users.import', ['errors' => $validator->errors()], 422);
        }

        $csvData = $this->parseCsv($request->file('file'));

        if ($csvData['error']) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => $csvData['error'],
                ], 422);
            }

            return response()->view('admin.users.import', ['error' => $csvData['error']], 422);
        }

        $rows = $this->validateRows($csvData['rows']);

        session(['import_rows' => $rows]);
        session(['import_file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : '']);

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'status' => 'preview',
                'rows' => $rows,
                'summary' => $this->buildSummary($rows),
                'headers' => $csvData['headers'],
            ]);
        }

        return view('admin.users.import-preview', [
            'rows' => $rows,
            'headers' => $csvData['headers'],
            'summary' => $this->buildSummary($rows),
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/users/import/process",
        tags: ["Admin", "Users"],
        summary: "Execute bulk user import (upsert users + tenant/group assignments)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: "removed_rows",
                        type: "array",
                        items: new OA\Items(type: "integer"),
                        description: "Array of row indices removed from preview"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Import processed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "applied"),
                        new OA\Property(property: "processed", type: "integer", example: 450),
                        new OA\Property(property: "failed", type: "integer", example: 50),
                        new OA\Property(property: "failed_rows", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Forbidden - requires users.manage permission", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 422, description: "No import data in session", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
        ]
    )]
    public function process(Request $request)
    {
        $rows = session('import_rows', []);

        if (empty($rows)) {
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No import data found. Please upload a CSV file first.',
                ], 422);
            }

            return back()->with('error', 'No import data found. Please upload a CSV file first.');
        }

        $removedIndices = $request->input('removed_rows', []);

        if (is_string($removedIndices)) {
            $removedIndices = json_decode($removedIndices, true) ?? [];
        }

        $removedIndices = is_array($removedIndices) ? array_map('intval', $removedIndices) : [];

        $rowsToImport = collect($rows)
            ->filter(fn ($row, $index) => !in_array($index, $removedIndices, true))
            ->filter(fn ($row) => in_array($row['status'], ['ready', 'ready_existing']))
            ->values();

        $results = $this->executeImport($rowsToImport);

        session()->forget('import_rows');
        session()->forget('import_file_name');

        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $failedRows = collect($results['failed_rows'])
                ->map(fn ($row) => [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'tenant_app' => $row['tenant_app'],
                    'user_group' => $row['user_group'],
                    'remarks' => $row['remarks'],
                ])
                ->values();

            session(['import_failed_rows' => $failedRows]);

            return response()->json([
                'status' => 'applied',
                'processed' => $results['successful'],
                'failed' => $results['failed'],
                'failed_rows' => $failedRows,
            ]);
        }

        return view('admin.users.import-results', [
            'summary' => $results,
            'results' => $rowsToImport,
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/users/import/failed",
        tags: ["Admin", "Users"],
        summary: "Download CSV of failed import rows",
        responses: [
            new OA\Response(
                response: 200,
                description: "CSV file of failed rows",
                content: new OA\MediaType(
                    mediaType: "text/csv"
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
            new OA\Response(response: 403, description: "Forbidden - requires users.manage permission", content: new OA\JsonContent(ref: "#/components/schemas/Error")),
        ]
    )]
    public function downloadFailed(Request $request): StreamedResponse
    {
        $failedRows = session('import_failed_rows', []);

        $filename = 'user-import-failed-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($failedRows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'email', 'tenant_app', 'user_group', 'REMARKS']);

            foreach ($failedRows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['tenant_app'],
                    $row['user_group'],
                    $row['remarks'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function parseCsv($file): array
    {
        $content = file_get_contents($file->getRealPath());
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');
        $lines = array_values($lines);

        if (empty($lines)) {
            return ['error' => 'File is empty', 'rows' => [], 'headers' => []];
        }

        $headers = array_map('trim', explode(',', $lines[0]));
        $expected = ['name', 'email', 'tenant_app', 'user_group'];

        if ($headers !== $expected) {
            $missing = array_diff($expected, $headers);
            $extra = array_diff($headers, $expected);

            return [
                'error' => 'Invalid headers. Expected: name,email,tenant_app,user_group. ' .
                           (!empty($missing) ? 'Missing: ' . implode(',', $missing) . '. ' : '') .
                           (!empty($extra) ? 'Extra: ' . implode(',', $extra) . '. ' : '') .
                           ($headers !== $expected && empty($missing) && empty($extra) ? 'Wrong order. ' : '') .
                           'Headers must be exactly: name,email,tenant_app,user_group',
                'rows' => [],
                'headers' => $headers,
            ];
        }

        $rows = [];
        $rowNumber = 1;

        for ($i = 1; $i < count($lines); $i++) {
            $fields = array_map('trim', explode(',', $lines[$i]));

            if (count($fields) !== 4) {
                $rows[] = [
                    'row_number' => $rowNumber,
                    'name' => $fields[0] ?? '',
                    'email' => $fields[1] ?? '',
                    'tenant_app' => $fields[2] ?? '',
                    'user_group' => $fields[3] ?? '',
                    'status' => 'error',
                    'remarks' => 'Invalid column count',
                ];
                $rowNumber++;
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'name' => $fields[0],
                'email' => $fields[1],
                'tenant_app' => $fields[2],
                'user_group' => $fields[3],
                'status' => 'pending',
                'remarks' => '',
            ];
            $rowNumber++;
        }

        return ['error' => null, 'rows' => $rows, 'headers' => $headers];
    }

    private function validateRows(array $rows): array
    {
        $seenEmails = [];
        $emailCounts = [];

        $existingEmails = User::whereIn('email', collect($rows)
            ->pluck('email')
            ->filter()
            ->toArray())
            ->pluck('email')
            ->toArray();

        $existingEmailSet = array_flip($existingEmails);

        foreach ($rows as $index => $row) {
            $remarks = [];
            $email = strtolower($row['email'] ?? '');

            if (empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $remarks[] = 'email is invalid';
            }

            if (empty($row['name'])) {
                $remarks[] = 'name is required';
            }

            if (empty($row['tenant_app'])) {
                $remarks[] = 'tenant_app is required';
            }

            if (empty($row['user_group'])) {
                $remarks[] = 'user_group is required';
            }

            if (!empty($email)) {
                if (!isset($emailCounts[$email])) {
                    $emailCounts[$email] = 0;
                }
                $emailCounts[$email]++;
            }

            $rows[$index]['remarks'] = implode('; ', $remarks);
            $rows[$index]['_email'] = $email;
        }

        $tenantCache = [];
        $groupCache = [];

        foreach ($rows as $index => $row) {
            $remarks = $row['remarks'];
            $email = $row['_email'] ?? '';
            $extraRemarks = [];

            if (!empty($row['tenant_app'])) {
                $tenantSlug = $row['tenant_app'];
                if (!isset($tenantCache[$tenantSlug])) {
                    $tenantCache[$tenantSlug] = Tenant::where('slug', $tenantSlug)->first();
                }
                if (!$tenantCache[$tenantSlug]) {
                    $extraRemarks[] = 'tenant_app does not exist';
                }
            }

            if (!empty($row['tenant_app']) && !empty($row['user_group'])) {
                $tenantSlug = $row['tenant_app'];
                $groupName = $row['user_group'];
                $cacheKey = $tenantSlug . '|' . $groupName;

                if (!isset($groupCache[$cacheKey])) {
                    $tenant = $tenantCache[$tenantSlug] ?? null;
                    if ($tenant) {
                        $groupCache[$cacheKey] = UserGroup::where('name', $groupName)
                            ->where('tenant_id', $tenant->id)
                            ->first();
                    } else {
                        $groupCache[$cacheKey] = false;
                    }
                }

                if (!$groupCache[$cacheKey]) {
                    $extraRemarks[] = 'user_group does not exist';
                }
            }

            if (isset($emailCounts[$email]) && $emailCounts[$email] > 1) {
                if (isset($seenEmails[$email])) {
                    $extraRemarks[] = 'Duplicate email found in uploaded file';
                }
                $seenEmails[$email] = true;
            }

            if (!empty($extraRemarks)) {
                $rows[$index]['remarks'] = $remarks . (empty($remarks) ? '' : '; ') . implode('; ', $extraRemarks);
            }

            if (isset($existingEmailSet[$email]) && empty($extraRemarks)) {
                $rows[$index]['status'] = 'ready_existing';
                $rows[$index]['remarks'] = 'Existing user - will update tenant and group assignment';
            } elseif (empty($extraRemarks) && empty($remarks)) {
                $rows[$index]['status'] = 'ready';
            } else {
                $rows[$index]['status'] = 'error';
            }

            unset($rows[$index]['_email']);
        }

        return $rows;
    }

    private function buildSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'ready' => 0,
            'existing_user' => 0,
            'errors' => 0,
            'removed' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['status'] === 'ready') {
                $summary['ready']++;
            } elseif ($row['status'] === 'ready_existing') {
                $summary['existing_user']++;
            } elseif ($row['status'] === 'error') {
                $summary['errors']++;
            }
        }

        return $summary;
    }

    private function executeImport(Collection $rows): array
    {
        $results = ['successful' => 0, 'failed' => 0, 'failed_rows' => []];

        $batches = $rows->chunk(self::BATCH_SIZE);

        foreach ($batches as $batch) {
            DB::transaction(function () use ($batch, &$results) {
                foreach ($batch as $row) {
                    try {
                        $this->processRow($row);
                        $results['successful']++;
                    } catch (\Throwable $e) {
                        $results['failed']++;
                        $results['failed_rows'][] = [
                            'name' => $row['name'],
                            'email' => $row['email'],
                            'tenant_app' => $row['tenant_app'],
                            'user_group' => $row['user_group'],
                            'remarks' => $e->getMessage(),
                        ];
                    }
                }
            });
        }

        session(['import_failed_rows' => collect($results['failed_rows'])->map(fn ($row) => [
            'name' => $row['name'],
            'email' => $row['email'],
            'tenant_app' => $row['tenant_app'],
            'user_group' => $row['user_group'],
            'remarks' => $row['remarks'],
        ])->values()->toArray()]);

        return $results;
    }

    private function processRow(array $row): void
    {
        $tenant = Tenant::where('slug', $row['tenant_app'])->firstOrFail();
        $group = UserGroup::where('name', $row['user_group'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $existingUser = User::where('email', $row['email'])->first();

        if (!$existingUser) {
            $user = $this->identity->register($row['email'], '', $row['name']);
            $user->update(['status' => 'pending']);

            $rawToken = $this->activation->createActivation($user);

            Mail::send('emails.activate-account', [
                'user' => $user,
                'token' => $rawToken,
            ], function ($m) use ($user) {
                $m->to($user->email)
                    ->subject('Activate your LOA Platform account');
            });
        } else {
            $user = $existingUser;
        }

        $this->tenants->addUserToTenant($user->id, $tenant->id);
        $this->authorization->addToGroup($user->id, $group->id);
    }
}
