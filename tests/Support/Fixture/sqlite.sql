DROP TABLE IF EXISTS "composite_fk";
DROP TABLE IF EXISTS "order_item";
DROP TABLE IF EXISTS "order_item_with_null_fk";
DROP TABLE IF EXISTS "item";
DROP TABLE IF EXISTS "order";
DROP TABLE IF EXISTS "order_with_null_fk";
DROP TABLE IF EXISTS "category";
DROP TABLE IF EXISTS "customer";
DROP TABLE IF EXISTS "profile";
DROP TABLE IF EXISTS "quoter";
DROP TABLE IF EXISTS "type";
DROP TABLE IF EXISTS "type_bit";
DROP TABLE IF EXISTS "null_values";
DROP TABLE IF EXISTS "negative_default_values";
DROP TABLE IF EXISTS "animal";
DROP TABLE IF EXISTS "default_pk";
DROP TABLE IF EXISTS "notauto_pk";
DROP TABLE IF EXISTS "timestamp_default";
DROP TABLE IF EXISTS "json_type";
DROP VIEW IF EXISTS "animal_view";
DROP TABLE IF EXISTS "T_constraints_4";
DROP TABLE IF EXISTS "T_constraints_3";
DROP TABLE IF EXISTS "T_constraints_2";
DROP TABLE IF EXISTS "T_constraints_1";
DROP TABLE IF EXISTS "T_upsert";
DROP TABLE IF EXISTS "T_upsert_1";
DROP TABLE IF EXISTS "T_constraints_check";
DROP TABLE IF EXISTS "foreign_keys_parent";
DROP TABLE IF EXISTS "foreign_keys_child";
DROP TABLE IF EXISTS "json_type";

CREATE TABLE "profile" (
  id INTEGER NOT NULL,
  description varchar(128) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "quoter" (
  id INTEGER NOT NULL,
  name varchar(16) NOT NULL,
  description varchar(128) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "customer" (
  id INTEGER NOT NULL,
  email varchar(128) NOT NULL,
  name varchar(128),
  address text,
  status INTEGER DEFAULT 0,
  profile_id INTEGER,
  PRIMARY KEY (id)
);

CREATE TABLE "category" (
  id INTEGER NOT NULL,
  name varchar(128) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "item" (
  id INTEGER NOT NULL,
  name varchar(128) NOT NULL,
  category_id INTEGER NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "order" (
  id INTEGER NOT NULL,
  customer_id INTEGER NOT NULL,
  created_at INTEGER NOT NULL,
  total decimal(10,0) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "order_with_null_fk" (
  id INTEGER NOT NULL,
  customer_id INTEGER,
  created_at INTEGER NOT NULL,
  total decimal(10,0) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "order_item" (
  order_id INTEGER NOT NULL,
  item_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL,
  subtotal decimal(10,0) NOT NULL,
  PRIMARY KEY (order_id, item_id)
);

CREATE TABLE "order_item_with_null_fk" (
  order_id INTEGER,
  item_id INTEGER,
  quantity INTEGER NOT NULL,
  subtotal decimal(10,0) NOT NULL
);

CREATE TABLE "composite_fk" (
  id int(11) NOT NULL,
  order_id int(11) NOT NULL,
  item_id int(11) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT FK_composite_fk_order_item FOREIGN KEY (order_id, item_id) REFERENCES "order_item" (order_id, item_id) ON DELETE CASCADE
);

CREATE TABLE "null_values" (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  var1 INTEGER UNSIGNED,
  var2 INTEGER,
  var3 INTEGER DEFAULT NULL,
  stringcol VARCHAR(32) DEFAULT NULL
);

CREATE TABLE "negative_default_values" (
  tinyint_col tinyint default '-123',
  smallint_col integer default '-123',
  int_col integer default '-123',
  bigint_col integer default '-123',
  float_col double default '-12345.6789',
  numeric_col decimal(5,2) default '-33.22'
);

CREATE TABLE "type" (
  int_col INTEGER NOT NULL,
  int_col2 INTEGER DEFAULT '1',
  tinyint_col TINYINT(3) DEFAULT '1',
  smallint_col SMALLINT(1) DEFAULT '1',
  char_col char(100) NOT NULL,
  char_col2 varchar(100) DEFAULT 'something"',
  char_col3 text,
  float_col double(4,3) NOT NULL,
  float_col2 double DEFAULT '1.23',
  blob_col blob,
  numeric_col decimal(5,2) DEFAULT '33.22',
  timestamp_col timestamp NOT NULL DEFAULT '2002-01-01 00:00:00',
  timestamp_default TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bool_col tinyint(1) NOT NULL,
  bool_col2 tinyint(1) DEFAULT '1',
  bit_col BIT(8) NOT NULL DEFAULT 130, -- 0b1000_0010
  json_col json NOT NULL DEFAULT '{"number":10}',
  json_text_col text CHECK(json_text_col IS NULL OR json_valid(json_text_col)) -- for STRICT table
);

CREATE TABLE "type_bit" (
  bit_col_1 bit(1) NOT NULL,
  bit_col_2 bit(1) DEFAULT '1',
  bit_col_3 bit(32) NOT NULL,
  bit_col_4 bit(32) DEFAULT '1',
  bit_col_5 bit(64) NOT NULL,
  bit_col_6 bit(64) DEFAULT '1'
);

CREATE TABLE "animal" (
  id INTEGER NOT NULL,
  type VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "default_pk" (
  id INTEGER NOT NULL DEFAULT 5,
  type VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE "notauto_pk" (
  id_1 INTEGER,
  id_2 DECIMAL(5,2),
  type VARCHAR(255) NOT NULL,
  PRIMARY KEY (id_1, id_2)
);

CREATE TABLE "timestamp_default" (
  id INTEGER PRIMARY KEY,
  text_col TEXT NOT NULL DEFAULT 'CURRENT_TIMESTAMP',
  timestamp_text TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  time_text TEXT NOT NULL DEFAULT CURRENT_TIME,
  date_text TEXT NOT NULL DEFAULT CURRENT_DATE
); -- STRICT

CREATE TABLE "json_type" (
  id INTEGER PRIMARY KEY,
  json_col JSON
);

CREATE VIEW "animal_view" AS SELECT * FROM "animal";

INSERT INTO "animal" ("type") VALUES ('yiiunit\data\ar\Cat');
INSERT INTO "animal" ("type") VALUES ('yiiunit\data\ar\Dog');

INSERT INTO "profile" (description) VALUES ('profile customer 1');
INSERT INTO "profile" (description) VALUES ('profile customer 3');

INSERT INTO "customer" (email, name, address, status, profile_id) VALUES ('user1@example.com', 'user1', 'address1', 1, 1);
INSERT INTO "customer" (email, name, address, status) VALUES ('user2@example.com', 'user2', 'address2', 1);
INSERT INTO "customer" (email, name, address, status, profile_id) VALUES ('user3@example.com', 'user3', 'address3', 2, 2);

INSERT INTO "category" (name) VALUES ('Books');
INSERT INTO "category" (name) VALUES ('Movies');

INSERT INTO "item" (name, category_id) VALUES ('Agile Web Application Development with Yii1.1 and PHP5', 1);
INSERT INTO "item" (name, category_id) VALUES ('Yii 1.1 Application Development Cookbook', 1);
INSERT INTO "item" (name, category_id) VALUES ('Ice Age', 2);
INSERT INTO "item" (name, category_id) VALUES ('Toy Story', 2);
INSERT INTO "item" (name, category_id) VALUES ('Cars', 2);

INSERT INTO "order" (customer_id, created_at, total) VALUES (1, 1325282384, 110.0);
INSERT INTO "order" (customer_id, created_at, total) VALUES (2, 1325334482, 33.0);
INSERT INTO "order" (customer_id, created_at, total) VALUES (2, 1325502201, 40.0);

INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (1, 1325282384, 110.0);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (2, 1325334482, 33.0);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (2, 1325502201, 40.0);

INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (1, 1, 1, 30.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (1, 2, 2, 40.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (2, 4, 1, 10.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (2, 5, 1, 15.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (2, 3, 1, 8.0);
INSERT INTO "order_item" (order_id, item_id, quantity, subtotal) VALUES (3, 2, 1, 40.0);

INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (1, 1, 1, 30.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (1, 2, 2, 40.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (2, 4, 1, 10.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (2, 5, 1, 15.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (2, 3, 1, 8.0);
INSERT INTO "order_item_with_null_fk" (order_id, item_id, quantity, subtotal) VALUES (3, 2, 1, 40.0);

INSERT INTO "json_type" (json_col) VALUES (null);
INSERT INTO "json_type" (json_col) VALUES ('[]');
INSERT INTO "json_type" (json_col) VALUES ('[1,2,3,null]');
INSERT INTO "json_type" (json_col) VALUES ('[3,4,5]');

/* bit test, see https://github.com/yiisoft/yii2/issues/9006 */

DROP TABLE IF EXISTS "bit_values";

CREATE TABLE "bit_values" (
  id INTEGER NOT NULL,
  val BOOLEAN NOT NULL CHECK (val IN (0,1)),
  PRIMARY KEY (id)
);

INSERT INTO "bit_values" (id, val) VALUES (1, 0);
INSERT INTO "bit_values" (id, val) VALUES (2, 1);

CREATE TABLE "T_constraints_1"
(
    "C_id" INT NOT NULL PRIMARY KEY,
    "C_not_null" INT NOT NULL,
    "C_check" VARCHAR(255) NULL CHECK ("C_check" <> ''),
    "C_unique" INT NOT NULL,
    "C_default" INT NOT NULL DEFAULT 0,
    CONSTRAINT "CN_unique" UNIQUE ("C_unique")
);

CREATE TABLE "T_constraints_2"
(
    "C_id_1" INT NOT NULL,
    "C_id_2" INT NOT NULL,
    "C_index_1" INT NULL,
    "C_index_2_1" INT NULL,
    "C_index_2_2" INT NULL,
    CONSTRAINT "CN_pk" PRIMARY KEY ("C_id_1", "C_id_2"),
    CONSTRAINT "CN_constraints_2_multi" UNIQUE ("C_index_2_1", "C_index_2_2")
);

CREATE INDEX "CN_constraints_2_single" ON "T_constraints_2" ("C_index_1");

CREATE TABLE "T_constraints_3"
(
    "C_id" INT NOT NULL,
    "C_fk_id_1" INT NOT NULL,
    "C_fk_id_2" INT NOT NULL,
    CONSTRAINT "CN_constraints_3" FOREIGN KEY ("C_fk_id_1", "C_fk_id_2") REFERENCES "T_constraints_2" ("C_id_1", "C_id_2") ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE "T_constraints_4"
(
    "C_id" INT NOT NULL PRIMARY KEY,
    "C_col_1" INT NULL,
    "C_col_2" INT NOT NULL,
    CONSTRAINT "CN_constraints_4" UNIQUE ("C_col_1", "C_col_2")
);

CREATE TABLE "T_upsert"
(
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "ts" INT NULL,
    "email" VARCHAR(128) NOT NULL UNIQUE,
    "recovery_email" VARCHAR(128) NULL,
    "address" TEXT NULL,
    "status" SMALLINT NOT NULL DEFAULT 0,
    "orders" INT NOT NULL DEFAULT 0,
    "profile_id" INT NULL,
    UNIQUE ("email", "recovery_email")
);

CREATE TABLE "T_upsert_1"
(
    "a" INTEGER NOT NULL PRIMARY KEY
);

CREATE TABLE "T_constraints_check"
(
    "C_id" INT NOT NULL PRIMARY KEY,
    "C_check_1" INT NOT NULL CHECK ("C_check_1" > 0),
    "C_check_2" INT NOT NULL CHECK ("C_check_2" > 0),
    CONSTRAINT "CN_constraints_check" CHECK ("C_check_1" > "C_check_2")
);

CREATE TABLE foreign_keys_parent
(
    a INTEGER,
    b INTEGER,
    c INTEGER,
    PRIMARY KEY(a, b),
    UNIQUE (b, c)
);

CREATE TABLE foreign_keys_child
(
    x INTEGER,
    y INTEGER,
    z INTEGER,
    FOREIGN KEY(x, y) REFERENCES foreign_keys_parent,
    FOREIGN KEY(y, z) REFERENCES foreign_keys_parent(b, c)
);
