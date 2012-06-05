DROP DATABASE 531_cultural_iir;

CREATE DATABASE 531_cultural_iir;

USE 531_cultural_iir;

CREATE TABLE search_cache (
  `query` VARCHAR( 250 ) primary key NOT NULL,
  `retrieved` datetime NOT NULL,
  `json` LONGTEXT NOT NULL
);

CREATE TABLE user_actions (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `system` VARCHAR(6) NOT NULL,
  `action` VARCHAR(10) NOT NULL,
  `notes` TEXT NOT NULL,
  PRIMARY KEY( `id` )
);

