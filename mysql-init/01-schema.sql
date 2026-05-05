-- ============================================================
-- Database Setup
-- ============================================================
CREATE DATABASE IF NOT EXISTS pipeline_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pipeline_db;

-- Drop tables in reverse order to respect foreign key constraints
DROP TABLE IF EXISTS LISTING_COMMENTS;
DROP TABLE IF EXISTS LISTING_LIKES;
DROP TABLE IF EXISTS USER_IMG;
DROP TABLE IF EXISTS LISTING_IMG;
DROP TABLE IF EXISTS LISTINGS;
DROP TABLE IF EXISTS ADMIN_LOGIN;
DROP TABLE IF EXISTS USERS;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE USERS (
    USER_ID INT AUTO_INCREMENT PRIMARY KEY,
    FIRST_NAME TEXT NULL,
    LAST_NAME TEXT NULL,
    STD_NUM INT NULL,
    COLLEGE VARCHAR(255) NOT NULL,
    DEPARTMENT VARCHAR(255) NOT NULL,
    SECTION VARCHAR(50) NOT NULL,
    SEX VARCHAR(50) NULL,
    USERNAME VARCHAR(255) NULL,
    EMAIL VARCHAR(255) NULL,
    PASSWORD VARCHAR(255) NULL,
    DATE_REGISTERED DATE NOT NULL
);

-- ============================================================
-- ADMIN_LOGIN
-- ============================================================
CREATE TABLE ADMIN_LOGIN (
    ADMIN_NUMBER INT PRIMARY KEY,
    USERNAME VARCHAR(255),
    PASSWORD VARCHAR(255)
);

-- ============================================================
-- LISTINGS
-- ============================================================
CREATE TABLE LISTINGS (
    LISTING_ID INT AUTO_INCREMENT PRIMARY KEY,
    USER_ID INT NOT NULL,
    TITLE VARCHAR(100) NOT NULL,
    DESCRIPTION TEXT,
    PRICE DECIMAL(10,2) NOT NULL,
    CATEGORY VARCHAR(50) NOT NULL,
    `CONDITION` VARCHAR(20) NOT NULL,
    STATUS VARCHAR(20) NOT NULL DEFAULT 'Available',
    MEETUP_SPOT VARCHAR(100),
    PAYMENT_METHOD VARCHAR(100),
    DATE_POSTED DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT listings_user_fk FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID)
);

-- ============================================================
-- LISTING_IMG
-- ============================================================
CREATE TABLE LISTING_IMG (
    IMG_ID INT AUTO_INCREMENT PRIMARY KEY,
    LISTING_ID INT NOT NULL,
    FILE_PATH VARCHAR(500) NOT NULL,
    IS_PRIMARY TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT listing_img_listing_fk FOREIGN KEY (LISTING_ID) REFERENCES LISTINGS(LISTING_ID)
);

-- ============================================================
-- USER_IMG
-- ============================================================
CREATE TABLE USER_IMG (
    IMG_ID INT AUTO_INCREMENT PRIMARY KEY,
    IMG_NAME VARCHAR(255),
    FILE_PATH VARCHAR(255),
    USER_ID INT,
    CONSTRAINT fk_user_img_user FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID)
);

-- ============================================================
-- LISTING_LIKES
-- ============================================================
CREATE TABLE LISTING_LIKES (
    LIKE_ID INT AUTO_INCREMENT PRIMARY KEY,
    LISTING_ID INT NOT NULL,
    USER_ID INT NOT NULL,
    CREATED_AT DATE NOT NULL,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID),
    FOREIGN KEY (LISTING_ID) REFERENCES LISTINGS(LISTING_ID)
);

-- ============================================================
-- LISTING_COMMENTS
-- ============================================================
CREATE TABLE LISTING_COMMENTS (
    COMMENT_ID INT AUTO_INCREMENT PRIMARY KEY,
    LISTING_ID INT NOT NULL,
    USER_ID INT NOT NULL,
    COMMENT_TEXT TEXT NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (LISTING_ID) REFERENCES LISTINGS(LISTING_ID),
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID)
);

-- ============================================================
-- Sample admin account (password: admin123 — change in prod!)
-- ============================================================
INSERT INTO ADMIN_LOGIN (ADMIN_NUMBER, USERNAME, PASSWORD)
VALUES (1, 'admin', 'admin123')
ON DUPLICATE KEY UPDATE USERNAME = VALUES(USERNAME);
