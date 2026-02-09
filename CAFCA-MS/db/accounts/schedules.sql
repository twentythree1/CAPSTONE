-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 09, 2026 at 01:40 PM
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
  `farmer_id` int(255) NOT NULL,
  `machine_id` int(255) NOT NULL,
  `schedule_date` date NOT NULL,
  `date_span` int(255) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Pending','Approved','On going','Completed') DEFAULT 'Pending',
  `reschedule_reason` text DEFAULT NULL,
  `rescheduled_at` datetime DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `return_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `farmer_id`, `machine_id`, `schedule_date`, `date_span`, `start_time`, `end_time`, `status`, `reschedule_reason`, `rescheduled_at`, `return_date`, `return_notes`) VALUES
(2, 2, 1, '2026-02-05', 3, '11:00:00', '23:00:00', 'Approved', NULL, NULL, NULL, NULL),
(5, 4, 1, '2026-01-13', 1, '08:00:00', '17:00:00', 'Completed', NULL, NULL, '2026-02-07 00:14:23', 'dwadwadwa'),
(6, 2, 1, '2026-01-14', 1, '15:32:00', '18:32:00', 'Completed', NULL, NULL, '2026-02-06 23:13:58', ''),
(7, 3, 1, '2026-01-15', 1, '15:38:00', '20:38:00', 'Completed', NULL, NULL, '2026-02-06 23:51:25', ''),
(8, 4, 1, '2026-01-29', 1, '08:33:00', '20:34:00', 'Completed', NULL, NULL, '2026-02-07 00:15:56', ''),
(9, 5, 1, '2026-01-30', 1, '08:35:00', '20:35:00', 'Completed', NULL, NULL, '2026-02-07 12:19:33', ''),
(10, 5, 1, '2026-01-31', 1, '08:51:00', '20:51:00', 'Completed', NULL, NULL, '2026-02-07 12:19:30', ''),
(11, 2, 1, '2026-02-01', 1, '08:52:00', '20:52:00', 'Completed', NULL, NULL, '2026-02-06 23:38:46', ''),
(12, 4, 1, '2026-02-02', 1, '09:02:00', '21:02:00', 'Completed', NULL, NULL, '2026-02-07 12:19:41', ''),
(13, 5, 1, '2026-02-03', 1, '09:13:00', '21:13:00', 'Completed', NULL, NULL, '2026-02-07 12:19:24', ''),
(14, 3, 1, '2026-02-04', 1, '09:14:00', '21:14:00', 'Completed', NULL, NULL, '2026-02-06 23:51:35', ''),
(15, 5, 1, '2026-02-07', 1, '08:58:00', '20:58:00', 'Approved', 'basta lang', '2026-02-06 21:05:25', NULL, NULL),
(16, 4, 1, '2026-02-22', 1, '08:26:00', '20:26:00', 'Approved', 'basta ah', '2026-02-07 00:15:29', NULL, NULL),
(18, 13, 11, '2026-02-03', 2, '06:51:00', '18:51:00', '', NULL, NULL, NULL, NULL),
(19, 4, 4, '2026-02-03', 1, '06:59:00', '18:59:00', 'Completed', NULL, NULL, '2026-02-07 00:17:55', ''),
(21, 10, 4, '2026-02-05', 2, '07:03:00', '19:03:00', 'Approved', NULL, NULL, NULL, NULL),
(22, 3, 1, '2026-02-09', 1, '07:30:00', '17:00:00', 'Approved', 'para timprano matapos', '2026-02-07 00:19:19', NULL, NULL),
(23, 14, 7, '2026-02-05', 1, '07:31:00', '19:31:00', 'Completed', NULL, NULL, '2026-02-07 12:19:18', ''),
(24, 13, 11, '2026-02-06', 0, '08:00:00', '17:00:00', '', NULL, NULL, NULL, NULL),
(25, 10, 11, '2026-02-07', 0, '08:00:00', '17:00:00', '', NULL, NULL, NULL, NULL),
(26, 10, 10, '2026-02-07', 0, '08:00:00', '17:00:00', '', NULL, NULL, NULL, NULL),
(27, 10, 12, '2026-02-07', 0, '08:00:00', '17:00:00', 'Approved', NULL, NULL, NULL, NULL),
(28, 14, 14, '2026-02-06', 0, '08:00:00', '17:00:00', 'Completed', NULL, NULL, '2026-02-07 00:19:45', ''),
(29, 5, 8, '2026-02-07', 0, '08:00:00', '17:00:00', 'Approved', NULL, NULL, NULL, NULL),
(30, 12, 14, '2026-02-09', 0, '08:00:00', '17:00:00', 'Approved', NULL, NULL, NULL, NULL),
(31, 14, 17, '2026-02-08', 0, '08:00:00', '17:00:00', 'Approved', NULL, NULL, NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

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
