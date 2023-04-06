
CREATE TABLE snmp_mib_file (
  mib_file_checksum VARBINARY(20) NOT NULL,
  mib_checksum VARBINARY(20) DEFAULT NULL,
  content MEDIUMTEXT CHARACTER SET binary COLLATE binary NOT NULL,
  file_size INT NOT NULL,
  parsed_mib MEDIUMTEXT DEFAULT NULL,
  last_processing_error TEXT DEFAULT NULL,
  PRIMARY KEY (mib_file_checksum),
  INDEX mib_reference (mib_checksum),
  INDEX idx_join (mib_checksum, mib_file_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE snmp_mib_upload (
  uuid VARBINARY(16) NOT NULL,
  mib_file_checksum VARBINARY(20) NOT NULL,
  username VARCHAR(255) NOT NULL,
  client_ip VARCHAR(45) NULL DEFAULT NULL,
  ts_upload BIGINT(20) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  PRIMARY KEY(uuid),
  INDEX idx_file_checksum (mib_file_checksum)
  -- no const
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE snmp_mib (
  mib_checksum VARBINARY(20),
  mib_name VARCHAR(128) NOT NULL, -- SNMP-TARGET-MIB
  smi_version TINYINT DEFAULT NULL, --  1, 2
  short_name VARCHAR(128), -- (IDENTITY) snmpTargetMIB
  last_updated VARCHAR(32) NOT NULL,
  ts_last_updated BIGINT(20) NOT NULL,
  organization VARCHAR(255) DEFAULT NULL,
  contact_info MEDIUMTEXT,
  description MEDIUMTEXT,
  PRIMARY KEY (mib_checksum),
  INDEX mib_name (mib_name),
  INDEX idx_join_sort (mib_checksum, mib_name),
  INDEX idx_organization (organization, mib_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE snmp_mib_stats (
  ts_generated BIGINT(20) NOT NULL,
  cnt_nodes_total BIGINT(20) NOT NULL,
  cnt_resolved_nodes BIGINT(20) NOT NULL,
  cnt_unresolved_nodes BIGINT(20) NOT NULL,
  cnt_files_total BIGINT(20) NOT NULL,
  file_size_total BIGINT(20) NOT NULL,
  cnt_files_parsed BIGINT(20) NOT NULL,
  cnt_files_failed BIGINT(20) NOT NULL,
  cnt_files_pending BIGINT(20) NOT NULL,
  cnt_duplicate_files BIGINT(20) NOT NULL,
  db_size_total BIGINT(20) NOT NULL,
  db_size_data BIGINT(20) NOT NULL,
  db_size_index BIGINT(20) NOT NULL,
  PRIMARY KEY (ts_generated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE snmp_mib_revision (
  mib_checksum VARBINARY(20) NOT NULL,
  revision VARCHAR(32) NOT NULL,
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE snmp_mib_import (
  mib_checksum VARBINARY(20) NOT NULL,
  source_mib_name VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  object_name VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (mib_checksum, source_mib_name, object_name),
  INDEX idx_search (source_mib_name, object_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE snmp_mib_node (
  mib_checksum VARBINARY(20) NOT NULL,
  object_name VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  parent_name VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  relative_oid INT UNSIGNED NOT NULL, -- TODO: Check this!
  macro VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  oid VARCHAR(1280) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, -- max 128 segments, limit for each of them? 2^28-1 ?? -> 9 chars + dot. 2^32-1 -> 10 chars + dot
  oid_uuid VARBINARY(16) DEFAULT NULL, -- uuid5(NS_OID, oid).
  depth INT(10) UNSIGNED DEFAULT NULL,
--  macro ENUM ( 'MODULE-IDENTITY', 'OBJECT-IDENTITY', 'OBJECT-TYPE', 'NOTIFICATION-TYPE') NOT NULL,

  description TEXT NULL DEFAULT NULL,
  units TEXT NULL DEFAULT NULL,
  access VARCHAR(32) NULL DEFAULT NULL, -- ENUM('read-only', 'read-write', 'not-accessible', 'write-only', 'read-create', 'accessible-for-notify') NULL DEFAULT NULL,
  status VARCHAR(32) NULL DEFAULT NULL, -- ENUM('current', 'deprecated', 'mandatory', 'obsolete', 'optional') NULL DEFAULT NULL,
  default_value VARCHAR(255) NULL DEFAULT NULL,
  reference TEXT NULL DEFAULT NULL,
  display_hint TEXT NULL DEFAULT NULL, -- nirgends vorhanden?
  syntax TEXT NULL DEFAULT NULL, -- syntax->type herausholen?
  table_index TEXT NULL DEFAULT NULL, -- nur bei macro = 'OBJECT-TYPE'. Hat zudem überall syntax->type: someObjectName
  items TEXT NULL DEFAULT NULL, -- nirgends vorhanden?
  objects TEXT NULL DEFAULT NULL, -- nur wenn macro = 'OBJECT-GROUP'
  -- max_access () --> TODO->enum, failsafe, mit mapping für v1 ?
  -- "SEQUENCE OF X"
  -- data_type

  PRIMARY KEY (mib_checksum, object_name),
  INDEX idx_oid_sorted_by_mib (mib_checksum, oid(128))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;
