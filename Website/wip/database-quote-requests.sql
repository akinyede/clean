-- SQL migration to add quote_requests table
-- Run this script to enable quote request storage

CREATE TABLE IF NOT EXISTS `quote_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `property_type` varchar(50) NOT NULL,
  `bedrooms` varchar(10) DEFAULT NULL,
  `bathrooms` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('new','contacted','quoted','converted','declined') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
