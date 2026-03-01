-- ================================================================
--  ERP SaaS Extension – Multi-Tenant, Dynamic Modules, EAV,
--  Workflow Engine, Widget Dashboard
--  Run AFTER erp_schema.sql:
--    mysql -u root -p erp_db < saas_schema.sql
-- ================================================================
USE erp_db;

-- ============================================================
-- 1. TENANTS
-- ============================================================

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE, -- URL-safe identifier: "acme-travels"
    name VARCHAR(150) NOT NULL,
    industry ENUM(
        'generic',
        'oil_gas',
        'travel_agency',
        'cinema',
        'fabrication',
        'logistics'
    ) DEFAULT 'generic',
    plan ENUM(
        'trial',
        'starter',
        'professional',
        'enterprise'
    ) DEFAULT 'trial',
    db_schema VARCHAR(80) NULL, -- for future separate-schema mode
    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
    locale VARCHAR(10) DEFAULT 'en_IN',
    logo_path VARCHAR(255) NULL,
    primary_color VARCHAR(7) DEFAULT '#336699', -- hex
    is_active TINYINT(1) DEFAULT 1,
    trial_ends DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Link users to tenants (a user belongs to exactly one tenant)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NULL AFTER id,
ADD CONSTRAINT fk_user_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id);

-- ============================================================
-- 2. MODULE REGISTRY & TENANT MODULES
-- ============================================================

CREATE TABLE IF NOT EXISTS modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE, -- 'hr', 'fleet', 'cinema.box_office'
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    industry VARCHAR(60) DEFAULT 'generic', -- which industry it belongs to
    parent_code VARCHAR(60) NULL, -- for sub-modules
    icon VARCHAR(40) DEFAULT 'module',
    version VARCHAR(20) DEFAULT '1.0.0',
    is_core TINYINT(1) DEFAULT 0, -- core = cannot be disabled
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS tenant_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    module_code VARCHAR(60) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    config_json JSON NULL, -- tenant-specific module config
    enabled_by INT UNSIGNED NULL,
    enabled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_module (tenant_id, module_code),
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (enabled_by) REFERENCES users (id)
);

-- ============================================================
-- 3. CUSTOM FIELDS (EAV model)
-- ============================================================

CREATE TABLE IF NOT EXISTS custom_field_defs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(60) NOT NULL, -- 'employee','vehicle','booking','inventory_item'
    field_code VARCHAR(60) NOT NULL,
    field_label VARCHAR(100) NOT NULL,
    field_type ENUM(
        'text',
        'number',
        'date',
        'select',
        'checkbox',
        'textarea',
        'file'
    ) DEFAULT 'text',
    options_json JSON NULL, -- for select: [{"value":"A","label":"Option A"}]
    placeholder VARCHAR(150) NULL,
    is_required TINYINT(1) DEFAULT 0,
    sort_order SMALLINT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_entity_field (
        tenant_id,
        entity_type,
        field_code
    ),
    FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

CREATE TABLE IF NOT EXISTS custom_field_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    field_def_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    value_text TEXT NULL,
    value_num DECIMAL(16, 4) NULL,
    value_date DATE NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_field_entity (
        field_def_id,
        entity_type,
        entity_id
    ),
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (field_def_id) REFERENCES custom_field_defs (id)
);

-- ============================================================
-- 4. WORKFLOW RULES ENGINE
-- ============================================================

CREATE TABLE IF NOT EXISTS workflow_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    trigger_event VARCHAR(80) NOT NULL, -- 'ticket_sale_count','stock_level','booking_created'
    condition_json JSON NOT NULL, -- {"metric":"ticket_sales","op":">","value":100}
    action_json JSON NOT NULL, -- {"type":"notify","channel":"email","target":"manager","message":"..."}
    last_triggered DATETIME NULL,
    trigger_count INT UNSIGNED DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS workflow_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    triggered_at DATETIME DEFAULT NOW(),
    result ENUM(
        'success',
        'failed',
        'skipped'
    ) DEFAULT 'success',
    detail TEXT NULL,
    FOREIGN KEY (rule_id) REFERENCES workflow_rules (id),
    FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

-- ============================================================
-- 5. DASHBOARD WIDGETS
-- ============================================================

CREATE TABLE IF NOT EXISTS widget_catalogue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    module_code VARCHAR(60) NULL, -- which module this widget belongs to
    data_endpoint VARCHAR(200) NULL, -- API path: '/erp/api/widgets.php?w=total_bookings'
    default_size VARCHAR(10) DEFAULT '1x1', -- '1x1','2x1','1x2' (cols x rows)
    icon VARCHAR(40) NULL,
    industry VARCHAR(60) DEFAULT 'generic'
);

CREATE TABLE IF NOT EXISTS tenant_dashboards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL, -- per-user layout
    layout_json JSON NOT NULL, -- [{widget_code, position, size, config}]
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_user_dash (tenant_id, user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- ============================================================
-- 6. INDUSTRY-SPECIFIC TABLES
-- ============================================================

-- OIL & GAS: Heavy Asset Tracking
CREATE TABLE IF NOT EXISTS assets_heavy (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    asset_tag VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NULL, -- Pump, Compressor, Pipeline, Rig
    location VARCHAR(150) NULL,
    serial_no VARCHAR(80) NULL,
    last_inspection DATE NULL,
    next_inspection DATE NULL,
    compliance_cert VARCHAR(80) NULL,
    status ENUM(
        'operational',
        'maintenance',
        'decommissioned'
    ) DEFAULT 'operational',
    custom_data JSON NULL, -- extensible via JSONB-style storage
    FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

-- CINEMA: Showtime & Ticketing
CREATE TABLE IF NOT EXISTS cinema_screens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    total_seats SMALLINT DEFAULT 100,
    seat_map JSON NULL, -- {"rows": [{"row":"A","seats":10},...]}
    FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

CREATE TABLE IF NOT EXISTS cinema_shows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    screen_id INT UNSIGNED NOT NULL,
    movie_title VARCHAR(200) NOT NULL,
    language VARCHAR(40) NULL,
    show_date DATE NOT NULL,
    show_time TIME NOT NULL,
    base_price DECIMAL(8, 2) DEFAULT 0,
    status ENUM(
        'active',
        'housefull',
        'cancelled'
    ) DEFAULT 'active',
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (screen_id) REFERENCES cinema_screens (id)
);

CREATE TABLE IF NOT EXISTS cinema_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    show_id INT UNSIGNED NOT NULL,
    seat_label VARCHAR(10) NOT NULL,
    customer_name VARCHAR(120) NULL,
    customer_phone VARCHAR(15) NULL,
    price DECIMAL(8, 2) DEFAULT 0,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM(
        'booked',
        'checked_in',
        'cancelled'
    ) DEFAULT 'booked',
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (show_id) REFERENCES cinema_shows (id)
);

-- FABRICATION: Bill of Materials & Job Costing
CREATE TABLE IF NOT EXISTS bom_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    product_code VARCHAR(50) NULL,
    version VARCHAR(20) DEFAULT '1.0',
    status ENUM(
        'draft',
        'approved',
        'obsolete'
    ) DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (created_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS bom_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bom_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NULL, -- ref to inventory items if applicable
    description VARCHAR(200) NOT NULL,
    material VARCHAR(80) NULL, -- e.g., "Mild Steel", "Aluminium 6061"
    gauge VARCHAR(30) NULL, -- custom: "10mm", "18 AWG"
    qty DECIMAL(10, 3) NOT NULL,
    unit VARCHAR(20) DEFAULT 'Nos',
    unit_cost DECIMAL(10, 2) DEFAULT 0,
    total_cost DECIMAL(12, 2) GENERATED ALWAYS AS (qty * unit_cost) STORED,
    FOREIGN KEY (bom_id) REFERENCES bom_items (id),
    FOREIGN KEY (item_id) REFERENCES items (id)
);

CREATE TABLE IF NOT EXISTS job_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    job_no VARCHAR(30) NOT NULL UNIQUE,
    bom_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    description TEXT NULL,
    qty_ordered DECIMAL(10, 3) DEFAULT 1,
    est_cost DECIMAL(12, 2) DEFAULT 0,
    actual_cost DECIMAL(12, 2) DEFAULT 0,
    start_date DATE NULL,
    due_date DATE NULL,
    status ENUM(
        'queued',
        'in_progress',
        'qc',
        'completed',
        'cancelled'
    ) DEFAULT 'queued',
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (bom_id) REFERENCES bom_items (id),
    FOREIGN KEY (customer_id) REFERENCES customers (id)
);

-- TRAVEL AGENCY: Flight & Hotel Bookings (API integration layer)
CREATE TABLE IF NOT EXISTS agency_bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_ref VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    type ENUM(
        'flight',
        'hotel',
        'package',
        'visa'
    ) NOT NULL,
    provider_ref VARCHAR(60) NULL, -- external PNR / booking ID from API
    provider_name VARCHAR(80) NULL, -- 'Amadeus','Sabre','Booking.com'
    details_json JSON NULL, -- full booking response from API
    amount DECIMAL(12, 2) DEFAULT 0,
    commission DECIMAL(10, 2) DEFAULT 0,
    status ENUM(
        'pending',
        'confirmed',
        'cancelled'
    ) DEFAULT 'confirmed',
    travel_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    FOREIGN KEY (customer_id) REFERENCES customers (id)
);

-- ============================================================
-- 7. SEED DATA
-- ============================================================

-- Default module catalogue
INSERT IGNORE INTO
    modules (
        code,
        name,
        description,
        industry,
        is_core
    )
VALUES
    -- Core (always on)
    (
        'core.auth',
        'Authentication & RBAC',
        'Login, sessions, roles',
        'generic',
        1
    ),
    (
        'core.dashboard',
        'Dashboard',
        'KPI widgets, quick access',
        'generic',
        1
    ),
    (
        'core.audit',
        'Audit Log',
        'Action tracking',
        'generic',
        1
    ),

-- Generic modules (toggleable)
(
    'hr',
    'HR & Payroll',
    'Employees, attendance, payroll',
    'generic',
    0
),
(
    'inventory',
    'Inventory',
    'Stock, procurement, warehouses',
    'generic',
    0
),
(
    'fleet',
    'Fleet Management',
    'Vehicles, drivers, maintenance',
    'generic',
    0
),
(
    'travel',
    'Corporate Travel',
    'Bookings, duty slips, invoices',
    'generic',
    0
),
(
    'expense',
    'Expense Management',
    'Submit & approve expenses',
    'generic',
    0
),
(
    'vendor',
    'Vendor Management',
    'Suppliers & purchase orders',
    'generic',
    0
),
(
    'crm',
    'CRM',
    'Customers & support tickets',
    'generic',
    0
),
(
    'reports',
    'Reports & Analytics',
    'Financial, HR, logistics',
    'generic',
    0
),

-- Industry-specific
(
    'oil_gas.assets',
    'Heavy Asset Tracking',
    'Rigs, compressors, pipelines',
    'oil_gas',
    0
),
(
    'oil_gas.compliance',
    'Compliance Management',
    'Regulatory & safety compliance',
    'oil_gas',
    0
),
(
    'cinema.screens',
    'Screen & Seat Management',
    'Seat maps, screen config',
    'cinema',
    0
),
(
    'cinema.showtime',
    'Showtime Scheduling',
    'Shows, pricing, booking',
    'cinema',
    0
),
(
    'cinema.boxoffice',
    'Box Office Ticketing',
    'Sell and scan tickets',
    'cinema',
    0
),
(
    'cinema.concession',
    'Concession Inventory',
    'Food & beverage stock tracking',
    'cinema',
    0
),
(
    'fabrication.bom',
    'Bill of Materials',
    'Product BOM with components',
    'fabrication',
    0
),
(
    'fabrication.jobs',
    'Job Costing',
    'Job orders and cost tracking',
    'fabrication',
    0
),
(
    'fabrication.cad',
    'CAD File Storage',
    'Upload and version CAD files',
    'fabrication',
    0
),
(
    'agency.flights',
    'Flight Bookings',
    'API-integrated flight booking',
    'travel_agency',
    0
),
(
    'agency.hotels',
    'Hotel Bookings',
    'Hotel booking with commissions',
    'travel_agency',
    0
),
(
    'agency.itinerary',
    'Itinerary Builder',
    'Build & share trip itineraries',
    'travel_agency',
    0
);

-- Default widget catalogue
INSERT IGNORE INTO
    widget_catalogue (
        code,
        name,
        module_code,
        data_endpoint,
        default_size,
        industry
    )
VALUES (
        'w.total_employees',
        'Total Employees',
        'hr',
        '/erp/api/widgets.php?w=total_employees',
        '1x1',
        'generic'
    ),
    (
        'w.today_bookings',
        'Today\'s Bookings',
        'travel',
        '/erp/api/widgets.php?w=today_bookings',
        '1x1',
        'generic'
    ),
    (
        'w.low_stock',
        'Low Stock Alert',
        'inventory',
        '/erp/api/widgets.php?w=low_stock',
        '1x1',
        'generic'
    ),
    (
        'w.open_tickets',
        'Open Tickets',
        'crm',
        '/erp/api/widgets.php?w=open_tickets',
        '1x1',
        'generic'
    ),
    (
        'w.pending_expenses',
        'Pending Expenses',
        'expense',
        '/erp/api/widgets.php?w=pending_expenses',
        '1x1',
        'generic'
    ),
    (
        'w.monthly_revenue',
        'Monthly Revenue',
        'reports',
        '/erp/api/widgets.php?w=monthly_revenue',
        '2x1',
        'generic'
    ),
    (
        'w.fleet_expiry',
        'Vehicle Expiry',
        'fleet',
        '/erp/api/widgets.php?w=fleet_expiry',
        '1x1',
        'generic'
    ),
    (
        'w.cinema_today',
        'Shows Today',
        'cinema.showtime',
        '/erp/api/widgets.php?w=cinema_today',
        '2x1',
        'cinema'
    ),
    (
        'w.ticket_sales',
        'Ticket Sales',
        'cinema.boxoffice',
        '/erp/api/widgets.php?w=ticket_sales',
        '1x1',
        'cinema'
    ),
    (
        'w.active_jobs',
        'Active Job Orders',
        'fabrication.jobs',
        '/erp/api/widgets.php?w=active_jobs',
        '1x1',
        'fabrication'
    ),
    (
        'w.bom_count',
        'BOMs Approved',
        'fabrication.bom',
        '/erp/api/widgets.php?w=bom_count',
        '1x1',
        'fabrication'
    ),
    (
        'w.asset_alerts',
        'Asset Alerts',
        'oil_gas.assets',
        '/erp/api/widgets.php?w=asset_alerts',
        '2x1',
        'oil_gas'
    );

-- Seed system/demo tenant
INSERT IGNORE INTO
    tenants (
        slug,
        name,
        industry,
        plan,
        is_active
    )
VALUES (
        'demo',
        'Demo Company',
        'generic',
        'enterprise',
        1
    );