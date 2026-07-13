-- db.sql — schema + seed data. Load once:
--   sudo mariadb < db.sql

CREATE DATABASE IF NOT EXISTS crypto_tracker;
USE crypto_tracker;

DROP TABLE IF EXISTS holdings;
DROP TABLE IF EXISTS coins;
DROP TABLE IF EXISTS users;

-- VULN: plaintext password storage — intentional for coursework
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,       -- plaintext, on purpose
    display_name  VARCHAR(100) DEFAULT '',     -- Stored XSS sink
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE coins (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    symbol  VARCHAR(10) NOT NULL,   -- BTC, ETH...
    name    VARCHAR(50) NOT NULL,
    price   DECIMAL(18,2) NOT NULL  -- manual/stored USD price
);

CREATE TABLE holdings (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NOT NULL,
    coin_id   INT NOT NULL,
    amount    DECIMAL(18,8) NOT NULL,
    buy_price DECIMAL(18,2) NOT NULL,
    notes     TEXT DEFAULT '',       -- Stored XSS sink
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coin_id) REFERENCES coins(id)
);

-- Seed coins
INSERT INTO coins (symbol, name, price) VALUES
 ('BTC','Bitcoin',65000.00),
 ('ETH','Ethereum',3200.00),
 ('SOL','Solana',150.00),
 ('ADA','Cardano',0.45),
 ('XRP','Ripple',0.60);

-- Seed users (plaintext passwords — for the SQLi dump demo)
INSERT INTO users (username, password, display_name) VALUES
 ('admin','admin123','Administrator'),
 ('alice','password1','Alice'),
 ('bob','qwerty','Bob');

-- Seed a couple of holdings so dashboards aren't empty
INSERT INTO holdings (user_id, coin_id, amount, buy_price, notes) VALUES
 (2, 1, 0.50000000, 60000.00, 'long term hold'),
 (2, 2, 5.00000000, 2800.00, 'staking'),
 (3, 3, 100.00000000, 120.00, 'moon');
