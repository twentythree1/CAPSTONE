-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 19, 2026 at 05:49 PM
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
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` int(255) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL,
  `acquisition_date` date NOT NULL,
  `unavailable_from` datetime DEFAULT NULL,
  `unavailable_until` datetime DEFAULT NULL,
  `unavailable_count` int(255) DEFAULT 1,
  `last_returned` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `name`, `quantity`, `status`, `acquisition_date`, `unavailable_from`, `unavailable_until`, `unavailable_count`, `last_returned`) VALUES
(1, 'Thresher', 1, 'Available', '2026-02-01', NULL, NULL, 1, NULL),
(2, 'Field Master', 1, 'Available', '2026-02-01', NULL, NULL, 1, NULL),
(4, 'Combine Harvester', 2, 'Available', '2026-02-01', '2026-02-19 22:57:00', '2026-02-20 04:00:00', 1, '2026-02-19 22:50:04'),
(5, 'Tractor', 2, 'Available', '2026-02-01', NULL, NULL, 1, NULL),
(6, 'Floating Tiller', 2, 'Available', '2026-02-01', NULL, NULL, 1, NULL),
(7, 'Precision Seeder', 1, 'Available', '2026-02-01', NULL, NULL, 1, NULL),
(8, 'Seed Cleaner', 1, 'Partially Damaged', '2026-02-01', NULL, NULL, 1, '2026-02-20 00:43:21'),
(9, 'Water Pump', 1, 'Available', '2026-02-01', NULL, NULL, 1, NULL),
(10, 'Rice Transplanter', 1, 'Available', '2026-02-01', NULL, NULL, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_machines_last_returned` (`last_returned`),
  ADD KEY `idx_unavailable_dates` (`unavailable_from`,`unavailable_until`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
