<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Employee documents (Odoo hr.employee.document via the mj_hr_documents addon).
 * Metadata comes from the local cache; file bytes stream from Odoo. HR staff
 * manage everyone's; a plain employee may list/download only their own.
 */
class HrDocumentsController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public const CATEGORIES = [
        'iqama', 'passport', 'license', 'contract', 'bond',
        'warning', 'permission', 'sick_leave', 'certificate', 'other',
    ];

    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = EmployeeDocument::query();

        // Plain employees are scoped to their own documents.
        if (!$user->can('hr.view_all')) {
            $query->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
        } elseif ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', (int) $empId);
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        $docs = $query->orderByDesc('id')->get()->map(fn ($d) => $this->summary($d));

        return response()->json(['data' => $docs]);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer', // odoo employee id
            'category'    => ['required', Rule::in(self::CATEGORIES)],
            'name'        => 'required|string|max:255',
            'file'        => 'required|string', // base64, raw or data-URI
            'filename'    => 'nullable|string|max:255',
            'issue_date'  => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'note'        => 'nullable|string|max:2000',
        ]);

        [$base64, $mimetype] = $this->decodeFile($data['file']);
        if ($base64 === null) {
            return response()->json(['message' => __('The file could not be read.')], 422);
        }

        $payload = [
            'employee_id' => (int) $data['employee_id'],
            'category'    => $data['category'],
            'name'        => $data['name'],
            'document'    => $base64,
            'filename'    => $data['filename'] ?? ($data['name'] . $this->extFor($mimetype)),
            'mimetype'    => $mimetype,
        ];
        foreach (['issue_date', 'expiry_date', 'note'] as $f) {
            if (!empty($data[$f])) $payload[$f] = $data[$f];
        }

        try {
            $odooId = $this->odoo->create('hr.employee.document', $payload);
            $doc = $this->sync->refreshEmployeeDocument($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $doc?->id, 'odoo_id' => $odooId]], 201);
    }

    public function downloadDocument(Request $request, int $id)
    {
        $doc = EmployeeDocument::findOrFail($id);

        $user = $request->user();
        if (!$user->can('hr.view_all') && $doc->odoo_employee_id !== ($user->employeeRecord()?->odoo_id ?? -1)) {
            abort(403);
        }

        try {
            // Ownership already enforced; read with the service account so a plain
            // employee can fetch their own document bytes.
            $rows = $this->odoo->useServiceAccount()->read('hr.employee.document', [$doc->odoo_id], ['document', 'filename', 'mimetype']);
        } catch (\Throwable) {
            abort(404);
        }
        if (empty($rows[0]) || empty($rows[0]['document'])) {
            abort(404);
        }

        $bytes = base64_decode($rows[0]['document'], true);
        if ($bytes === false) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type'        => $rows[0]['mimetype'] ?: ($doc->mimetype ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="' . ($rows[0]['filename'] ?: $doc->filename ?: 'document') . '"',
        ]);
    }

    public function destroyDocument(int $id): JsonResponse
    {
        $doc = EmployeeDocument::findOrFail($id);

        try {
            $this->odoo->unlink('hr.employee.document', [$doc->odoo_id]);
            $doc->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    /** Active employees for the document upload picker. */
    public function employees(): JsonResponse
    {
        return response()->json([
            'data' => Employee::where('active', true)->orderBy('name')
                ->get(['id', 'odoo_id', 'name', 'emp_code']),
        ]);
    }

    /** Split a data-URI (or raw base64) into [clean base64, mimetype]. */
    protected function decodeFile(string $value): array
    {
        $mimetype = 'application/octet-stream';
        if (preg_match('/^data:([a-zA-Z0-9.+\/-]+);base64,(.*)$/s', $value, $m)) {
            $mimetype = $m[1];
            $value = $m[2];
        }
        $value = preg_replace('/\s+/', '', $value);
        if ($value === '' || base64_decode($value, true) === false) {
            return [null, $mimetype];
        }
        return [$value, $mimetype];
    }

    protected function extFor(string $mimetype): string
    {
        return match ($mimetype) {
            'application/pdf' => '.pdf',
            'image/jpeg'      => '.jpg',
            'image/png'       => '.png',
            'image/webp'      => '.webp',
            default           => '',
        };
    }

    protected function summary(EmployeeDocument $d): array
    {
        return [
            'id'            => $d->id,
            'odoo_id'       => $d->odoo_id,
            'employee_name' => $d->employee_name,
            'odoo_employee_id' => $d->odoo_employee_id,
            'name'          => $d->name,
            'category'      => $d->category,
            'filename'      => $d->filename,
            'mimetype'      => $d->mimetype,
            'issue_date'    => $d->issue_date?->toDateString(),
            'expiry_date'   => $d->expiry_date?->toDateString(),
            'note'          => $d->note,
        ];
    }
}
