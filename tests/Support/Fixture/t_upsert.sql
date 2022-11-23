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
