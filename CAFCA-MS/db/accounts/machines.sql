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
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `acquisition_date` date NOT NULL,
  `last_returned` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `name`, `type`, `status`, `acquisition_date`, `last_returned`) VALUES
(1, 'Hand Tractor', 'dalhdwad', 'Totally Damaged', '2016-03-12', '2026-02-07 12:19:41'),
(4, 'Harvester', 'dawdawdwa', 'Partially Damaged', '2026-02-04', '2026-02-07 00:17:55'),
(7, 'Sprayers & Spreaders', 'dawdwadwa', 'Partially Damaged', '2026-02-03', '2026-02-07 12:19:18'),
(8, 'Hay & Forage Machinery', 'adwdwa', 'Totally Damaged', '2026-02-03', NULL),
(9, 'Plow', 'dawdaw', 'Available', '2026-02-04', NULL),
(10, 'Harrow', 'dwadwa', 'Available', '2026-02-04', NULL),
(11, 'Planter', 'dadaw', 'Available', '2026-02-04', NULL),
(12, 'Combine Harvester', 'adwa', 'Partially Damaged', '2026-02-04', NULL),
(14, 'Thresher', 'dawdawdwa', 'Available', '2026-02-03', '2026-02-07 00:19:45'),
(17, 'Sample', 'dawdwadwa', 'Available', '2026-02-01', NULL);

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
