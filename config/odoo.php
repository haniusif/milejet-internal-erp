<?php

return [
    // البيانات الافتراضية (للـ background sync و jobs)
    'url'        => env('ODOO_URL', 'http://localhost:8069'),
    'db'         => env('ODOO_DB', 'odoo'),
    'username'   => env('ODOO_USERNAME', 'admin'),
    'api_key'    => env('ODOO_API_KEY', ''),
    'verify_ssl' => env('ODOO_VERIFY_SSL', true),

    // hr.payroll.structure id used for contracts created from the app (SA-STD)
    'payroll_struct_id' => (int) env('ODOO_PAYROLL_STRUCT_ID', 1),
];
