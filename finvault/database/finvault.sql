-- =====================================================
-- FinVault - Modern Digital Banking Platform
-- MySQL Schema (educational simulation - no real money)
-- =====================================================
CREATE DATABASE IF NOT EXISTS finvault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finvault;

-- ---------------- USERS ----------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  dob DATE NULL,
  gender ENUM('male','female','other') NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mobile VARCHAR(15) NOT NULL,
  address TEXT NULL,
  city VARCHAR(60) NULL,
  state VARCHAR(60) NULL,
  pan_number VARCHAR(10) NULL,
  aadhaar_number VARCHAR(12) NULL,
  profile_photo VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_mobile (mobile),
  INDEX idx_users_status (status),
  INDEX idx_users_role (role),
  INDEX idx_users_created (created_at)
) ENGINE=InnoDB;

-- ---------------- ACCOUNTS ----------------
CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  account_number VARCHAR(12) NOT NULL UNIQUE,
  account_type ENUM('savings','current') NOT NULL DEFAULT 'savings',
  balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_acc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_acc_status (status)
) ENGINE=InnoDB;

-- ---------------- TRANSACTIONS ----------------
CREATE TABLE transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_ref VARCHAR(30) NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  counterparty_account VARCHAR(12) NULL,
  counterparty_name VARCHAR(100) NULL,
  type ENUM('deposit','withdrawal','transfer_in','transfer_out') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  balance_after DECIMAL(15,2) NOT NULL,
  description VARCHAR(255) NULL,
  channel ENUM('internal','qr','admin') NOT NULL DEFAULT 'internal',
  status ENUM('success','failed','pending') NOT NULL DEFAULT 'success',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_txn_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_txn_ref (txn_ref),
  INDEX idx_txn_created (created_at),
  INDEX idx_txn_type (type)
) ENGINE=InnoDB;

-- ---------------- BENEFICIARIES ----------------
CREATE TABLE beneficiaries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  account_number VARCHAR(12) NOT NULL,
  email VARCHAR(150) NULL,
  mobile VARCHAR(15) NULL,
  bank_name VARCHAR(100) NOT NULL DEFAULT 'FinVault',
  verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ben_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_ben_name (name),
  INDEX idx_ben_account (account_number)
) ENGINE=InnoDB;

-- ---------------- LOANS ----------------
CREATE TABLE loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  loan_ref VARCHAR(20) NOT NULL UNIQUE,
  loan_type ENUM('personal','education','business') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  tenure_months INT NOT NULL,
  interest_rate DECIMAL(5,2) NOT NULL,
  emi DECIMAL(15,2) NOT NULL,
  purpose VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_remarks VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_loan_status (status)
) ENGINE=InnoDB;

-- ---------------- CARDS ----------------
CREATE TABLE cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  card_type ENUM('debit','credit') NOT NULL,
  card_number VARCHAR(16) NULL,
  status ENUM('requested','active','blocked','rejected') NOT NULL DEFAULT 'requested',
  admin_remarks VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_card_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_card_status (status)
) ENGINE=InnoDB;

-- ---------------- KYC DOCUMENTS ----------------
CREATE TABLE kyc_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  doc_type ENUM('aadhaar','pan','photo') NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected','reupload') NOT NULL DEFAULT 'pending',
  admin_remarks VARCHAR(255) NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  CONSTRAINT fk_kyc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_kyc_user_doc (user_id, doc_type),
  INDEX idx_kyc_status (status)
) ENGINE=InnoDB;

-- ---------------- NOTIFICATIONS ----------------
CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  message VARCHAR(500) NOT NULL,
  type ENUM('login','transfer','loan','card','kyc','security','general') NOT NULL DEFAULT 'general',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------- AUDIT LOGS ----------------
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  details VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- ---------------- OTP CODES ----------------
CREATE TABLE otp_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  purpose ENUM('email_verify','password_reset') NOT NULL DEFAULT 'email_verify',
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_user (user_id, purpose, used)
) ENGINE=InnoDB;

-- ---------------- PASSWORD RESETS ----------------
CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(16) NOT NULL UNIQUE,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------- REMEMBER ME TOKENS ----------------
CREATE TABLE remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(16) NOT NULL UNIQUE,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- SEED: default administrator account
-- Email:    sakib1@gmail.com
-- Password: password
-- =====================================================

INSERT INTO users (
  full_name,
  email,
  mobile,
  password_hash,
  role,
  status,
  email_verified
)
VALUES (
  'FinVault Administrator',
  'sakib1@gmail.com',
  '9999999999',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin',
  'active',
  1
);