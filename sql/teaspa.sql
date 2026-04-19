-- ============================================================
-- TEA SPA RESERVATION AND OPERATIONAL MANAGEMENT SYSTEM
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS teaspa_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE teaspa_db;

-- ============================================================
-- TABLE: users (Sprint 1 - Authentication)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    username    VARCHAR(50)   NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('manager','therapist','cashier','purchasing','accounting') NOT NULL,
    is_active   TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role     (role)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: services (Sprint 2 - Reservation)
-- ============================================================
CREATE TABLE IF NOT EXISTS services (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)   NOT NULL,
    description TEXT,
    duration    INT            NOT NULL COMMENT 'Duration in minutes (e.g. 60, 90)',
    price       DECIMAL(12,2)  NOT NULL,
    is_active   TINYINT(1)     DEFAULT 1,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: reservations (Sprint 2 - Reservation)
-- ============================================================
CREATE TABLE IF NOT EXISTS reservations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_name   VARCHAR(100)  NOT NULL,
    phone_number    VARCHAR(20)   NOT NULL,
    service_id      INT           NOT NULL,
    therapist_id    INT           NULL COMMENT 'Assigned therapist (users.id)',
    room_number     CHAR(2)       NOT NULL COMMENT '01-08',
    reservation_date DATE         NOT NULL,
    reservation_time TIME         NOT NULL,
    end_time        TIME          NOT NULL COMMENT 'Calculated: start + service duration',
    status          ENUM('Menunggu','Proses','Selesai') DEFAULT 'Menunggu',
    notes           TEXT,
    created_by      INT           NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_res_service   (service_id)   REFERENCES services(id)  ON UPDATE CASCADE,
    FOREIGN KEY fk_res_therapist (therapist_id) REFERENCES users(id)     ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY fk_res_creator   (created_by)   REFERENCES users(id)     ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_date       (reservation_date),
    INDEX idx_status     (status),
    INDEX idx_room       (room_number),
    INDEX idx_therapist  (therapist_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: payments (Sprint 4 - Payment)
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id  INT           NOT NULL UNIQUE,
    amount          DECIMAL(12,2) NOT NULL,
    payment_method  ENUM('tunai','transfer') DEFAULT 'tunai',
    payment_status  ENUM('Belum Bayar','Lunas') DEFAULT 'Belum Bayar',
    paid_at         TIMESTAMP     NULL,
    confirmed_by    INT           NULL COMMENT 'Cashier user id',
    notes           TEXT,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_pay_reservation (reservation_id) REFERENCES reservations(id) ON UPDATE CASCADE,
    FOREIGN KEY fk_pay_cashier     (confirmed_by)   REFERENCES users(id)        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_status (payment_status),
    INDEX idx_paid   (paid_at)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: therapist_schedules (Sprint 5 - Therapist Schedule)
-- ============================================================
CREATE TABLE IF NOT EXISTS therapist_schedules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    therapist_id    INT           NOT NULL,
    schedule_date   DATE          NOT NULL,
    start_time      TIME          NOT NULL,
    end_time        TIME          NOT NULL,
    is_available    TINYINT(1)    DEFAULT 1,
    notes           TEXT,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_sched_therapist (therapist_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_therapist_date (therapist_id, schedule_date),
    INDEX idx_date           (schedule_date)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: products (Sprint 6 - Inventory)
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    unit        VARCHAR(30)   NOT NULL COMMENT 'e.g. ml, gram, pcs',
    stock       DECIMAL(10,2) DEFAULT 0,
    min_stock   DECIMAL(10,2) DEFAULT 10 COMMENT 'Low stock threshold',
    description TEXT,
    is_active   TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name  (name),
    INDEX idx_stock (stock)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: product_usage (Sprint 6 - Inventory)
-- ============================================================
CREATE TABLE IF NOT EXISTS product_usage (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id  INT           NOT NULL,
    product_id      INT           NOT NULL,
    quantity_used   DECIMAL(10,2) NOT NULL,
    recorded_by     INT           NULL COMMENT 'Therapist user id',
    notes           TEXT,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_usage_reservation (reservation_id) REFERENCES reservations(id) ON UPDATE CASCADE,
    FOREIGN KEY fk_usage_product     (product_id)     REFERENCES products(id)     ON UPDATE CASCADE,
    FOREIGN KEY fk_usage_therapist   (recorded_by)    REFERENCES users(id)        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_reservation (reservation_id),
    INDEX idx_product     (product_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: stock_requests (Sprint 6 - Inventory)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT           NOT NULL,
    requested_qty   DECIMAL(10,2) NOT NULL,
    approved_qty    DECIMAL(10,2) NULL,
    status          ENUM('Pending','Disetujui','Ditolak') DEFAULT 'Pending',
    requested_by    INT           NULL,
    approved_by     INT           NULL,
    request_notes   TEXT,
    approval_notes  TEXT,
    requested_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    approved_at     TIMESTAMP     NULL,
    FOREIGN KEY fk_req_product  (product_id)  REFERENCES products(id) ON UPDATE CASCADE,
    FOREIGN KEY fk_req_requester(requested_by)REFERENCES users(id)    ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY fk_req_approver (approved_by) REFERENCES users(id)    ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default users (password: password123 - bcrypt hash)
INSERT INTO users (name, username, password, role) VALUES
('Admin Manager',    'manager',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager'),
('Sari Terapis',     'therapist',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'therapist'),
('Budi Kasir',       'cashier',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier'),
('Ani Purchasing',   'purchasing', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'purchasing'),
('Dini Accounting',  'accounting', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'accounting'),
('Rina Terapis',     'therapist2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'therapist');

-- Default services
INSERT INTO services (name, description, duration, price) VALUES
('Body Treatment - Regular',    'Perawatan tubuh komprehensif dengan bahan alami pilihan', 60,  250000),
('Body Treatment - Premium',    'Perawatan premium dengan bahan organik eksklusif',        90,  350000),
('Body Therapy - Relaksasi',    'Terapi relaksasi otot dengan teknik pijat Swedia',        60,  200000),
('Body Therapy - Deep Tissue',  'Pijat jaringan dalam untuk pemulihan intensif',           90,  300000),
('Aromatherapy Massage',        'Pijat aromatik dengan essential oil pilihan',             60,  225000),
('Hot Stone Massage',           'Terapi batu panas untuk relaksasi mendalam',              90,  375000),
('Facial Treatment',            'Perawatan wajah dengan masker alami',                    60,  200000),
('Scrub & Wrap',                'Lulur dan balutan tubuh untuk kulit halus',               90,  280000);

-- Default products
INSERT INTO products (name, unit, stock, min_stock) VALUES
('Minyak Esensial Lavender',   'ml',  500,  100),
('Scrub Kopi Organik',         'gram',1000, 200),
('Masker Lumpur',              'gram', 800, 150),
('Handuk Kecil',               'pcs',  50,  10),
('Handuk Besar',               'pcs',  30,  10),
('Minyak Kelapa',              'ml',  600, 100),
('Garam Himalaya',             'gram', 900, 200),
('Aromatherapy Oil Mix',       'ml',  400, 100);
