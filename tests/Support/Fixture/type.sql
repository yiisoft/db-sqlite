DROP TABLE IF EXISTS "type";
CREATE TABLE "type" (
  int_col INTEGER NOT NULL,
  int_col2 INTEGER DEFAULT '1',
  tinyint_col TINYINT(3) DEFAULT '1',
  smallint_col SMALLINT(1) DEFAULT '1',
  char_col char(100) NOT NULL,
  char_col2 varchar(100) DEFAULT 'something',
  char_col3 text,
  float_col double(4,3) NOT NULL,
  float_col2 double DEFAULT '1.23',
  blob_col blob,
  numeric_col decimal(5,2) DEFAULT '33.22',
  time timestamp NOT NULL DEFAULT '2002-01-01 00:00:00',
  bool_col tinyint(1) NOT NULL,
  bool_col2 tinyint(1) DEFAULT '1',
  ts_default TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
