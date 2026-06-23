<?php

namespace App\Services;

use App\Models\User;

/**
 * Shared Odoo-backed login: authenticates the credentials against Odoo and
 * upserts the local user (name, groups, mapped roles, hr.employee link).
 * Used by the web login, the SPA API (/api/v1/auth) and available to the
 * mobile API.
 */
class OdooAuthService
{
    public function __construct(
        protected OdooService $odoo,
        protected OdooRoleMapper $roleMapper,
    ) {}

    /** Returns the upserted User, or null when Odoo rejects the credentials. */
    public function attempt(string $email, string $password): ?User
    {
        $uid = $this->odoo->tryAuthenticate($email, $password);
        if (!$uid) {
            return null;
        }

        $this->odoo->setCredentials($email, $password, $uid);

        $name     = $email;
        $groupIds = [];
        try {
            $rows = $this->odoo->read('res.users', [$uid], ['name', 'groups_id']);
            if (!empty($rows[0])) {
                $name     = $rows[0]['name'] ?: $name;
                $groupIds = $rows[0]['groups_id'] ?? [];
            }
        } catch (\Exception) {
            // fall through with defaults
        }

        // Link to hr.employee via user_id (User::employeeRecord() still
        // falls back to the work-email match when this stays null).
        $odooEmployeeId = null;
        try {
            $emp = $this->odoo->searchRead('hr.employee', [['user_id', '=', $uid]], ['id'], 1);
            $odooEmployeeId = $emp[0]['id'] ?? null;
        } catch (\Exception) {
            // non-fatal
        }

        $roles = ['employee'];
        try {
            if (!empty($groupIds)) {
                $groups = $this->odoo->read('res.groups', $groupIds, ['name', 'category_id']);
                $roles  = $this->roleMapper->rolesFromGroups($groups);
            }
        } catch (\Exception) {
            // keep default
        }

        return User::updateOrCreate(
            ['odoo_uid' => $uid],
            [
                'name'             => $name,
                'email'            => $email,
                'odoo_api_key'     => $password,
                'odoo_employee_id' => $odooEmployeeId,
                'odoo_group_ids'   => $groupIds,
                'roles'            => $roles,
                'roles_synced_at'  => now(),
            ]
        );
    }
}
