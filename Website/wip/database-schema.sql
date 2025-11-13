-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 03, 2025 at 06:00 AM
-- Server version: 10.6.23-MariaDB-cll-lve
-- PHP Version: 8.4.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wip`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `role` enum('admin','manager','staff') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@wasatchcleaners.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 1, '2025-11-03 05:29:47', '2025-10-23 15:48:51', '2025-11-03 05:29:47');

-- --------------------------------------------------------

--
-- Table structure for table `application_logs`
--

CREATE TABLE `application_logs` (
  `id` int(11) NOT NULL,
  `level` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `request_id` varchar(32) NOT NULL,
  `user_id` varchar(100) DEFAULT 'anonymous',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `application_logs`
--

INSERT INTO `application_logs` (`id`, `level`, `message`, `context`, `request_id`, `user_id`, `ip_address`, `user_agent`, `url`, `created_at`) VALUES
(1, 'ERROR', 'Booking persistence failed', '{\"error\":\"Unknown column \'customer_id\' in \'INSERT INTO\'\",\"trace\":\"#0 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(104): mysqli->prepare()\\n#1 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(76): persist_booking()\\n#2 {main}\"}', 'ffcc53275ac95e6d', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/booking/submit-booking.php', '2025-10-23 21:18:12'),
(2, 'ERROR', 'Booking persistence failed', '{\"error\":\"Unknown column \'duration_minutes\' in \'INSERT INTO\'\",\"trace\":\"#0 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(104): mysqli->prepare()\\n#1 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(76): persist_booking()\\n#2 {main}\"}', '03bf8a06293af2aa', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/booking/submit-booking.php', '2025-10-23 21:28:07'),
(3, 'ERROR', 'Booking persistence failed', '{\"error\":\"Unknown column \'status_label\' in \'INSERT INTO\'\",\"trace\":\"#0 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(104): mysqli->prepare()\\n#1 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(76): persist_booking()\\n#2 {main}\"}', '61562688e033dbcb', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/booking/submit-booking.php', '2025-10-23 21:31:33'),
(4, 'ERROR', 'Booking persistence failed', '{\"error\":\"Unknown column \'status_label\' in \'INSERT INTO\'\",\"trace\":\"#0 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(104): mysqli->prepare()\\n#1 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(76): persist_booking()\\n#2 {main}\"}', '8a47322f2fb7b3ec', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/booking/submit-booking.php', '2025-10-23 21:33:56'),
(5, 'ERROR', 'Booking persistence failed', '{\"error\":\"Unknown column \'source\' in \'INSERT INTO\'\",\"trace\":\"#0 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(104): mysqli->prepare()\\n#1 \\/home\\/enoudohc\\/ekotgroup.enoudoh.com\\/booking\\/submit-booking.php(76): persist_booking()\\n#2 {main}\"}', '35817abcf4a288b4', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/booking/submit-booking.php', '2025-10-23 21:35:54'),
(6, 'ERROR', 'Email sending error', '{\"booking_id\":\"WC-20251025-21CF53\",\"error\":\"Class \\\"PHPMailer\\\\PHPMailer\\\\PHPMailer\\\" not found\"}', '2939c52ba355d8df', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '/booking/submit-booking.php', '2025-10-25 08:09:36'),
(7, 'WARNING', 'Failed to send SMS confirmation via OpenPhone', '{\"booking_id\":\"WC-20251025-21CF53\",\"http_code\":400,\"response\":\"{\\\"message\\\":\\\"The input was invalid\\\",\\\"code\\\":\\\"0200400\\\",\\\"status\\\":400,\\\"docs\\\":\\\"https:\\/\\/openphone.com\\/docs\\\",\\\"title\\\":\\\"Bad Request\\\",\\\"errors\\\":[{\\\"path\\\":\\\"\\/to\\/0\\\",\\\"message\\\":\\\"Expected string to match \'^\\\\\\\\+[1-9]\\\\\\\\d{1,14}$\'\\\",\\\"value\\\":\\\"(913) 346-5680\\\",\\\"schema\\\":{\\\"type\\\":\\\"String\\\",\\\"pattern\\\":\\\"^\\\\\\\\+[1-9]\\\\\\\\d{1,14}$\\\"}}]}\"}', '2939c52ba355d8df', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '/booking/submit-booking.php', '2025-10-25 08:09:36'),
(8, 'ERROR', 'Failed to persist booking', '{\"booking_id\":\"WC-20251027-48C192\",\"error\":\"The number of elements in the type definition string must match the number of bind variables\"}', '199a8fd959ff5055', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/wip/boot_check.php', '2025-10-27 19:37:36'),
(9, 'ERROR', 'Failed to persist booking', '{\"booking_id\":\"WC-20251027-7441FC\",\"error\":\"The number of elements in the type definition string must match the number of bind variables\"}', '88dde854828e0ba8', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/wip/boot_check.php', '2025-10-27 23:14:37'),
(11, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251023-B71998\",\"staff_ids\":[1],\"error\":\"Unknown column \'updated_at\' in \'UPDATE\'\",\"assigned_by\":1}', '016fdb5e8980619b', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/wip/admin/api/assignments.php', '2025-10-31 11:44:15'),
(12, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251023-B71998\",\"staff_ids\":[2],\"error\":\"Unknown column \'created_by\' in \'INSERT INTO\'\",\"assigned_by\":1}', 'daf53db3698c5131', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15', '/wip/admin/api/assignments.php', '2025-11-01 03:44:06'),
(13, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251025-21CF53\",\"staff_ids\":[2],\"error\":\"Unknown column \'created_by\' in \'INSERT INTO\'\",\"assigned_by\":1}', 'c66a59c0ec643d7b', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '/wip/admin/api/assignments.php', '2025-11-01 05:39:19'),
(14, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251025-21CF53\",\"staff_ids\":[2],\"error\":\"Unknown column \'created_by\' in \'INSERT INTO\'\",\"assigned_by\":1}', '1671bbb005561156', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '/wip/admin/api/assignments.php', '2025-11-01 05:39:33'),
(15, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251025-21CF53\",\"staff_ids\":[2],\"error\":\"Unknown column \'created_by\' in \'INSERT INTO\'\",\"assigned_by\":1}', '86688f26ef914ae4', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '/wip/admin/api/assignments.php', '2025-11-01 05:39:40'),
(16, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251024-7BF0D0\",\"staff_ids\":[2],\"error\":\"Unknown column \'created_by\' in \'INSERT INTO\'\",\"assigned_by\":1}', 'aa09cfdfc63081cf', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/wip/admin/api/assignments.php', '2025-11-01 06:59:47'),
(17, 'ERROR', 'Failed to assign staff', '{\"booking_id\":\"WC-20251024-7BF0D0\",\"staff_ids\":[1],\"error\":\"Unknown column \'created_by\' in \'INSERT INTO\'\",\"assigned_by\":1}', '41b0a6c226e59b4c', 'anonymous', '69.76.174.203', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '/wip/admin/api/assignments.php', '2025-11-01 07:07:23');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `property_type` varchar(50) NOT NULL,
  `bedrooms` varchar(10) NOT NULL,
  `bathrooms` varchar(10) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(20) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(50) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `estimated_price` decimal(10,2) NOT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `source` varchar(20) DEFAULT NULL,
  `status_label` varchar(50) NOT NULL,
  `payment_status` enum('unpaid','paid','refunded') DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `quickbooks_invoice_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_id`, `customer_id`, `service_type`, `frequency`, `property_type`, `bedrooms`, `bathrooms`, `appointment_date`, `appointment_time`, `duration_minutes`, `first_name`, `last_name`, `email`, `phone`, `address`, `city`, `state`, `zip`, `notes`, `estimated_price`, `final_price`, `status`, `source`, `status_label`, `payment_status`, `payment_method`, `quickbooks_invoice_id`, `created_at`, `updated_at`) VALUES
(1, 'WC-20251023-B71998', 1, 'onetime', 'monthly', 'apartment', '3', '4', '2025-10-31', '11:00 AM', 180, 'Wasatch', 'Cleaners', 'wasatch@gmail.com', '0000009999', '2435 Main Stereet', 'Ghot', 'UT', '09912', '', 241.68, NULL, 'pending', 'website', 'scheduled', 'unpaid', NULL, NULL, '2025-10-23 21:37:45', '2025-10-23 21:37:45'),
(2, 'WC-20251023-A8D42C', 1, 'onetime', 'monthly', 'apartment', '3', '4', '2025-10-31', '11:00 AM', 180, 'Wasatch', 'Cleaners', 'wasatchclean05@gmail.com', '0000009999', '2435 Main Stereet', 'Ghot', 'UT', '09912', '', 241.68, NULL, 'confirmed', 'website', 'scheduled', 'unpaid', NULL, NULL, '2025-10-23 21:46:31', '2025-10-30 19:43:12'),
(3, 'WC-20251024-7BF0D0', 2, 'move', 'biweekly', 'apartment', '3', '2', '2025-10-30', '01:00 PM', 240, 'Test', 'Jan', 'ekemthad@gmail.com', '8167154786', '234 Hagab', 'LOLO', 'UT', '90821', '', 430.56, NULL, 'completed', 'website', 'scheduled', 'unpaid', NULL, NULL, '2025-10-24 23:32:34', '2025-10-30 19:42:54'),
(4, 'WC-20251025-21CF53', 3, 'onetime', 'monthly', 'apartment', '2', '1', '2025-10-27', '09:00 AM', 180, 'Testeee', 'Woloo', 'elise.ud@yahoo.com', '9133465680', '2222 Harag', 'Gshs', 'UT', '09101', '', 196.37, NULL, 'pending', 'website', 'scheduled', 'unpaid', NULL, NULL, '2025-10-25 08:09:36', '2025-10-25 08:09:36'),
(6, 'WC-20251027-BDA200', 3, 'deep', 'onetime', 'house', '3', '2', '2025-10-29', '2', 210, 'Elise', 'Udoh', 'elise.ud@yahoo.com', '19133465680', '123 Test Street', 'Salt Lake City', 'UT', '84101', 'LIVE TEST - Notification System Test Booking', 398.40, NULL, 'pending', 'website', 'scheduled', 'unpaid', NULL, NULL, '2025-10-28 02:34:18', '2025-10-28 02:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `booking_assignments`
--

CREATE TABLE `booking_assignments` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assignment_role` enum('lead','assistant') DEFAULT 'assistant',
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_assignments`
--

INSERT INTO `booking_assignments` (`id`, `booking_id`, `staff_id`, `assigned_at`, `assignment_role`, `created_by`) VALUES
(14, 'WC-20251024-7BF0D0', 1, '2025-11-02 16:53:42', 'assistant', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `booking_metrics`
--

CREATE TABLE `booking_metrics` (
  `id` int(11) NOT NULL,
  `metric_date` date NOT NULL,
  `total_bookings` int(11) DEFAULT 0,
  `confirmed_bookings` int(11) DEFAULT 0,
  `completed_bookings` int(11) DEFAULT 0,
  `cancelled_bookings` int(11) DEFAULT 0,
  `total_revenue` decimal(12,2) DEFAULT 0.00,
  `average_booking_value` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_status_history`
--

CREATE TABLE `booking_status_history` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_status_history`
--

INSERT INTO `booking_status_history` (`id`, `booking_id`, `previous_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
(1, 'WC-20251023-B71998', NULL, 'scheduled', NULL, 'Booking created via public website', '2025-10-23 21:37:45'),
(2, 'WC-20251023-A8D42C', NULL, 'scheduled', NULL, 'Booking created via public website', '2025-10-23 21:46:31'),
(3, 'WC-20251024-7BF0D0', NULL, 'scheduled', NULL, 'Booking created via public website', '2025-10-24 23:32:34'),
(4, 'WC-20251025-21CF53', NULL, 'scheduled', NULL, 'Booking created via public website', '2025-10-25 08:09:36'),
(6, 'WC-20251027-BDA200', NULL, 'scheduled', NULL, 'Booking created via public website', '2025-10-28 02:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `id` int(11) NOT NULL,
  `business_name` varchar(255) DEFAULT 'Wasatch Cleaners',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `operating_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`operating_hours`)),
  `holidays` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`holidays`)),
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
  `logo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_settings`
--

INSERT INTO `business_settings` (`id`, `business_name`, `phone`, `email`, `address`, `city`, `state`, `zip`, `operating_hours`, `holidays`, `notification_preferences`, `logo_path`, `created_at`, `updated_at`) VALUES
(1, 'Wasatch Cleaners', '(385) 213-8900', 'support@wasatchcleaners.com', NULL, NULL, NULL, NULL, NULL, NULL, '{\"default_duration\": 180, \"notify_email\": 1, \"notify_sms\": 1}', NULL, '2025-10-23 15:48:52', '2025-10-23 15:48:52');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `external_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `marketing_opt_in` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `external_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `city`, `state`, `zip`, `notes`, `marketing_opt_in`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Wasatch', 'Cleaners', 'wasatchclean05@gmail.com', '0000009999', '2435 Main Stereet', 'Ghot', 'UT', '09912', '', 1, '2025-10-23 21:18:12', '2025-10-23 21:46:31'),
(2, NULL, 'Test', 'Jan', 'ekemthad@gmail.com', '8167154786', '234 Hagab', 'LOLO', 'UT', '90821', '', 1, '2025-10-24 23:32:34', '2025-10-24 23:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `booking_id`, `recipient_email`, `subject`, `status`, `error_message`, `sent_at`) VALUES
(4, 'WC-20251027-BDA200', 'elise.ud@yahoo.com', 'Booking Confirmation - WC-20251027-BDA200', 'sent', NULL, '2025-10-28 02:34:20'),
(5, 'WC-20251027-BDA200', 'admin@wasatchcleaners.com', '🔔 New Booking Alert: WC-20251027-BDA200', 'sent', NULL, '2025-10-28 02:34:22'),
(6, 'WC-20251027-BDA200', 'ekemthad@gmail.com', 'New Assignment: Booking WC-20251027-BDA200', 'sent', NULL, '2025-10-28 02:34:24');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `quickbooks_invoice_id` varchar(255) DEFAULT NULL,
  `booking_id` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `status` enum('draft','sent','paid','void') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('booking_created','booking_updated','booking_cancelled','payment_received','staff_assignment') NOT NULL,
  `booking_id` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `booking_id`, `customer_id`, `staff_id`, `payload`, `is_read`, `created_at`) VALUES
(4, 'booking_created', NULL, NULL, NULL, '{\"message\":\"Test Customer booked a Regular service for Oct 30, 2025.\",\"appointment_time\":\"10:00 AM\",\"estimated_price\":175.43999999999999772626324556767940521240234375}', 0, '2025-10-28 00:07:07'),
(5, 'booking_created', 'WC-20251027-BDA200', NULL, NULL, '{\"message\":\"Elise Udoh booked a Deep service for Oct 29, 2025.\",\"appointment_time\":\"2:00 PM\",\"estimated_price\":398.3999999999999772626324556767940521240234375}', 0, '2025-10-28 02:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `quickbooks_payment_id` varchar(255) NOT NULL,
  `quickbooks_invoice_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `status` varchar(50) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `receipt_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `identifier` varchar(64) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `identifier`, `attempts`, `expires_at`, `created_at`) VALUES
(11, '4087e5c1cb38d6455d41a13a1ec51064', 1, '2025-10-25 02:14:36', '2025-10-25 08:09:36');

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_reminders`
--

CREATE TABLE `scheduled_reminders` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `send_at` datetime NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_availability`
--

CREATE TABLE `service_availability` (
  `id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Sunday, 1=Monday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_bookings` int(11) DEFAULT 4,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_availability`
--

INSERT INTO `service_availability` (`id`, `day_of_week`, `start_time`, `end_time`, `max_bookings`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 1, '08:00:00', '17:00:00', 4, 1, '2025-10-23 15:48:51', '2025-10-23 15:48:51'),
(2, 2, '08:00:00', '17:00:00', 4, 1, '2025-10-23 15:48:51', '2025-10-23 15:48:51'),
(3, 3, '08:00:00', '17:00:00', 4, 1, '2025-10-23 15:48:51', '2025-10-23 15:48:51'),
(4, 4, '08:00:00', '17:00:00', 4, 1, '2025-10-23 15:48:51', '2025-10-23 15:48:51'),
(5, 5, '08:00:00', '17:00:00', 4, 1, '2025-10-23 15:48:51', '2025-10-23 15:48:51'),
(6, 6, '08:00:00', '17:00:00', 4, 1, '2025-10-23 15:48:51', '2025-10-23 15:48:51');

-- --------------------------------------------------------

--
-- Table structure for table `service_catalog`
--

CREATE TABLE `service_catalog` (
  `id` int(11) NOT NULL,
  `service_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `default_duration_minutes` int(11) DEFAULT 180,
  `recurrence_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recurrence_options`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_catalog`
--

INSERT INTO `service_catalog` (`id`, `service_code`, `name`, `description`, `base_price`, `default_duration_minutes`, `recurrence_options`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'regular', 'Standard Cleaning', 'Ideal for ongoing maintenance on a weekly or bi-weekly cadence.', 129.00, 150, '[\"weekly\", \"biweekly\", \"monthly\"]', 1, '2025-10-23 15:48:52', '2025-10-23 15:48:52'),
(2, 'deep', 'Deep Cleaning', 'Top-to-bottom detail clean including baseboards, cabinets, and appliances.', 249.00, 210, '[\"onetime\"]', 1, '2025-10-23 15:48:52', '2025-10-23 15:48:52'),
(3, 'move', 'Move In / Move Out', 'Turn-key move service with fridge, oven, and interior cabinet wipe downs.', 299.00, 240, '[\"onetime\"]', 1, '2025-10-23 15:48:52', '2025-10-23 15:48:52'),
(4, 'onetime', 'One-Time Cleaning', 'Perfect for pre-event refreshes or seasonal tidy-ups.', 159.00, 180, '[\"onetime\"]', 1, '2025-10-23 15:48:52', '2025-10-23 15:48:52');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`id`, `booking_id`, `recipient_phone`, `message`, `status`, `error_message`, `sent_at`) VALUES
(1, 'WC-20251025-21CF53', '(913) 346-5680', 'Wasatch Cleaners: Booking confirmed! ID: WC-20251025-21CF53. Date: 2025-10-27 at 09:00 AM. We\'ll contact you soon to confirm. Questions? Call (385)213-8900', 'failed', 'HTTP 400', '2025-10-25 08:09:36'),
(3, 'WC-20251027-BDA200', '+19133465680', 'Wasatch Cleaners: Booking confirmed! ID: WC-20251027-BDA200. Date: 2025-10-29 at 2:00 PM. We\'ll contact you soon to confirm. Questions? Call (385)213-8900', 'failed', 'HTTP 401', '2025-10-28 02:34:20');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('cleaner','team_lead','inspector','admin') DEFAULT 'cleaner',
  `color_tag` varchar(7) DEFAULT '#14b8a6',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `role`, `color_tag`, `hourly_rate`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Jane', 'Doe', 'jane.doe@wasatchcleaners.com', '+13852138900', 'team_lead', '#14b8a6', NULL, NULL, 1, '2025-10-29 01:03:05', '2025-10-29 01:03:05'),
(2, NULL, 'John', 'Smith', 'john.smith@wasatchcleaners.com', '+19133465680', 'cleaner', '#f59e0b', NULL, NULL, 1, '2025-10-29 01:03:05', '2025-10-29 01:03:05');

-- --------------------------------------------------------

--
-- Table structure for table `staff_availability`
--

CREATE TABLE `staff_availability` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `application_logs`
--
ALTER TABLE `application_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_request` (`request_id`),
  ADD KEY `idx_level_created` (`level`,`created_at`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `booking_assignments`
--
ALTER TABLE `booking_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_booking_staff` (`booking_id`,`staff_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `booking_metrics`
--
ALTER TABLE `booking_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_metric_date` (`metric_date`),
  ADD KEY `idx_metric_date` (`metric_date`);

--
-- Indexes for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_status_history_booking` (`booking_id`),
  ADD KEY `idx_status_history_created` (`created_at`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_name` (`last_name`,`first_name`),
  ADD KEY `idx_customer_email` (`email`),
  ADD KEY `idx_customer_phone` (`phone`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_recipient` (`recipient_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD UNIQUE KEY `quickbooks_invoice_id` (`quickbooks_invoice_id`),
  ADD KEY `idx_invoice_status` (`status`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_type` (`type`),
  ADD KEY `idx_notifications_read` (`is_read`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quickbooks_payment_id` (`quickbooks_payment_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_invoice_id` (`quickbooks_invoice_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_identifier_expires` (`identifier`,`expires_at`);

--
-- Indexes for table `scheduled_reminders`
--
ALTER TABLE `scheduled_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_send_at` (`send_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_status_send_at` (`status`,`send_at`);

--
-- Indexes for table `service_availability`
--
ALTER TABLE `service_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_day` (`day_of_week`),
  ADD KEY `idx_is_available` (`is_available`);

--
-- Indexes for table `service_catalog`
--
ALTER TABLE `service_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_code` (`service_code`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_recipient` (`recipient_phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_active` (`is_active`),
  ADD KEY `idx_staff_name` (`last_name`,`first_name`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `staff_availability`
--
ALTER TABLE `staff_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_date` (`staff_id`,`available_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `application_logs`
--
ALTER TABLE `application_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `booking_assignments`
--
ALTER TABLE `booking_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `booking_metrics`
--
ALTER TABLE `booking_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `business_settings`
--
ALTER TABLE `business_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `scheduled_reminders`
--
ALTER TABLE `scheduled_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_availability`
--
ALTER TABLE `service_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_catalog`
--
ALTER TABLE `service_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_availability`
--
ALTER TABLE `staff_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_assignments`
--
ALTER TABLE `booking_assignments`
  ADD CONSTRAINT `booking_assignments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_assignments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_assignments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  ADD CONSTRAINT `booking_status_history_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `scheduled_reminders`
--
ALTER TABLE `scheduled_reminders`
  ADD CONSTRAINT `scheduled_reminders_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_availability`
--
ALTER TABLE `staff_availability`
  ADD CONSTRAINT `staff_availability_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
