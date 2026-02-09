-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 09, 2026 at 01:39 PM
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
-- Table structure for table `machine_history`
--

CREATE TABLE `machine_history` (
  `id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `status_before` varchar(50) DEFAULT NULL,
  `status_after` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` varchar(100) NOT NULL,
  `changed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `machine_history`
--

INSERT INTO `machine_history` (`id`, `machine_id`, `schedule_id`, `status_before`, `status_after`, `notes`, `changed_by`, `changed_at`) VALUES
(1, 1, 14, 'In Use', 'Partially Damaged', '', 'jan', '2026-02-06 23:51:35'),
(3, 1, 5, 'In Use', 'Available', 'dwadwadwa', 'jan', '2026-02-07 00:14:23'),
(4, 7, 23, 'In Use', 'Partially Damaged', '', 'jani', '2026-02-07 12:19:18'),
(5, 1, 13, 'In Use', 'Totally Damaged', '', 'jani', '2026-02-07 12:19:24'),
(6, 1, 10, 'In Use', 'Totally Damaged', '', 'jani', '2026-02-07 12:19:30'),
(7, 1, 9, 'In Use', 'Partially Damaged', '', 'jani', '2026-02-07 12:19:33'),
(8, 1, 12, 'In Use', 'Totally Damaged', '', 'jani', '2026-02-07 12:19:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `machine_history`
--
ALTER TABLE `machine_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `idx_machine_id` (`machine_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `machine_history`
--
ALTER TABLE `machine_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `machine_history`
--
ALTER TABLE `machine_history`
  ADD CONSTRAINT `machine_history_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `machine_history_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
