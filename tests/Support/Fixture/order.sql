DROP TABLE IF EXISTS "order";
CREATE TABLE "order" (
  id INTEGER NOT NULL,
  customer_id INTEGER NOT NULL,
  created_at INTEGER NOT NULL,
  total decimal(10,0) NOT NULL,
  PRIMARY KEY (id)
);
INSERT INTO "order" (customer_id, created_at, total) VALUES (1, 1325282384, 110.0);
INSERT INTO "order" (customer_id, created_at, total) VALUES (2, 1325334482, 33.0);
INSERT INTO "order" (customer_id, created_at, total) VALUES (2, 1325502201, 40.0);
