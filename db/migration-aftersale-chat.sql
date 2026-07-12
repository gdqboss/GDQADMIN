-- Migration: Add aftersale_messages table for customer-staff chat
-- Date: 2026-03-15

CREATE TABLE IF NOT EXISTS aftersale_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aftersale_id INT NOT NULL,
  sender_type ENUM('customer','staff') NOT NULL,
  sender_id INT NOT NULL,
  sender_name VARCHAR(50),
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aftersale_id) REFERENCES after_sale_records(id)
);

CREATE INDEX idx_as_msg_aftersale ON aftersale_messages(aftersale_id, created_at);
