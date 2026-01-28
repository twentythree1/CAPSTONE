-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2025 at 05:05 PM
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
-- Table structure for table `farmers`
--

CREATE TABLE `farmers` (
  `id` int(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `age` int(2) NOT NULL,
  `birthday` date NOT NULL,
  `address` varchar(255) NOT NULL,
  `land` varchar(255) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `phone` int(255) NOT NULL,
  `created_at` varchar(255) NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farmers`
--

INSERT INTO `farmers` (`id`, `name`, `age`, `birthday`, `address`, `land`, `unit`, `phone`, `created_at`) VALUES
(2, 'Jan Laurence Tan', 21, '2003-12-23', 'Kabankalan City', '2131', 'acre(s)', 2147483647, '2025-05-02 20:09:44'),
(3, 'Ryan Jay', 21, '2003-12-04', 'Kabankalan City', '135', 'm²', 13213131, '2025-05-03 18:38:48'),
(4, 'Michael', 22, '2002-11-11', 'Kabankalan City', '2131', 'hectare(s)', 98271973, '2025-05-03 18:41:13'),
(5, 'Robert', 20, '2004-05-31', 'Capuling Lamang', '121231', 'km²', 21474836, '2025-05-03 18:47:35'),
(6, 'Kurt Jan', 21, '2004-01-28', 'Ilog Supremacy', '180', 'N/A', 2147483647, '2025-05-03 18:50:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `farmers`
--
ALTER TABLE `farmers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `farmers`
--
ALTER TABLE `farmers`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
