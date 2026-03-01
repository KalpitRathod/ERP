<?php
// ================================================================
//  ERP – Module Registry
//  /erp/config/modules.php
//  Defines all available modules, their metadata, nav structure,
//  and required roles. tenants/tenant_modules table controls
//  which modules are ON per tenant at runtime.
// ================================================================

return [
    // code => [name, roles_allowed, nav_parent, nav_label, url, industry]

    // ---- CORE (always enabled) -----------------------------------
    'core.auth'      => ['name'=>'Auth & RBAC',     'core'=>true, 'industry'=>'generic'],
    'core.dashboard' => ['name'=>'Dashboard',        'core'=>true, 'industry'=>'generic',
                         'url'=> BASE_URL . '/dashboard/index.php'],
    'core.audit'     => ['name'=>'Audit Log',        'core'=>true, 'industry'=>'generic'],

    // ---- GENERIC MODULES ----------------------------------------
    'hr' => [
        'name'    => 'HR & Payroll',
        'industry'=> 'generic',
        'roles'   => ['super_admin','hr_manager'],
        'nav'     => [
            ['label'=>'Employees',  'url'=>BASE_URL.'/modules/hr/employees.php'],
            ['label'=>'Attendance', 'url'=>BASE_URL.'/modules/hr/attendance.php'],
            ['label'=>'Payroll',    'url'=>BASE_URL.'/modules/hr/payroll.php'],
        ],
    ],
    'inventory' => [
        'name'    => 'Inventory',
        'industry'=> 'generic',
        'roles'   => ['super_admin','logistics_manager'],
        'nav'     => [
            ['label'=>'Stock',        'url'=>BASE_URL.'/modules/inventory/stock.php'],
            ['label'=>'Procurement',  'url'=>BASE_URL.'/modules/inventory/procurement.php'],
        ],
    ],
    'fleet' => [
        'name'    => 'Fleet',
        'industry'=> 'generic',
        'roles'   => ['super_admin','logistics_manager'],
        'nav'     => [
            ['label'=>'Vehicles',    'url'=>BASE_URL.'/modules/fleet/vehicles.php'],
            ['label'=>'Maintenance', 'url'=>BASE_URL.'/modules/fleet/maintenance.php'],
        ],
    ],
    'travel' => [
        'name'    => 'Travel',
        'industry'=> 'generic',
        'roles'   => ['super_admin','travel_coordinator','employee'],
        'nav'     => [
            ['label'=>'Bookings',        'url'=>BASE_URL.'/modules/travel/bookings.php'],
            ['label'=>'Duty Slips',      'url'=>BASE_URL.'/modules/travel/expense.php'],
        ],
    ],
    'expense' => [
        'name'    => 'Expenses',
        'industry'=> 'generic',
        'roles'   => ['super_admin','accounts','employee','hr_manager'],
        'nav'     => [
            ['label'=>'My Expenses', 'url'=>BASE_URL.'/modules/expense/expenses.php'],
            ['label'=>'Approvals',   'url'=>BASE_URL.'/modules/expense/approvals.php'],
        ],
    ],
    'vendor' => [
        'name'    => 'Vendors',
        'industry'=> 'generic',
        'roles'   => ['super_admin','logistics_manager','accounts'],
        'nav'     => [
            ['label'=>'Vendors',         'url'=>BASE_URL.'/modules/vendor/vendors.php'],
            ['label'=>'Purchase Orders', 'url'=>BASE_URL.'/modules/vendor/purchase_orders.php'],
        ],
    ],
    'crm' => [
        'name'    => 'CRM',
        'industry'=> 'generic',
        'roles'   => ['super_admin','accounts','travel_coordinator'],
        'nav'     => [
            ['label'=>'Customers',       'url'=>BASE_URL.'/modules/crm/customers.php'],
            ['label'=>'Support Tickets', 'url'=>BASE_URL.'/modules/crm/tickets.php'],
        ],
    ],
    'reports' => [
        'name'  => 'Reports',
        'industry'=> 'generic',
        'roles' => ['super_admin','accounts','hr_manager','logistics_manager'],
        'nav'   => [['label'=>'Reports', 'url'=>BASE_URL.'/modules/reports/index.php']],
    ],

    // ---- INDUSTRY: OIL & GAS ------------------------------------
    'oil_gas.assets' => [
        'name'    => 'Heavy Asset Tracking',
        'industry'=> 'oil_gas',
        'roles'   => ['super_admin','logistics_manager'],
        'nav'     => [['label'=>'Assets', 'url'=>BASE_URL.'/modules/oil_gas/assets.php']],
    ],
    'oil_gas.compliance' => [
        'name'    => 'Compliance',
        'industry'=> 'oil_gas',
        'roles'   => ['super_admin'],
        'nav'     => [['label'=>'Compliance', 'url'=>BASE_URL.'/modules/oil_gas/compliance.php']],
    ],

    // ---- INDUSTRY: CINEMA ---------------------------------------
    'cinema.screens'   => ['name'=>'Screens',       'industry'=>'cinema', 'roles'=>['super_admin']],
    'cinema.showtime'  => ['name'=>'Showtimes',      'industry'=>'cinema', 'roles'=>['super_admin'],
        'nav' => [['label'=>'Showtimes', 'url'=>BASE_URL.'/modules/cinema/showtimes.php']]],
    'cinema.boxoffice' => ['name'=>'Box Office',     'industry'=>'cinema', 'roles'=>['super_admin','employee'],
        'nav' => [['label'=>'Box Office','url'=>BASE_URL.'/modules/cinema/box_office.php']]],
    'cinema.concession'=> ['name'=>'Concession',     'industry'=>'cinema', 'roles'=>['super_admin']],

    // ---- INDUSTRY: FABRICATION ----------------------------------
    'fabrication.bom'  => ['name'=>'Bill of Materials', 'industry'=>'fabrication', 'roles'=>['super_admin'],
        'nav' => [['label'=>'BOM','url'=>BASE_URL.'/modules/fabrication/bom.php']]],
    'fabrication.jobs' => ['name'=>'Job Orders',        'industry'=>'fabrication', 'roles'=>['super_admin'],
        'nav' => [['label'=>'Jobs','url'=>BASE_URL.'/modules/fabrication/jobs.php']]],
    'fabrication.cad'  => ['name'=>'CAD Files',         'industry'=>'fabrication', 'roles'=>['super_admin']],

    // ---- INDUSTRY: TRAVEL AGENCY --------------------------------
    'agency.flights'   => ['name'=>'Flight Bookings', 'industry'=>'travel_agency', 'roles'=>['super_admin','employee'],
        'nav' => [['label'=>'Flights','url'=>BASE_URL.'/modules/agency/flights.php']]],
    'agency.hotels'    => ['name'=>'Hotel Bookings',  'industry'=>'travel_agency', 'roles'=>['super_admin','employee'],
        'nav' => [['label'=>'Hotels','url'=>BASE_URL.'/modules/agency/hotels.php']]],
    'agency.itinerary' => ['name'=>'Itinerary Builder','industry'=>'travel_agency','roles'=>['super_admin','employee'],
        'nav' => [['label'=>'Itineraries','url'=>BASE_URL.'/modules/agency/itineraries.php']]],
];
