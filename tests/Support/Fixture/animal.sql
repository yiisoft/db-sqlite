DROP TABLE IF EXISTS "animal";
CREATE TABLE "animal" (
  id INTEGER NOT NULL,
  type VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
);
INSERT INTO "animal" ("type") VALUES ('yiiunit\data\ar\Cat');
INSERT INTO "animal" ("type") VALUES ('yiiunit\data\ar\Dog');
DROP VIEW IF EXISTS "animal_view";
CREATE VIEW "animal_view" AS SELECT * FROM "animal";
