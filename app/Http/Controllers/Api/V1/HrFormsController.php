<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceEnd;
use App\Models\SickLeave;
use App\Models\Warning;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * HR Forms: warnings, sick-leave forms and end-of-service clearance
 * (Odoo models from mj_hr_forms). PDFs render in Odoo (QWeb/wkhtmltopdf) and
 * stream through here. Reads from the local cache; writes go to Odoo first.
 */
class HrFormsController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    /** type slug → [odoo model, eloquent model, sync method, state actions]. */
    private function registry(): array
    {
        return [
            'warning' => [
                'odoo'    => 'hr.warning',
                'model'   => Warning::class,
                'sync'    => 'syncWarnings',
                'actions' => ['issue' => 'action_issue', 'acknowledge' => 'action_acknowledge', 'draft' => 'action_reset_draft'],
            ],
            'sick-leave' => [
                'odoo'    => 'hr.sick.leave',
                'model'   => SickLeave::class,
                'sync'    => 'syncSickLeaves',
                'actions' => ['confirm' => 'action_confirm', 'draft' => 'action_reset_draft'],
            ],
            'service-end' => [
                'odoo'    => 'hr.service.end',
                'model'   => ServiceEnd::class,
                'sync'    => 'syncServiceEnds',
                'actions' => ['clear' => 'action_clear', 'draft' => 'action_reset_draft'],
            ],
        ];
    }

    private function cfg(string $type): array
    {
        $reg = $this->registry();
        abort_unless(isset($reg[$type]), 404);
        return $reg[$type];
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $cfg = $this->cfg($type);
        $user = $request->user();

        $query = $cfg['model']::query();
        if (!$user->can('hr.view_all')) {
            $query->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
        } elseif ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', (int) $empId);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $cfg = $this->cfg($type);
        $payload = $this->validatePayload($request, $type);
        // Drop null optionals (Odoo keeps its defaults) and cast the many2one.
        $payload = array_filter($payload, fn ($v) => $v !== null && $v !== '');
        $payload['employee_id'] = (int) $payload['employee_id'];

        try {
            $odooId = $this->odoo->create($cfg['odoo'], $payload);
            $this->sync->{$cfg['sync']}();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['odoo_id' => $odooId]], 201);
    }

    public function action(Request $request, string $type, int $id, string $action): JsonResponse
    {
        $cfg = $this->cfg($type);
        abort_unless(isset($cfg['actions'][$action]), 404);
        $rec = $cfg['model']::findOrFail($id);

        try {
            $this->odoo->executeKw($cfg['odoo'], $cfg['actions'][$action], [[$rec->odoo_id]]);
            $this->sync->{$cfg['sync']}();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $rec->id]]);
    }

    public function pdf(Request $request, string $type, int $id)
    {
        $cfg = $this->cfg($type);
        $rec = $cfg['model']::findOrFail($id);

        // Plain employees may only print their own forms.
        $user = $request->user();
        if (!$user->can('hr.view_all') && $rec->odoo_employee_id !== ($user->employeeRecord()?->odoo_id ?? -1)) {
            abort(403);
        }

        try {
            // Ownership already enforced; render with the service account so an
            // employee's own Odoo user need not hold read rights on the model.
            $b64 = $this->odoo->useServiceAccount()->executeKw($cfg['odoo'], 'render_pdf', [[$rec->odoo_id]]);
        } catch (\Throwable) {
            abort(404);
        }
        $bytes = is_string($b64) ? base64_decode($b64, true) : false;
        if ($bytes === false) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($rec->name ?: $type) . '.pdf"',
        ]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $cfg = $this->cfg($type);
        $rec = $cfg['model']::findOrFail($id);

        try {
            $this->odoo->unlink($cfg['odoo'], [$rec->odoo_id]);
            $rec->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    /** Per-type validation + Odoo payload. */
    private function validatePayload(Request $request, string $type): array
    {
        return match ($type) {
            'warning' => $request->validate([
                'employee_id'  => 'required|integer',
                'warning_type' => ['required', Rule::in(['first', 'second', 'final'])],
                'date'         => 'nullable|date',
                'subject'      => 'required|string|max:255',
                'description'  => 'nullable|string|max:5000',
            ]),
            'sick-leave' => $request->validate([
                'employee_id' => 'required|integer',
                'date_from'   => 'required|date',
                'date_to'     => 'required|date|after_or_equal:date_from',
                'diagnosis'   => 'nullable|string|max:255',
                'doctor_name' => 'nullable|string|max:255',
                'facility'    => 'nullable|string|max:255',
                'note'        => 'nullable|string|max:5000',
            ]),
            'service-end' => $this->cleanBools($request->validate([
                'employee_id'       => 'required|integer',
                'reason'            => ['required', Rule::in(['resignation', 'dismissal', 'probation_end', 'contract_end', 'mutual'])],
                'last_working_day'  => 'nullable|date',
                'notice_served'     => 'nullable|boolean',
                'custody_returned'  => 'nullable|boolean',
                'custody_note'      => 'nullable|string|max:5000',
                'settlement_amount' => 'nullable|numeric|min:0',
                'clearance_note'    => 'nullable|string|max:5000',
            ])),
            default => abort(404),
        };
    }

    /** Drop null optionals so Odoo keeps its defaults; cast employee_id to int. */
    private function cleanBools(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null);
    }
}
