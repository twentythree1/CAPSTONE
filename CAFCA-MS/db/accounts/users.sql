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
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `created_at`) VALUES
(1, 'admin', '$2y$10$R.Nbymv7q.sBQougYSdhGepGK1IqwyBIPsEy8PnAxHIyBar9a7JJy', '2025-04-04 14:25:59'),
(2, 'jan', '$2y$10$IPmSaP3mFaPY0ou5IGWJxulVorulUB0dF2dQb2/pbGEJO721pQEJG', '2025-04-04 15:04:47'),
(9, 'jani', '$2y$10$u0CekGvdnDK6CRLeTMQ91ew95Ze8NStVowH8FFCLUvHD6T3YeAGI6', '2025-04-07 14:40:57'),
(10, 'kin', '$2y$10$jXlvNYw1W4mBzu1Uc68ooOTWN49RKRAJ6J56wlEKbsSlN4UKj.sMG', '2025-08-28 15:31:45'),
(11, 'ken', '$2y$10$kObMpx4jaRcrzDMm4TCGOeuRQ4skVzuAEf7JVit9nGrHOftjhdNZO', '2026-01-28 13:01:58'),
(12, 'manang ni', '$2y$10$vTjhdesF72pNLlaso5cvY.2Kh0SdaqJ.Uuv86ZnzulQcFPrXcqO1O', '2026-02-07 14:47:33'),
(13, 'jani', '$2y$10$D9kuScZ/3aEuoq8F9ldfB.lMhGKiJ6hFrzqVGs39VjIUh..Ji3Ki6', '2026-02-09 12:34:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
