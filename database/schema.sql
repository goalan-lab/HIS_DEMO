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

CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  weekday TINYINT NOT NULL COMMENT '0=日..6=六',
  slot ENUM('早診','午診','晚診') NOT NULL,
  room VARCHAR(20) NULL,
  quota INT NOT NULL DEFAULT 30 COMMENT '掛號限額',
  active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id),
  UNIQUE KEY uq_sched (doctor_id, weekday, slot)
);

CREATE TABLE IF NOT EXISTS registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(20) NOT NULL UNIQUE COMMENT '掛號單號',
  patient_id INT NOT NULL,
  visit_date DATE NOT NULL,
  slot ENUM('早診','午診','晚診') NOT NULL DEFAULT '早診',
  dept VARCHAR(50) NOT NULL DEFAULT '家醫科',
  doctor_id INT NULL,
  queue_no INT NULL COMMENT '診間號碼',
  called_at DATETIME NULL COMMENT '叫號時間',
  status ENUM('掛號','看診中','待批價','待領藥','結案','退掛') NOT NULL DEFAULT '掛號',
  reg_fee INT NOT NULL DEFAULT 150,
  exam_fee INT NOT NULL DEFAULT 350,
  ins_type ENUM('健保','自費','重大傷病','榮民','低收') NOT NULL DEFAULT '健保',
  copay INT NOT NULL DEFAULT 0 COMMENT '部分負擔',
  nhi_claim TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否申報健保',
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
  safety_stock INT NOT NULL DEFAULT 50,
  expiry DATE NULL,
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

CREATE TABLE IF NOT EXISTS stock_txns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  drug_id INT NOT NULL,
  txn_type ENUM('進貨','發藥','退藥回庫','盤點調整','報廢') NOT NULL,
  qty INT NOT NULL COMMENT '正=入庫 負=出庫',
  before_stock INT NOT NULL DEFAULT 0,
  after_stock INT NOT NULL DEFAULT 0,
  ref_type VARCHAR(30) NULL,
  ref_id INT NULL,
  note VARCHAR(200) NULL,
  created_by VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (drug_id) REFERENCES drugs(id)
);

CREATE TABLE IF NOT EXISTS stocktakes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  st_no VARCHAR(20) NOT NULL UNIQUE,
  status ENUM('盤點中','已入帳') NOT NULL DEFAULT '盤點中',
  created_by VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stocktake_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stocktake_id INT NOT NULL,
  drug_id INT NOT NULL,
  sys_qty INT NOT NULL DEFAULT 0,
  counted_qty INT NULL,
  diff INT NULL,
  FOREIGN KEY (stocktake_id) REFERENCES stocktakes(id),
  FOREIGN KEY (drug_id) REFERENCES drugs(id),
  UNIQUE KEY uq_st_item (stocktake_id, drug_id)
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

CREATE TABLE IF NOT EXISTS wards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE COMMENT '病房/護理站',
  bed_fee INT NOT NULL DEFAULT 1500 COMMENT '每日病床費'
);

CREATE TABLE IF NOT EXISTS beds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ward_id INT NOT NULL,
  bed_no VARCHAR(20) NOT NULL,
  status ENUM('空床','佔床','清潔中') NOT NULL DEFAULT '空床',
  UNIQUE KEY uq_bed (ward_id, bed_no),
  FOREIGN KEY (ward_id) REFERENCES wards(id)
);

CREATE TABLE IF NOT EXISTS admissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adm_no VARCHAR(20) NOT NULL UNIQUE COMMENT '住院號',
  patient_id INT NOT NULL,
  bed_id INT NULL,
  doctor_id INT NULL,
  admit_date DATE NOT NULL,
  discharge_date DATE NULL,
  status ENUM('住院中','已出院') NOT NULL DEFAULT '住院中',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (bed_id) REFERENCES beds(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

CREATE TABLE IF NOT EXISTS ipd_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admission_id INT NOT NULL,
  order_type ENUM('藥品','檢查','處置','護理') NOT NULL DEFAULT '藥品',
  drug_id INT NULL,
  item_name VARCHAR(100) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  price INT NOT NULL DEFAULT 0,
  note VARCHAR(200) NULL,
  status ENUM('開立','執行中','完成','取消') NOT NULL DEFAULT '開立',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admission_id) REFERENCES admissions(id),
  FOREIGN KEY (drug_id) REFERENCES drugs(id)
);

CREATE TABLE IF NOT EXISTS nursing_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admission_id INT NOT NULL,
  note_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vitals VARCHAR(200) NULL COMMENT '體溫/血壓/脈搏等',
  content TEXT NULL,
  nurse VARCHAR(50) NULL,
  FOREIGN KEY (admission_id) REFERENCES admissions(id)
);

CREATE TABLE IF NOT EXISTS op_rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE COMMENT '刀房',
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS surgeries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  op_no VARCHAR(20) NOT NULL UNIQUE COMMENT '手術號',
  admission_id INT NULL COMMENT '可掛住院或直接排門診病人',
  reg_id INT NULL COMMENT '門診掛號(手術費併批價)',
  patient_id INT NOT NULL,
  doctor_id INT NULL COMMENT '主刀',
  op_room_id INT NULL,
  op_name VARCHAR(100) NOT NULL COMMENT '術式',
  op_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  anesthesia ENUM('局部','半身','全身','無') NOT NULL DEFAULT '局部',
  fee INT NOT NULL DEFAULT 0 COMMENT '手術費',
  status ENUM('排程','術前準備','手術中','恢復中','完成','取消') NOT NULL DEFAULT '排程',
  note VARCHAR(200) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admission_id) REFERENCES admissions(id),
  FOREIGN KEY (reg_id) REFERENCES registrations(id),
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id),
  FOREIGN KEY (op_room_id) REFERENCES op_rooms(id)
);

CREATE TABLE IF NOT EXISTS exam_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  category ENUM('檢驗','檢查') NOT NULL DEFAULT '檢驗',
  name VARCHAR(100) NOT NULL,
  price INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS exam_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(20) NOT NULL UNIQUE,
  reg_id INT NULL,
  admission_id INT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  price INT NOT NULL DEFAULT 0,
  status ENUM('開立','檢體採集','檢驗中','已發報告','取消') NOT NULL DEFAULT '開立',
  ordered_by VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reg_id) REFERENCES registrations(id),
  FOREIGN KEY (admission_id) REFERENCES admissions(id),
  FOREIGN KEY (item_id) REFERENCES exam_items(id)
);

CREATE TABLE IF NOT EXISTS exam_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  result_text TEXT NULL,
  abnormal TINYINT(1) NOT NULL DEFAULT 0,
  reported_by VARCHAR(50) NULL,
  reported_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES exam_orders(id)
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
