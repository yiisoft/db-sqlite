DROP TABLE IF EXISTS "order_with_null_fk";
CREATE TABLE "order_with_null_fk" (
  id INTEGER NOT NULL,
  customer_id INTEGER,
  created_at INTEGER NOT NULL,
  total decimal(10,0) NOT NULL,
  PRIMARY KEY (id)
);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (1, 1325282384, 110.0);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (2, 1325334482, 33.0);
INSERT INTO "order_with_null_fk" (customer_id, created_at, total) VALUES (2, 1325502201, 40.0);
