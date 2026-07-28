<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmTicket;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Support tickets (Odoo mj.crm.ticket via mj_crm_helpdesk).
 * Sales/support (crm.view/write) log, assign, resolve and close tickets with a
 * contract-driven SLA (mj.crm.contract.delivery_sla_hours).
 */
class CrmTicketController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    private const CATEGORIES = ['delivery_issue', 'billing', 'complaint', 'damage', 'inquiry', 'other'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const SOURCES = ['phone', 'email', 'portal', 'whatsapp'];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.view'), 403);
        $q = CrmTicket::query();
        if ($request->boolean('mine')) $q->where('odoo_user_id', $this->odooUid($request));
        if ($request->boolean('open')) $q->whereIn('stage', ['new', 'in_progress', 'waiting']);
        if ($request->boolean('breached')) $q->where('sla_state', 'breached')->whereIn('stage', ['new', 'in_progress', 'waiting']);
        if ($stage = $request->get('stage')) $q->where('stage', $stage);
        if ($priority = $request->get('priority')) $q->where('priority', $priority);
        if ($partnerId = $request->get('partner_id')) $q->where('odoo_partner_id', (int) $partnerId);

        $page = $q->orderByRaw("FIELD(stage,'new','in_progress','waiting','resolved','closed','cancelled')")
            ->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 30), 100))
            ->withQueryString()->through(fn ($t) => $this->summary($t));
        return response()->json($page->toArray());
    }

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.view'), 403);
        $open = CrmTicket::whereIn('stage', ['new', 'in_progress', 'waiting']);
        return response()->json(['data' => [
            'open' => (clone $open)->count(),
            'breached' => (clone $open)->where('sla_state', 'breached')->count(),
            'due_soon' => (clone $open)->where('sla_state', 'due_soon')->count(),
            'mine' => (clone $open)->where('odoo_user_id', $this->odooUid($request))->count(),
            'by_stage' => CrmTicket::selectRaw('stage, count(*) as c')->groupBy('stage')->pluck('c', 'stage'),
        ]]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('crm.view'), 403);
        $t = CrmTicket::findOrFail($id);
        return response()->json(['data' => $this->summary($t) + [
            'description' => $t->description, 'resolution' => $t->resolution,
            'odoo_contract_id' => $t->odoo_contract_id, 'contract_name' => $t->contract_name,
            'source' => $t->source, 'related_ref' => $t->related_ref,
            'date_open' => $t->date_open?->toIso8601String(), 'date_closed' => $t->date_closed?->toIso8601String(),
            'satisfaction' => $t->satisfaction,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'partner_id' => 'nullable|integer',
            'description' => 'nullable|string|max:5000',
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'source' => ['nullable', Rule::in(self::SOURCES)],
            'related_ref' => 'nullable|string|max:255',
        ]);
        $payload = array_filter([
            'subject' => $data['subject'], 'partner_id' => $data['partner_id'] ?? null,
            'description' => $data['description'] ?? null, 'category' => $data['category'] ?? 'delivery_issue',
            'priority' => $data['priority'] ?? 'normal', 'source' => $data['source'] ?? 'phone',
            'related_ref' => $data['related_ref'] ?? null,
        ], fn ($v) => $v !== null);
        try {
            $odooId = $this->odoo->create('mj.crm.ticket', $payload);
            $t = $this->sync->refreshCrmTicket($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $t?->id, 'odoo_id' => $odooId]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $t = CrmTicket::findOrFail($id);
        $data = $request->validate([
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'user_id' => 'nullable|integer',
            'resolution' => 'nullable|string|max:5000',
            'satisfaction' => ['nullable', Rule::in(['bad', 'ok', 'good'])],
            'related_ref' => 'nullable|string|max:255',
        ]);
        $payload = [];
        foreach (['priority', 'category', 'resolution', 'satisfaction', 'related_ref'] as $f) {
            if (array_key_exists($f, $data)) $payload[$f] = $data[$f];
        }
        if (array_key_exists('user_id', $data)) $payload['user_id'] = $data['user_id'] ? (int) $data['user_id'] : false;
        try {
            if ($payload) $this->odoo->write('mj.crm.ticket', [$t->odoo_id], $payload);
            $this->sync->refreshCrmTicket($t->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    public function action(Request $request, int $id, string $action): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $map = ['assign' => 'action_assign', 'wait' => 'action_wait', 'resume' => 'action_resume',
                'resolve' => 'action_resolve', 'close' => 'action_close', 'reopen' => 'action_reopen',
                'cancel' => 'action_cancel', 'reset' => 'action_reset'];
        abort_unless(isset($map[$action]), 404);
        $t = CrmTicket::findOrFail($id);
        try {
            // resolve/close may carry a resolution / satisfaction
            $write = [];
            if (in_array($action, ['resolve', 'close'], true) && $request->filled('resolution')) $write['resolution'] = $request->string('resolution')->toString();
            if ($action === 'close' && $request->filled('satisfaction')) $write['satisfaction'] = $request->string('satisfaction')->toString();
            if ($write) $this->odoo->write('mj.crm.ticket', [$t->odoo_id], $write);
            $this->odoo->executeKw('mj.crm.ticket', $map[$action], [[$t->odoo_id]]);
            $this->sync->refreshCrmTicket($t->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    public function config(): JsonResponse
    {
        $teams = cache()->remember('crm.ticket.teams', now()->addHour(), function () {
            try {
                return array_map(fn ($x) => ['id' => $x['id'], 'name' => $x['name']],
                    $this->odoo->useServiceAccount()->searchRead('crm.team', [], ['id', 'name'], 50, 0, 'name'));
            } catch (\Throwable) {
                return [];
            }
        });
        return response()->json(['data' => [
            'categories' => self::CATEGORIES, 'priorities' => self::PRIORITIES,
            'sources' => self::SOURCES, 'teams' => $teams,
        ]]);
    }

    // --- helpers ---

    private function odooUid(Request $request): int
    {
        // Laravel users are mapped from Odoo res.users; fall back to -1 if unknown.
        return (int) ($request->user()->odoo_user_id ?? $request->user()->odoo_id ?? -1);
    }

    protected function summary(CrmTicket $t): array
    {
        return [
            'id' => $t->id, 'name' => $t->name, 'subject' => $t->subject,
            'partner_name' => $t->partner_name, 'odoo_partner_id' => $t->odoo_partner_id,
            'category' => $t->category, 'priority' => $t->priority, 'stage' => $t->stage,
            'user_name' => $t->user_name, 'team_name' => $t->team_name,
            'sla_state' => $t->sla_state, 'sla_deadline' => $t->sla_deadline?->toIso8601String(),
            'sla_hours' => $t->sla_hours,
        ];
    }
}
