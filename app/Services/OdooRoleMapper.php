<?php

namespace App\Services;

/**
 * Translates a user's Odoo group memberships into the Laravel role slugs
 * that this app uses for authorization gates.
 *
 * Groups are matched by their "Category / Name" full name so we don't break
 * if Odoo renumbers IDs across installs.
 */
class OdooRoleMapper
{
    /** @var array<string, string[]>  role => list of Odoo "Category / Name" strings */
    public const MAP = [
        'admin' => [
            'Administration / Access Rights',
        ],
        'hr_manager' => [
            'Employees / Administrator',
            'Contracts / Employee Manager',
        ],
        'hr_officer' => [
            'Employees / Officer: Manage all employees',
        ],
        'payroll_manager' => [
            'Payroll / Manager',
        ],
        'payroll_officer' => [
            'Payroll / Officer',
        ],
        'leave_manager' => [
            'Time Off / Administrator',
            'Time Off / Officer: Manage all requests',
        ],
        'recruitment_manager' => [
            'Recruitment / Administrator',
        ],
        'recruitment_officer' => [
            'Recruitment / Officer: Manage all applicants',
        ],
        'employee' => [
            'User types / Internal User',
        ],
    ];

    /**
     * Given a list of group descriptors as returned from
     * `res.groups.read(ids, ['name', 'category_id'])`, return the list of
     * Laravel role slugs the user has.
     *
     * @param array<int, array{name: string, category_id: array|false}> $groups
     * @return string[]
     */
    public function rolesFromGroups(array $groups): array
    {
        // Build a set of "Category / Name" full-names the user is in
        $userFullNames = [];
        foreach ($groups as $g) {
            $catName = is_array($g['category_id']) ? ($g['category_id'][1] ?? null) : null;
            $name    = $g['name'] ?? null;
            if (!$catName || !$name) continue;
            $userFullNames[] = "{$catName} / {$name}";
        }
        $userSet = array_flip($userFullNames);

        $roles = [];
        foreach (self::MAP as $role => $required) {
            foreach ($required as $fullName) {
                if (isset($userSet[$fullName])) {
                    $roles[] = $role;
                    continue 2;
                }
            }
        }

        // Admin implies everything below — additive
        if (in_array('admin', $roles, true)) {
            $roles = array_values(array_unique(array_merge($roles, array_keys(self::MAP))));
        }

        // hr_manager implies hr_officer
        if (in_array('hr_manager', $roles, true) && !in_array('hr_officer', $roles, true)) {
            $roles[] = 'hr_officer';
        }

        // payroll_manager implies payroll_officer
        if (in_array('payroll_manager', $roles, true) && !in_array('payroll_officer', $roles, true)) {
            $roles[] = 'payroll_officer';
        }

        // recruitment_manager implies recruitment_officer
        if (in_array('recruitment_manager', $roles, true) && !in_array('recruitment_officer', $roles, true)) {
            $roles[] = 'recruitment_officer';
        }

        // Every authenticated user is at least "employee"
        if (!in_array('employee', $roles, true)) {
            $roles[] = 'employee';
        }

        return array_values(array_unique($roles));
    }

    /**
     * Fetch user groups from Odoo and resolve roles in one call.
     */
    public function rolesForOdooUser(OdooService $odoo, int $odooUid): array
    {
        $userRow = $odoo->read('res.users', [$odooUid], ['groups_id']);
        $groupIds = $userRow[0]['groups_id'] ?? [];

        if (empty($groupIds)) return ['employee'];

        $groups = $odoo->read('res.groups', $groupIds, ['name', 'category_id']);
        return $this->rolesFromGroups($groups);
    }
}
