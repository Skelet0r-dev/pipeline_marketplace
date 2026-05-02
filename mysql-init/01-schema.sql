-- ============================================================
-- Database Setup
-- ============================================================
CREATE DATABASE IF NOT EXISTS pipeline_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pipeline_db;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS USERS (
    USER_ID     INT AUTO_INCREMENT PRIMARY KEY,
    FIRST_NAME  TEXT,
    LAST_NAME   TEXT,
    STD_NUM     INT,
    CYS         VARCHAR(255),
    SEX         VARCHAR(50),
    USERNAME    VARCHAR(255),
    EMAIL       VARCHAR(255),
    PASSWORD    VARCHAR(255)
);

-- ============================================================
-- ADMIN_LOGIN
-- ============================================================
CREATE TABLE IF NOT EXISTS ADMIN_LOGIN (
    ADMIN_NUMBER INT PRIMARY KEY,
    USERNAME     VARCHAR(255),
    PASSWORD     VARCHAR(255)
);

-- ============================================================
-- LISTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS LISTINGS (
    LISTING_ID      INT AUTO_INCREMENT PRIMARY KEY,
    USER_ID         INT NOT NULL,
    TITLE           VARCHAR(100) NOT NULL,
    DESCRIPTION     TEXT,
    PRICE           DECIMAL(10,2) NOT NULL,
    CATEGORY        VARCHAR(50) NOT NULL,
    `CONDITION`     VARCHAR(20) NOT NULL,
    STATUS          VARCHAR(20) NOT NULL DEFAULT 'Available',
    MEETUP_SPOT     VARCHAR(100),
    PAYMENT_METHOD  VARCHAR(100),
    DATE_POSTED     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_user FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID)
);

-- ============================================================
-- LISTING_IMG
-- ============================================================
CREATE TABLE IF NOT EXISTS LISTING_IMG (
    IMG_ID      INT AUTO_INCREMENT PRIMARY KEY,
    LISTING_ID  INT NOT NULL,
    FILE_PATH   VARCHAR(500) NOT NULL,
    IS_PRIMARY  TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_listing_img FOREIGN KEY (LISTING_ID) REFERENCES LISTINGS(LISTING_ID)
);

-- ============================================================
-- USER_IMG
-- ============================================================
CREATE TABLE IF NOT EXISTS USER_IMG (
    IMG_ID    INT AUTO_INCREMENT PRIMARY KEY,
    IMG_NAME  VARCHAR(255),
    FILE_PATH VARCHAR(255),
    USER_ID   INT
);

-- ============================================================
-- Sample admin account (password: admin123 — change in prod!)
-- ============================================================
INSERT INTO ADMIN_LOGIN (ADMIN_NUMBER, USERNAME, PASSWORD)
VALUES (1, 'admin', 'admin123')
ON DUPLICATE KEY UPDATE USERNAME = VALUES(USERNAME);
