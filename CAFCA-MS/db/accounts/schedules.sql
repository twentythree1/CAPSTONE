-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2026 at 11:16 AM
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
-- Database: `testdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `farmer_id` int(255) DEFAULT NULL,
  `non_member_name` varchar(255) DEFAULT NULL,
  `machine_id` int(255) NOT NULL,
  `schedule_date` date NOT NULL,
  `date_span` int(255) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Pending','Approved','On going','Completed','Expired','Cancelled') DEFAULT 'Pending',
  `reschedule_reason` text DEFAULT NULL,
  `rescheduled_at` datetime DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `farmer_id`, `non_member_name`, `machine_id`, `schedule_date`, `date_span`, `start_time`, `end_time`, `status`, `reschedule_reason`, `rescheduled_at`, `return_date`, `return_notes`, `created_at`) VALUES
(62, 2, NULL, 11, '2026-02-21', 0, '00:42:00', '00:44:00', 'Expired', NULL, NULL, NULL, NULL, '2026-02-20 16:42:10'),
(64, 30, NULL, 2, '2026-02-23', 0, '08:00:00', '17:00:00', 'Completed', 'test', '2026-02-21 00:48:34', NULL, NULL, '2026-02-20 16:48:18'),
(65, 2, NULL, 2, '2026-02-24', 0, '08:00:00', '17:00:00', 'Completed', 'te mong', '2026-02-21 16:22:44', NULL, NULL, '2026-02-21 08:02:14'),
(66, 2, NULL, 7, '2026-02-24', 0, '06:00:00', '08:00:00', 'Completed', 'test', '2026-02-22 23:11:38', NULL, NULL, '2026-02-21 08:03:06'),
(67, 5, NULL, 9, '2026-02-23', 4, '08:00:00', '17:00:00', 'Approved', NULL, NULL, NULL, NULL, '2026-02-21 08:25:28'),
(68, 11, NULL, 11, '2026-02-23', 0, '08:00:00', '17:00:00', 'Cancelled', NULL, NULL, NULL, NULL, '2026-02-21 08:27:35'),
(69, 10, NULL, 6, '2026-02-21', 0, '22:49:00', '22:50:00', 'Completed', NULL, NULL, '2026-02-22 22:59:40', '', '2026-02-21 14:48:23'),
(70, 2, NULL, 11, '2026-02-22', 0, '00:27:00', '00:28:00', 'Completed', NULL, NULL, '2026-02-22 22:59:56', '', '2026-02-21 16:26:58'),
(71, 4, NULL, 10, '2026-02-22', 0, '00:45:00', '00:46:00', 'Completed', NULL, NULL, NULL, NULL, '2026-02-21 16:44:04'),
(72, 30, NULL, 11, '2026-02-24', 0, '08:00:00', '17:00:00', 'Completed', NULL, NULL, NULL, NULL, '2026-02-21 16:53:33'),
(73, 3, NULL, 8, '2026-02-22', 0, '14:48:00', '14:49:00', 'Completed', NULL, NULL, '2026-02-22 23:04:51', '', '2026-02-22 06:47:52'),
(75, NULL, 'Secret', 6, '2026-02-26', 0, '08:00:00', '21:30:00', 'Approved', NULL, NULL, NULL, NULL, '2026-02-25 10:12:09'),
(76, 15, NULL, 8, '2026-02-26', 1, '08:00:00', '17:00:00', 'Approved', NULL, NULL, NULL, NULL, '2026-02-25 10:14:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `machine_id` (`machine_id`),
  ADD KEY `idx_schedules_return_date` (`return_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`id`),
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
