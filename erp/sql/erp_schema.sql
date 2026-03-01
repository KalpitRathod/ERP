-- ================================================================
--  ERP System – Unified Database Schema
--  Run: mysql -u root -p erp_db < erp_schema.sql
-- ================================================================

CREATE DATABASE IF NOT EXISTS erp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE erp_db;

-- -----------------------------------------------------------
-- CORE / AUTH
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    head_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- super_admin, hr_manager, etc.
    label VARCHAR(80) NOT NULL,
    permissions JSON NULL -- {"module": ["read","write"]}
);

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emp_code VARCHAR(20) UNIQUE,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NULL,
    phone VARCHAR(20) NULL,
    status ENUM(
        'active',
        'inactive',
        'suspended'
    ) DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles (id),
    FOREIGN KEY (department_id) REFERENCES departments (id)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(80) NOT NULL,
    record_id INT UNSIGNED NULL,
    detail TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- -----------------------------------------------------------
-- HR & PAYROLL
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    designation VARCHAR(100) NOT NULL,
    doj DATE NOT NULL, -- Date of Joining
    dob DATE NULL,
    gender ENUM('M', 'F', 'Other') NULL,
    address TEXT NULL,
    bank_name VARCHAR(80) NULL,
    bank_acc VARCHAR(30) NULL,
    bank_ifsc VARCHAR(15) NULL,
    salary_basic DECIMAL(10, 2) DEFAULT 0.00,
    pf_applicable TINYINT(1) DEFAULT 1,
    esi_applicable TINYINT(1) DEFAULT 0,
    status ENUM(
        'active',
        'resigned',
        'terminated'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emp_id INT UNSIGNED NOT NULL,
    att_date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    status ENUM(
        'present',
        'absent',
        'half_day',
        'leave',
        'holiday'
    ) DEFAULT 'present',
    remarks VARCHAR(255) NULL,
    UNIQUE KEY uq_emp_date (emp_id, att_date),
    FOREIGN KEY (emp_id) REFERENCES employees (id)
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emp_id INT UNSIGNED NOT NULL,
    leave_type ENUM('CL', 'SL', 'PL', 'LWP') DEFAULT 'CL',
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    days DECIMAL(4, 1) NOT NULL,
    reason TEXT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'pending',
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES employees (id),
    FOREIGN KEY (approved_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emp_id INT UNSIGNED NOT NULL,
    month TINYINT NOT NULL, -- 1-12
    year SMALLINT NOT NULL,
    working_days TINYINT DEFAULT 26,
    present_days TINYINT DEFAULT 0,
    basic DECIMAL(10, 2) DEFAULT 0,
    hra DECIMAL(10, 2) DEFAULT 0,
    other_allow DECIMAL(10, 2) DEFAULT 0,
    gross DECIMAL(10, 2) DEFAULT 0,
    pf_deduction DECIMAL(10, 2) DEFAULT 0,
    esi_deduction DECIMAL(10, 2) DEFAULT 0,
    tds_deduction DECIMAL(10, 2) DEFAULT 0,
    other_deductions DECIMAL(10, 2) DEFAULT 0,
    net_pay DECIMAL(10, 2) DEFAULT 0,
    status ENUM('draft', 'processed', 'paid') DEFAULT 'draft',
    paid_on DATE NULL,
    UNIQUE KEY uq_payroll (emp_id, month, year),
    FOREIGN KEY (emp_id) REFERENCES employees (id)
);

-- -----------------------------------------------------------
-- INVENTORY & SUPPLY CHAIN
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS item_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    parent INT UNSIGNED NULL
);

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NULL,
    unit VARCHAR(20) DEFAULT 'Nos', -- Nos, Kg, Ltr, etc.
    hsn_code VARCHAR(10) NULL,
    gst_rate DECIMAL(5, 2) DEFAULT 18.00,
    reorder_level DECIMAL(10, 2) DEFAULT 0,
    description TEXT NULL,
    FOREIGN KEY (category_id) REFERENCES item_categories (id)
);

CREATE TABLE IF NOT EXISTS warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(200) NOT NULL,
    manager_id INT UNSIGNED NULL,
    FOREIGN KEY (manager_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    qty DECIMAL(12, 3) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_wh (item_id, warehouse_id),
    FOREIGN KEY (item_id) REFERENCES items (id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    type ENUM(
        'in',
        'out',
        'transfer',
        'adjustment'
    ) NOT NULL,
    qty DECIMAL(12, 3) NOT NULL,
    ref_type VARCHAR(30) NULL, -- 'po','sale','adjustment'
    ref_id INT UNSIGNED NULL,
    moved_by INT UNSIGNED NULL,
    moved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remarks VARCHAR(255) NULL,
    FOREIGN KEY (item_id) REFERENCES items (id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
);

-- -----------------------------------------------------------
-- VENDORS & PROCUREMENT
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    gst_no VARCHAR(20) NULL,
    pan_no VARCHAR(12) NULL,
    contact VARCHAR(12) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    category VARCHAR(80) NULL, -- Fuel, Maintenance, IT, etc.
    payment_terms VARCHAR(80) NULL,
    rating TINYINT DEFAULT 3, -- 1-5
    status ENUM(
        'active',
        'inactive',
        'blacklisted'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) NOT NULL UNIQUE,
    vendor_id INT UNSIGNED NOT NULL,
    raised_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    po_date DATE NOT NULL,
    delivery_date DATE NULL,
    warehouse_id INT UNSIGNED NULL,
    total_amount DECIMAL(12, 2) DEFAULT 0,
    gst_amount DECIMAL(12, 2) DEFAULT 0,
    grand_total DECIMAL(12, 2) DEFAULT 0,
    status ENUM(
        'draft',
        'sent',
        'approved',
        'received',
        'cancelled'
    ) DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors (id),
    FOREIGN KEY (raised_by) REFERENCES users (id),
    FOREIGN KEY (approved_by) REFERENCES users (id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
);

CREATE TABLE IF NOT EXISTS po_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    qty DECIMAL(12, 3) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    gst_rate DECIMAL(5, 2) DEFAULT 18.00,
    total DECIMAL(12, 2) NOT NULL,
    received_qty DECIMAL(12, 3) DEFAULT 0,
    FOREIGN KEY (po_id) REFERENCES purchase_orders (id),
    FOREIGN KEY (item_id) REFERENCES items (id)
);

-- -----------------------------------------------------------
-- FLEET & ASSET MANAGEMENT
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS drivers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emp_id INT UNSIGNED NULL, -- if employed in-house
    name VARCHAR(120) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    licence_no VARCHAR(30) NOT NULL UNIQUE,
    licence_expiry DATE NOT NULL,
    licence_type VARCHAR(40) NULL,
    status ENUM(
        'active',
        'on_leave',
        'inactive'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES employees (id)
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reg_no VARCHAR(20) NOT NULL UNIQUE,
    type VARCHAR(60) NOT NULL, -- Innova, Dzire, Truck...
    model VARCHAR(100) NULL,
    fuel_type ENUM(
        'Diesel',
        'Petrol',
        'CNG',
        'Electric',
        'Other'
    ) DEFAULT 'Diesel',
    seating TINYINT DEFAULT 4,
    driver_id INT UNSIGNED NULL,
    insurance_exp DATE NULL,
    puc_exp DATE NULL,
    fitness_exp DATE NULL,
    permit_exp DATE NULL,
    odometer_km INT UNSIGNED DEFAULT 0,
    status ENUM(
        'active',
        'maintenance',
        'inactive'
    ) DEFAULT 'active',
    purchased_on DATE NULL,
    purchase_price DECIMAL(12, 2) NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers (id)
);

CREATE TABLE IF NOT EXISTS maintenance_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    maint_date DATE NOT NULL,
    type ENUM(
        'routine',
        'breakdown',
        'accident',
        'inspection'
    ) DEFAULT 'routine',
    description TEXT NULL,
    vendor_id INT UNSIGNED NULL,
    cost DECIMAL(10, 2) DEFAULT 0,
    odometer_km INT UNSIGNED NULL,
    next_due_km INT UNSIGNED NULL,
    next_due_dt DATE NULL,
    done_by INT UNSIGNED NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
    FOREIGN KEY (vendor_id) REFERENCES vendors (id),
    FOREIGN KEY (done_by) REFERENCES users (id)
);

-- -----------------------------------------------------------
-- TRAVEL & BOOKINGS (TravelBook integration)
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    gst_no VARCHAR(20) NULL,
    contact VARCHAR(120) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(15) NULL,
    billing_mode ENUM(
        'Monthly',
        'Per Trip',
        'Quarterly'
    ) DEFAULT 'Monthly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS travel_bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) NOT NULL UNIQUE,
    company_id INT UNSIGNED NULL,
    passenger_name VARCHAR(120) NOT NULL,
    passenger_phone VARCHAR(15) NOT NULL,
    booking_type ENUM(
        'Full Day',
        'Local',
        'Airport Pickup',
        'Airport Drop',
        'Outstation'
    ) NOT NULL,
    pickup_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    pickup_location TEXT NOT NULL,
    drop_location TEXT NOT NULL,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    est_km INT NULL,
    base_rate DECIMAL(10, 2) DEFAULT 0,
    status ENUM(
        'confirmed',
        'ongoing',
        'completed',
        'cancelled'
    ) DEFAULT 'confirmed',
    booked_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies (id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles (id),
    FOREIGN KEY (driver_id) REFERENCES drivers (id),
    FOREIGN KEY (booked_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS duty_slips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL UNIQUE,
    start_km INT UNSIGNED NULL,
    end_km INT UNSIGNED NULL,
    total_km INT UNSIGNED GENERATED ALWAYS AS (end_km - start_km) STORED,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    toll_charges DECIMAL(8, 2) DEFAULT 0,
    parking_charges DECIMAL(8, 2) DEFAULT 0,
    night_halt_charges DECIMAL(8, 2) DEFAULT 0,
    submitted_by INT UNSIGNED NULL,
    submitted_at TIMESTAMP NULL,
    status ENUM(
        'pending',
        'submitted',
        'verified'
    ) DEFAULT 'pending',
    FOREIGN KEY (booking_id) REFERENCES travel_bookings (id),
    FOREIGN KEY (submitted_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    company_id INT UNSIGNED NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    trips_count INT DEFAULT 0,
    amount DECIMAL(12, 2) DEFAULT 0,
    gst_rate DECIMAL(5, 2) DEFAULT 5.00,
    gst_amount DECIMAL(12, 2) DEFAULT 0,
    total_amount DECIMAL(12, 2) DEFAULT 0,
    due_date DATE NULL,
    bill_mode ENUM(
        'NEFT',
        'Cheque',
        'UPI',
        'Cash'
    ) DEFAULT 'NEFT',
    status ENUM(
        'draft',
        'sent',
        'paid',
        'overdue',
        'cancelled'
    ) DEFAULT 'draft',
    generated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies (id),
    FOREIGN KEY (generated_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    paid_amount DECIMAL(12, 2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_mode ENUM(
        'NEFT',
        'RTGS',
        'Cheque',
        'UPI',
        'Cash'
    ) DEFAULT 'NEFT',
    reference_no VARCHAR(50) NULL,
    remarks TEXT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices (id),
    FOREIGN KEY (recorded_by) REFERENCES users (id)
);

-- -----------------------------------------------------------
-- EXPENSE MANAGEMENT
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emp_id INT UNSIGNED NOT NULL,
    category ENUM(
        'Travel',
        'Fuel',
        'Accommodation',
        'Food',
        'Office',
        'Other'
    ) DEFAULT 'Other',
    description TEXT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    exp_date DATE NOT NULL,
    payment_mode ENUM(
        'Cash',
        'Card',
        'UPI',
        'Reimbursement'
    ) DEFAULT 'Cash',
    receipt_path VARCHAR(255) NULL,
    project_ref VARCHAR(80) NULL,
    status ENUM(
        'draft',
        'submitted',
        'approved',
        'rejected',
        'reimbursed'
    ) DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES employees (id)
);

CREATE TABLE IF NOT EXISTS expense_approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id INT UNSIGNED NOT NULL,
    approver_id INT UNSIGNED NOT NULL,
    action ENUM(
        'approved',
        'rejected',
        'queried'
    ) NOT NULL,
    remark TEXT NULL,
    actioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES expenses (id),
    FOREIGN KEY (approver_id) REFERENCES users (id)
);

-- -----------------------------------------------------------
-- CRM
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL, -- link to billing company
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(15) NULL,
    tier ENUM(
        'Standard',
        'Silver',
        'Gold',
        'Platinum'
    ) DEFAULT 'Standard',
    assigned_to INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies (id),
    FOREIGN KEY (assigned_to) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NULL,
    priority ENUM(
        'low',
        'medium',
        'high',
        'critical'
    ) DEFAULT 'medium',
    status ENUM(
        'open',
        'in_progress',
        'resolved',
        'closed'
    ) DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers (id),
    FOREIGN KEY (assigned_to) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets (id),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- -----------------------------------------------------------
-- SEED DATA
-- -----------------------------------------------------------

INSERT IGNORE INTO
    roles (name, label)
VALUES ('super_admin', 'Super Admin'),
    ('hr_manager', 'HR Manager'),
    (
        'logistics_manager',
        'Logistics Manager'
    ),
    (
        'travel_coordinator',
        'Travel Coordinator'
    ),
    ('accounts', 'Accounts'),
    ('employee', 'Employee'),
    ('vendor', 'Vendor User');

INSERT IGNORE INTO
    departments (name, code)
VALUES ('Administration', 'ADMIN'),
    ('Human Resources', 'HR'),
    ('Logistics', 'LOG'),
    ('Finance', 'FIN'),
    ('Operations', 'OPS'),
    ('IT', 'IT');

-- Default super admin  (password: Admin@1234)
INSERT IGNORE INTO
    users (
        emp_code,
        name,
        email,
        password_hash,
        role_id,
        department_id,
        status
    )
VALUES (
        'EMP001',
        'System Administrator',
        'admin@erp.local',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: Admin@1234
        1,
        1,
        'active'
    );