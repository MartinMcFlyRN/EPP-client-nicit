-- Schema MySQL/MariaDB per EPP .it web client
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','operator','readonly') NOT NULL DEFAULT 'operator',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(64) NOT NULL PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  updated_by VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS epp_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL,
  user_id INT UNSIGNED NULL,
  username VARCHAR(64) NULL,
  command VARCHAR(32) NOT NULL,
  object VARCHAR(255) NULL,
  cltrid VARCHAR(64) NULL,
  svtrid VARCHAR(64) NULL,
  result_code SMALLINT NULL,
  result_msg VARCHAR(255) NULL,
  duration_ms INT NULL,
  error TEXT NULL,
  request_xml MEDIUMTEXT NULL,
  response_xml MEDIUMTEXT NULL,
  KEY idx_created (created_at),
  KEY idx_object (object(64)),
  KEY idx_command (command)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cache locale dei domini (aggiornata a ogni info/create)
CREATE TABLE IF NOT EXISTS domains (
  name VARCHAR(255) NOT NULL PRIMARY KEY,
  roid VARCHAR(64) NULL,
  registrant VARCHAR(64) NULL,
  status VARCHAR(255) NULL,
  cl_id VARCHAR(64) NULL,
  cr_date DATETIME NULL,
  ex_date DATETIME NULL,
  updated_at DATETIME NOT NULL,
  info_json MEDIUMTEXT NULL,
  KEY idx_ex (ex_date),
  KEY idx_registrant (registrant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NULL,
  org VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  is_registrant TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  info_json MEDIUMTEXT NULL,
  KEY idx_name (name(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS poll_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  msg_id VARCHAR(64) NOT NULL,
  q_date VARCHAR(40) NULL,
  msg VARCHAR(1024) NULL,
  raw_xml MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  acked_at DATETIME NULL,
  acked_by VARCHAR(64) NULL,
  UNIQUE KEY uq_msg (msg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
