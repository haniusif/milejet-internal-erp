<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\OdooService;
use Illuminate\Console\Command;
use Throwable;

class ProvisionEmployeeUsers extends Command
{
    protected $signature = 'odoo:provision-users
        {--apply : Actually create/link users in Odoo (omit for a dry-run preview)}
        {--password=12345678 : Password to set on newly created accounts}
        {--domain=milejet.space : Domain used to build a login from emp_code when an employee has no work_email}
        {--limit=0 : Max employees to process (0 = all)}';

    protected $description = 'Create internal res.users (attendance self-service) for employees with no linked Odoo user and set hr.employee.user_id, so they can log in to the mobile app. Uses work_email when present, otherwise builds {emp_code}@{domain}.';

    public function handle(OdooService $odoo): int
    {
        $apply    = (bool) $this->option('apply');
        $password = (string) $this->option('password');
        $domain   = strtolower(trim((string) $this->option('domain')));
        $limit    = (int) $this->option('limit');
        $tag      = $apply ? '[APPLY]' : '[DRY-RUN]';

        // Internal User group — makes the account an internal user; "My Attendances",
        // "My Time Off" and "My Payslips" self-service follow from this in Odoo.
        $groupUserId = $this->resolveXmlId($odoo, 'base', 'group_user');
        if (!$groupUserId) {
            $this->error('Could not resolve base.group_user in Odoo. Aborting.');
            return self::FAILURE;
        }

        // Source of truth for identity: local cache (has emp_code). Linked to Odoo via odoo_id.
        $query = Employee::whereNotNull('odoo_id')->orderBy('name');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $employees = $query->get();

        if ($employees->isEmpty()) {
            $this->info('No local employees with an odoo_id. Run a sync first.');
            return self::SUCCESS;
        }

        // Which Odoo employees already have a linked user? Fetch in one call.
        $odooIds = $employees->pluck('odoo_id')->all();
        $odooRows = $odoo->searchRead('hr.employee', [['id', 'in', $odooIds]], ['id', 'user_id', 'work_email']);
        $odooById = [];
        foreach ($odooRows as $r) {
            $odooById[(int) $r['id']] = $r;
        }

        $this->info("$tag Internal User group id: $groupUserId   login domain: $domain");

        $created = 0;
        $linked  = 0;
        $skipped = 0;
        $errors  = [];
        $rows    = [];

        foreach ($employees as $emp) {
            $odooEmp = $odooById[(int) $emp->odoo_id] ?? null;
            if (!$odooEmp) {
                $errors[] = "{$emp->name}: odoo_id {$emp->odoo_id} not found in Odoo";
                continue;
            }

            // Already linked to a user → nothing to do.
            $userField = $odooEmp['user_id'] ?? false;
            if (is_array($userField) && !empty($userField)) {
                $skipped++;
                continue;
            }

            // Pick a login: real work_email wins, else generate from emp_code.
            $login = $this->loginFor($emp, $odooEmp, $domain);
            if (!$login) {
                $errors[] = "{$emp->name}: no work_email and no usable emp_code → cannot build a login";
                continue;
            }
            $generated = !filter_var((string) ($odooEmp['work_email'] ?? ''), FILTER_VALIDATE_EMAIL);

            if (!$apply) {
                $this->line(sprintf('  would provision: %-28s %s%s', $emp->name, $login, $generated ? '  (generated)' : ''));
                continue;
            }

            try {
                $existing = $odoo->searchRead('res.users', [['login', '=', $login]], ['id'], 1);
                if (!empty($existing)) {
                    $uid    = (int) $existing[0]['id'];
                    $action = 'linked-existing';
                    $linked++;
                } else {
                    $uid = $odoo->create('res.users', [
                        'name'      => $emp->name,
                        'login'     => $login,
                        'email'     => $login,
                        'password'  => $password,
                        'groups_id' => [[6, 0, [$groupUserId]]],
                    ]);
                    $action = 'created';
                    $created++;
                }

                // Link the employee and backfill its work_email when it was empty.
                $empValues = ['user_id' => $uid];
                if ($generated) {
                    $empValues['work_email'] = $login;
                }
                $odoo->write('hr.employee', [(int) $emp->odoo_id], $empValues);

                $rows[] = [$emp->odoo_id, $emp->name, $login, $uid, $action, $action === 'created' ? $password : ''];
                $this->line("  $action: {$emp->name} → $login (uid $uid)");
            } catch (Throwable $e) {
                $errors[] = "{$emp->name} <{$login}>: " . $e->getMessage();
                $this->error("  FAILED {$emp->name}: " . $e->getMessage());
            }
        }

        if ($apply && !empty($rows)) {
            $path = storage_path('app/employee_users_' . now()->format('Ymd_His') . '.csv');
            $fh = fopen($path, 'w');
            fputcsv($fh, ['odoo_employee_id', 'name', 'login', 'odoo_uid', 'action', 'password']);
            foreach ($rows as $r) {
                fputcsv($fh, $r);
            }
            fclose($fh);
            $this->info("Credentials written to: $path");
        }

        $this->newLine();
        $this->info("$tag Done. created=$created linked=$linked already-linked=$skipped errors=" . count($errors));
        foreach ($errors as $e) {
            $this->warn("  - $e");
        }
        if (!$apply) {
            $this->comment('Dry-run only. Re-run with --apply to write to Odoo.');
        }

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }

    /** Real work_email if valid, else {slug(emp_code)}@{domain}, else null. */
    private function loginFor(Employee $emp, array $odooEmp, string $domain): ?string
    {
        $email = strtolower(trim((string) ($odooEmp['work_email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $code = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $emp->emp_code));
        if ($code === '' || $domain === '') {
            return null;
        }
        return "$code@$domain";
    }

    private function resolveXmlId(OdooService $odoo, string $module, string $name): ?int
    {
        $rows = $odoo->searchRead(
            'ir.model.data',
            [['module', '=', $module], ['name', '=', $name]],
            ['res_id'],
            1
        );
        return isset($rows[0]['res_id']) ? (int) $rows[0]['res_id'] : null;
    }
}
