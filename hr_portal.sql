-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 24, 2025 at 09:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hr_portal`
--
CREATE DATABASE IF NOT EXISTS `hr_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hr_portal`;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Foreign key to users.id for efficiency',
  `employee_code` varchar(50) NOT NULL COMMENT 'The User ID from the machine (denormalized for convenience)',
  `shift_id` int(11) DEFAULT NULL,
  `punch_time` datetime NOT NULL,
  `punch_state` tinyint(1) NOT NULL COMMENT '0=In, 1=Out',
  `status` varchar(20) NOT NULL DEFAULT 'valid',
  `violation_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `attendance_logs`
--

TRUNCATE TABLE `attendance_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `audit_logs`
--

TRUNCATE TABLE `audit_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `departments`
--

TRUNCATE TABLE `departments`;
--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'IT', ''),
(2, 'HR', '');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `port` int(11) NOT NULL DEFAULT 4370,
  `device_brand` varchar(100) DEFAULT NULL,
  `status` enum('online','offline','unknown') NOT NULL DEFAULT 'unknown',
  `last_seen` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `serial_number` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `devices`
--

TRUNCATE TABLE `devices`;
--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `name`, `ip_address`, `port`, `device_brand`, `status`, `last_seen`, `created_at`, `updated_at`, `serial_number`) VALUES
(1, 'FingerTec AC100C', '192.168.1.120', 4370, 'FingerTec', 'online', '2025-06-24 22:55:55', '2025-06-22 15:03:19', '2025-06-24 19:55:55', '8128191');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance_days` float NOT NULL DEFAULT 0,
  `last_accrual_date` date DEFAULT NULL,
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `leave_balances`
--

TRUNCATE TABLE `leave_balances`;
--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `user_id`, `leave_type_id`, `balance_days`, `last_accrual_date`, `last_updated_at`) VALUES
(1, 2, 1, 21, '2025-06-22', '2025-06-22 20:20:08'),
(11, 3, 1, 21, '2025-06-22', '2025-06-22 20:20:08'),
(12, 4, 1, 20, '2025-06-22', '2025-06-22 20:25:18'),
(13, 5, 1, 21, '2025-06-22', '2025-06-22 20:20:08'),
(14, 6, 1, 21, '2025-06-22', '2025-06-22 20:20:08');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `accrual_days_per_year` float NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `leave_types`
--

TRUNCATE TABLE `leave_types`;
--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `accrual_days_per_year`, `is_active`) VALUES
(1, 'Annual Leave', 21, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `request_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `notifications`
--

TRUNCATE TABLE `notifications`;
-- --------------------------------------------------------

--
-- Table structure for table `request_comments`
--

CREATE TABLE `request_comments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `request_comments`
--

TRUNCATE TABLE `request_comments`;
-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `grace_in_minutes` int(11) NOT NULL DEFAULT 0,
  `grace_out_minutes` int(11) NOT NULL DEFAULT 0,
  `is_night_shift` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `shifts`
--

TRUNCATE TABLE `shifts`;
--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `shift_name`, `start_time`, `end_time`, `break_start_time`, `break_end_time`, `grace_in_minutes`, `grace_out_minutes`, `is_night_shift`, `created_at`, `updated_at`) VALUES
(1, 'Default Day Shift', '08:00:00', '17:00:00', NULL, NULL, 15, 0, 0, '2025-06-22 14:10:58', '2025-06-22 14:10:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `role` enum('user','manager','hr','hr_manager','admin') NOT NULL DEFAULT 'user',
  `shift_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `direct_manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_code`, `full_name`, `email`, `password`, `contact_number`, `emergency_contact_name`, `emergency_contact_number`, `role`, `shift_id`, `department_id`, `direct_manager_id`, `is_active`, `must_change_password`, `last_login_at`, `created_at`, `updated_at`) VALUES
(2, '1178', 'Joseph Ashraf', 'eptj0e@gmail.com', '$2y$10$R.JmE1zsBTIu8xbFW4/6bezM0RWqtm/UPBvau1.5oCxIlFWVCTbke', NULL, NULL, NULL, 'admin', 1, 1, NULL, 1, 0, NULL, '2025-06-22 14:12:40', '2025-06-22 16:09:39'),
(3, '800', 'Reda Ayman', 'reda.ayman@gmail.com', '$2y$10$bTkcrjHdP.y3qBTjS2Z9A.BFdOPjJS02nhwRRufJ.HY2z/yJE2n0K', NULL, NULL, NULL, 'manager', 1, 1, NULL, 1, 0, NULL, '2025-06-22 19:41:55', '2025-06-22 19:41:55'),
(4, '850', 'Mohamed Taha', 'mohamed.taha@gmail.com', '$2y$10$QDddg/TRRvl6risoRDk1/.6PH1/ZpbQwUpZEDiXnH8LcVHMDCAHEa', NULL, NULL, NULL, 'user', 1, 1, 3, 1, 0, NULL, '2025-06-22 19:42:25', '2025-06-22 20:04:32'),
(5, '122', 'Sara Zayed', 'sara.zayed@gmail.com', '$2y$10$OhcUC4Bq2IO.YSZOumE7V.LlLk2BIdJdDmIfrapOosE122CsJv8bS', NULL, NULL, NULL, 'hr_manager', 1, 2, NULL, 1, 0, NULL, '2025-06-22 19:56:17', '2025-06-22 20:04:23'),
(6, '99', 'Aya Ahmed', 'aya.ahmed@gmail.com', '$2y$10$ocMdWK4PDq5ZlqYJ2EjeVuTcwszZcjxYCsw3MXxqmC5vAATI.3VF.', NULL, NULL, NULL, 'hr', 1, 2, 5, 1, 0, NULL, '2025-06-22 20:05:09', '2025-06-22 20:05:09');

-- --------------------------------------------------------

--
-- Table structure for table `vacation_requests`
--

CREATE TABLE `vacation_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) DEFAULT NULL,
  `duration_days` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('pending_manager','pending_hr','approved','rejected','cancelled','pending_cancellation_hr') NOT NULL DEFAULT 'pending_manager',
  `manager_id` int(11) DEFAULT NULL,
  `hr_id` int(11) DEFAULT NULL,
  `action_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `vacation_requests`
--

TRUNCATE TABLE `vacation_requests`;
--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_punch` (`user_id`,`punch_time`,`punch_state`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_ip_port` (`ip_address`,`port`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_leave_type` (`user_id`,`leave_type_id`),
  ADD KEY `leave_balances_ibfk_2` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_name` (`shift_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `direct_manager_id` (`direct_manager_id`);

--
-- Indexes for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `vacation_requests_ibfk_3` (`manager_id`),
  ADD KEY `vacation_requests_ibfk_4` (`hr_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_comments`
--
ALTER TABLE `request_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `request_comments`
--
ALTER TABLE `request_comments`
  ADD CONSTRAINT `request_comments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `vacation_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `request_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`direct_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vacation_requests`
--
ALTER TABLE `vacation_requests`
  ADD CONSTRAINT `vacation_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vacation_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `vacation_requests_ibfk_3` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `vacation_requests_ibfk_4` FOREIGN KEY (`hr_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
