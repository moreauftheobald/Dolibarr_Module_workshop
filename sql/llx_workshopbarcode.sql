CREATE TABLE llx_workshopbarcode (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  date_creation datetime DEFAULT NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  label varchar(255) DEFAULT NULL,
  code varchar(255) NOT NULL DEFAULT '',
  entity integer NOT NULL DEFAULT 1
) ENGINE=innodb;
