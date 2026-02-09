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
  `phone` varchar(11) NOT NULL,
  `created_at` varchar(255) NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farmers`
--

INSERT INTO `farmers` (`id`, `name`, `age`, `birthday`, `address`, `land`, `unit`, `phone`, `created_at`) VALUES
(2, 'Jan Laurence Tan', 21, '2003-12-23', 'Kabankalan City', '2131', 'acre(s)', '09376217863', '2025-05-02 20:09:44'),
(3, 'Ryan Jay', 21, '2003-12-04', 'Kabankalan City', '135', 'm²', '09797686876', '2025-05-03 18:38:48'),
(4, 'Michael', 22, '2002-11-11', 'Kabankalan City', '2131', 'hectare(s)', '09218136287', '2025-05-03 18:41:13'),
(5, 'Robert', 20, '2004-05-31', 'Capuling Lamang', '121231', 'km²', '21474836', '2025-05-03 18:47:35'),
(8, 'Rigo', 0, '2003-01-01', 'Malabong', '3', 'acre(s)', '2147483647', '2026-02-03 18:21:59'),
(10, 'Roan', 0, '2011-02-01', 'Camansi', '124', 'hectare(s)', '2147483647', '2026-02-03 18:24:54'),
(11, 'Robel', 0, '2011-02-01', 'Cauayan', '13', 'hectare(s)', '14324325345', '2026-02-03 18:31:25'),
(12, 'Christian', 0, '2011-02-01', '1243254354364', '13424', 'hectare(s)', '14324325345', '2026-02-03 18:32:33'),
(13, 'John', 0, '2011-02-01', 'Bisan diin lang da', '31231', 'acres', '32153425436', '2026-02-03 18:33:06'),
(14, 'Jerovi', 0, '2011-02-01', 'Camansi', '1234231', 'hectare(s)', '39287492379', '2026-02-03 20:09:21'),
(15, 'Yoki', 0, '2011-02-05', 'Lilo', '1', 'm²', '32423432423', '2026-02-05 21:03:32');

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
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
