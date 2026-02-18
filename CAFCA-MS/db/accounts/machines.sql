-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 18, 2026 at 03:07 PM
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
  `last_returned` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `name`, `quantity`, `status`, `acquisition_date`, `last_returned`) VALUES
(1, 'Hand Tractor', 0, 'Totally Damaged', '2016-03-12', '2026-02-17 16:17:19'),
(4, 'Harvester', 0, 'Partially Damaged', '2026-02-04', '2026-02-17 16:17:31'),
(7, 'Sprayers & Spreaders', 1, 'Available', '2026-02-03', '2026-02-07 12:19:18'),
(8, 'Hay & Forage Machinery', 1, 'Available', '2026-02-03', '2026-02-17 16:17:36'),
(9, 'Plow', 0, 'Available', '2026-02-04', NULL),
(10, 'Harrow', 0, 'Available', '2026-02-04', '2026-02-17 16:17:42'),
(11, 'Planter', 0, 'Available', '2026-02-04', NULL),
(12, 'Combine Harvester', 0, 'Available', '2026-02-04', '2026-02-17 16:17:56'),
(14, 'Thresher', 0, 'Available', '2026-02-03', '2026-02-17 16:18:04'),
(17, 'Sample', 0, 'Available', '2026-02-01', '2026-02-17 16:18:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_machines_last_returned` (`last_returned`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
