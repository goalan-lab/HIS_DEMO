-- HIS_DEMO MVP schema: 病人 / 掛號 / 批價 / 處方 / 藥品
CREATE DATABASE IF NOT EXISTS his_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE his_demo;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT '掛號', -- 掛號/批價/藥局/管理
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chart_no VARCHAR(20) NOT NULL UNIQUE COMMENT '病歷號',
  name VARCHAR(50) NOT NULL,
  id_no VARCHAR(20) NULL COMMENT '身分證',
  birth_date DATE NULL,
  gender ENUM('男','女','其他') DEFAULT '男',
  phone VARCHAR(30) NULL,
  address VARCHAR(200) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  dept VARCHAR(50) NOT NULL DEFAULT '家醫科',
  room VARCHAR(20) NULL
);

CREATE TABLE IF NOT EXISTS registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(20) NOT NULL UNIQUE COMMENT '掛號單號',
  patient_id INT NOT NULL,
  visit_date DATE NOT NULL,
  slot ENUM('早診','午診','晚診') NOT NULL DEFAULT '早診',
  dept VARCHAR(50) NOT NULL DEFAULT '家醫科',
  doctor_id INT NULL,
  status ENUM('掛號','看診中','待批價','待領藥','結案','退掛') NOT NULL DEFAULT '掛號',
  reg_fee INT NOT NULL DEFAULT 150,
  exam_fee INT NOT NULL DEFAULT 350,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

CREATE TABLE IF NOT EXISTS bills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_no VARCHAR(20) NOT NULL UNIQUE,
  reg_id INT NOT NULL UNIQUE,
  med_fee INT NOT NULL DEFAULT 0 COMMENT '藥費',
  total INT NOT NULL DEFAULT 0,
  paid TINYINT(1) NOT NULL DEFAULT 0,
  paid_at DATETIME NULL,
  cashier VARCHAR(50) NULL,
  voided TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否作廢(退費)',
  void_at DATETIME NULL,
  FOREIGN KEY (reg_id) REFERENCES registrations(id)
);

CREATE TABLE IF NOT EXISTS drugs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  spec VARCHAR(100) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT '顆',
  price INT NOT NULL DEFAULT 0 COMMENT '單價(元)',
  stock INT NOT NULL DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_id INT NOT NULL,
  drug_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  usage_note VARCHAR(200) NULL COMMENT '用法',
  dispensed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reg_id) REFERENCES registrations(id),
  FOREIGN KEY (drug_id) REFERENCES drugs(id)
);

CREATE TABLE IF NOT EXISTS refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_id INT NOT NULL,
  bill_id INT NULL,
  amount INT NOT NULL DEFAULT 0,
  reason VARCHAR(200) NULL,
  restock TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否回補庫存',
  created_by VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reg_id) REFERENCES registrations(id),
  FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- 預設帳號 admin / his1234
INSERT IGNORE INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$wH4m6o6o6o6o6o6o6o6o6e6J1Gx7vQ5qQ5qQ5qQ5qQ5qQ5qQ5qQ5q', '管理');

-- 預設醫師
INSERT IGNORE INTO doctors (id, name, dept, room) VALUES
(1, '王大明', '家醫科', '101'),
(2, '李小華', '內科', '102'),
(3, '陳美玲', '小兒科', '103');

-- 預設藥品
INSERT IGNORE INTO drugs (code, name, spec, unit, price, stock) VALUES
('D001', '普拿疼', '500mg', '錠', 5, 1000),
('D002', '感冒糖漿', '120ml', '瓶', 80, 200),
('D003', '胃藥', '20mg', '顆', 10, 500),
('D004', '抗生素', '250mg', '顆', 15, 300),
('D005', '眼藥水', '10ml', '瓶', 60, 150);
