DROP TABLE IF EXISTS "profile";
CREATE TABLE "profile" (
  id INTEGER NOT NULL,
  description varchar(128) NOT NULL,
  PRIMARY KEY (id)
);
INSERT INTO "profile" (description) VALUES ('profile customer 1');
INSERT INTO "profile" (description) VALUES ('profile customer 3');
