-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2026 at 11:16 AM
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
-- Database: `clocking_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `full_name`, `email`, `password_hash`, `last_login`, `created_at`) VALUES
(1, 'admin', 'System Administrator', 'admin@sbs.edu.ng', 'Admin@1234', '2026-06-11 10:09:10', '2026-06-09 14:53:38');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `att_date` date NOT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `auto_clocked_out` tinyint(1) DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `designation` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `enroll_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`id`, `staff_id`, `full_name`, `department`, `designation`, `email`, `phone`, `enroll_date`, `is_active`, `created_at`) VALUES
(1, 'SBS/LEC/008', 'Olayinka Jeremiah', 'Computer Science', 'Lecturer', 'Olay84414@gmail.com', NULL, '2026-06-09', 1, '2026-06-09 15:40:40'),
(12, 'SBS/LEC/002', 'aedsa', 'Business Administration', 'Lecturer', 'Towerspalms.mfl@gmail.com', '07039333423', '2026-06-10', 1, '2026-06-10 11:13:15');

-- --------------------------------------------------------

--
-- Table structure for table `qr_tokens`
--

CREATE TABLE `qr_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `action` enum('clock_in','clock_out','register') NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_tokens`
--

INSERT INTO `qr_tokens` (`id`, `token`, `staff_id`, `action`, `expires_at`, `used`, `created_at`) VALUES
(1, 'e80b11da6d768b7f1cd27d368b5efba8c8570b073813aefe222ce1e79c9e0316', 'SBS/LEC/008', 'clock_in', '2026-06-09 22:28:43', 1, '2026-06-09 21:26:43'),
(2, 'f150ee6b95689c3e839ec97f2977dcd088e496b661b09464bdf6f4b5a1c7a965', 'SBS/LEC/008', 'clock_in', '2026-06-09 22:28:48', 1, '2026-06-09 21:26:48'),
(3, '00c63c620feb0fd0539b176a55524862f8e352b89c75c422101db865cf9a1487', 'SBS/LEC/008', 'clock_out', '2026-06-09 22:28:52', 0, '2026-06-09 21:26:52'),
(4, 'c766039e1a1339f747f4bb6f1dc082693e1137d3d3f547f2b89284daa943553b', 'SBS/LEC/008', 'register', '2026-06-09 22:28:55', 1, '2026-06-09 21:26:55'),
(5, 'a0abfb43f73b1a7cd23b4e0f9f8800b4511af35c5b16cefd8c7340b314b99fad', 'SBS/LEC/008', 'clock_in', '2026-06-09 22:30:25', 1, '2026-06-09 21:28:25'),
(6, 'a2c3ddd0e62e81d1e29f36fefb1614386aa4b6fd188fc5c6b0d71996a6c77a03', 'SBS/LEC/008', 'clock_in', '2026-06-09 22:56:09', 1, '2026-06-09 21:54:09'),
(7, '39d4931a33b9bb150ebfde744cdc28706a4f1165fd859eaea4e0f3d9772dfcbb', 'SBS/LEC/008', 'clock_in', '2026-06-09 22:58:33', 1, '2026-06-09 21:56:33'),
(8, '341f90e1a382d474b1375b039d5183f84ea2f82fe8678fc3efeac28c70230ade', 'SBS/LEC/008', 'clock_in', '2026-06-09 23:16:41', 1, '2026-06-09 22:14:41'),
(9, '488967c9ec98633e0d04a421d77e54438c57b0360c1e15913970a4f81a33218e', 'SBS/LEC/008', 'clock_in', '2026-06-09 23:16:53', 1, '2026-06-09 22:14:53'),
(10, '61d15511d03f68881eeef6a1dc9b20611d034fdd4e29714e4ba85f5d0841f932', 'SBS/LEC/008', 'clock_in', '2026-06-09 23:16:54', 1, '2026-06-09 22:14:54'),
(11, '88c11727d1dd0c03e97d6facd2df0368c2f3c542a83234c47b61e151a5fd9c76', 'SBS/LEC/008', 'clock_in', '2026-06-10 00:52:11', 1, '2026-06-09 23:50:11'),
(12, '3470c5e32c09bbc1e322fef57f59bf32c2aedf7aabc703022b8e6cd57195d3b9', 'SBS/LEC/008', 'clock_in', '2026-06-10 00:58:26', 1, '2026-06-09 23:56:26'),
(13, '9a5e4f735860f822d9ef07dddfa543f93869bf732456c0c4a202874f81960f63', 'SBS/LEC/008', 'clock_in', '2026-06-10 00:58:56', 1, '2026-06-09 23:56:56'),
(14, '34b723b6ac31b36d4c80c5b82c9606bfbdeca319fd1a55d17eb3fbcd72a2049b', 'SBS/LEC/008', 'clock_in', '2026-06-10 01:00:02', 1, '2026-06-09 23:58:02'),
(15, '5964e2fe0c837939cadbaa440aaa4f935bc7d889736467d974bd8664bb7be09c', 'SBS/LEC/008', 'clock_in', '2026-06-10 01:07:28', 1, '2026-06-10 00:05:28'),
(16, 'fc4de6a324046564f37b085248e824c928b14d4bc24d97d4b7fa16d716bce252', 'SBS/LEC/008', 'register', '2026-06-10 01:07:34', 1, '2026-06-10 00:05:34'),
(17, '0323454d0ca6205ceb11b37ad7da00f0cccaae5d0261aaaade80d5e0997faaea', 'SBS/LEC/008', 'register', '2026-06-10 01:07:35', 1, '2026-06-10 00:05:35'),
(18, '0709acdc38087cfa3c9bd51ad6f4097669bd45e58cacd9478892eab8249684f3', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:12:48', 1, '2026-06-10 08:10:48'),
(19, 'd6e5234e147fa7b96552b0706d4b77d7a12d80ec8b82788bbcb3ef3330178157', 'SBS/LEC/008', 'register', '2026-06-10 09:13:08', 1, '2026-06-10 08:11:08'),
(20, '2850c186dd87955918199c867e6996c61a0228cd343db6edf516cf93f7350286', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:13:10', 1, '2026-06-10 08:11:10'),
(21, 'bd902e8405f9aeab779438872144c0c7f3dbbfef7a2d08526358bedf34232aa3', 'SBS/LEC/008', 'register', '2026-06-10 09:13:12', 1, '2026-06-10 08:11:12'),
(22, '412518e3ef8dc139ea4e26e59ad0b4a3560d18d17f7643c2b69f83605d15a1a5', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:13:14', 1, '2026-06-10 08:11:14'),
(23, '0f575a9635ff28413aa03db2dfa2ba24021a47c96e0bf270856b7001f3fa4ad4', 'SBS/LEC/008', 'register', '2026-06-10 09:13:16', 1, '2026-06-10 08:11:16'),
(24, 'f40a9e1ce328e16256bbacc245580a1ff109827aa1f8a17977d7913c02e15a20', 'SBS/LEC/008', 'register', '2026-06-10 09:15:43', 1, '2026-06-10 08:13:43'),
(25, '84995d908670b7a27c1ace5c640176c08c9b4935f9770dc4768e3ac5723d245e', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:27:35', 1, '2026-06-10 08:25:35'),
(26, 'ccfdc5df21d699a85880405a8d933ea22407a32b12d9be56c3d89b2f386e4418', 'SBS/LEC/008', 'register', '2026-06-10 09:27:38', 1, '2026-06-10 08:25:38'),
(27, '75aa46704e668bb908233d6cce1d8b4a6c14566cb9b25ac7d14f99c7ba332332', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:52:09', 1, '2026-06-10 08:50:09'),
(28, '49cc906aaa2b724728b7a53f91481c3d7eedeb270c42eb7246696cb2681c92a0', 'SBS/LEC/008', 'register', '2026-06-10 09:52:13', 1, '2026-06-10 08:50:13'),
(29, '2746fd6e725f1b7d8f8a1c49cb5ebbb4ee2a6aee8a488c54355631dbb26ca24a', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:52:25', 1, '2026-06-10 08:50:25'),
(30, '81cc45758623290676560146b07dd34019ba687bd54a9901f909c2afe272ae09', 'SBS/LEC/008', 'register', '2026-06-10 09:52:31', 1, '2026-06-10 08:50:31'),
(31, 'e9014861756130a3acef0cf0ce917d119212e54bcfd57bf9c8868441147f81fd', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:52:37', 1, '2026-06-10 08:50:37'),
(32, 'afa91ae1abbd20612602461422cf65c87e0cb86c81bde26b00d8202d25f82c46', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:52:41', 1, '2026-06-10 08:50:41'),
(33, 'd8335041f11561cc45fae8f7dde55b095ebfddabf1875c5fdcc3d4d8a8c97fcf', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:52:56', 1, '2026-06-10 08:50:56'),
(34, '1283c9b7b6253fe45b449a6172f88f2e8a19b6b638dc190de931567ed8df0687', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:53:01', 1, '2026-06-10 08:51:01'),
(35, 'ce6f8de29363f088fd628d0edd81807edb7c3ede340e7ea689ffb60dc9fb2da8', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:53:38', 1, '2026-06-10 08:51:38'),
(36, '35a6e38878aa6a102628f8e8b75e26fb04c21e51eb3117db54d6f9ff676d07c6', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:53:50', 1, '2026-06-10 08:51:50'),
(37, 'a8db1dcabaa2d6a6cbf0403c4b6f46ea77d9a8ffc6048dc827b7c020b7d11d2b', 'SBS/LEC/008', 'register', '2026-06-10 09:54:32', 1, '2026-06-10 08:52:32'),
(38, '2c7644b79ebd7bee109c68c2d537f3ad6633fb473d927058d96422d5b3435332', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:55:19', 1, '2026-06-10 08:53:19'),
(39, '3b0d96923b62ca160a44ac3fbe5993c084811017628e2e5123409389449fada5', 'SBS/LEC/008', 'register', '2026-06-10 09:55:42', 1, '2026-06-10 08:53:42'),
(40, 'a90e9f930757d557345f93a89a2b7e3bcf211db733ede955ec4c54cc82ad3493', 'SBS/LEC/008', 'clock_in', '2026-06-10 09:58:58', 1, '2026-06-10 08:56:58'),
(41, 'fcc7f6a9574c90130b02cce52b1c258519c31c01471547ab587b41f5f1418a93', 'SBS/LEC/008', 'clock_in', '2026-06-10 10:01:57', 1, '2026-06-10 08:59:57'),
(42, '20839857c102179cda85d12e1f4a92847e48f7457e980301f354d9c0851cf8f0', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:20:04', 1, '2026-06-10 10:18:04'),
(43, 'baa9cf6ccbfd79e21df14364642f6f00db1f22b9cd19796b6ede9d4bfb6ed793', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:26:21', 1, '2026-06-10 10:24:21'),
(44, '756fe312ccc260460185143a978a5b48891f23fc066bb47a1032f8d346a90176', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:26:31', 1, '2026-06-10 10:24:31'),
(45, 'f76c5b5a8ec6dd5ec79aa680504888d3bec3373b83400cf22f045251329807bd', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:29:25', 1, '2026-06-10 10:27:25'),
(46, '90d64a046753d5cdf1520da4cd19c669c87adf134f368494e5d0181fcd690d03', 'SBS/LEC/008', 'register', '2026-06-10 11:29:28', 0, '2026-06-10 10:27:28'),
(47, '81f26e51ba08f3f2b2d1f1419ca462621b350a4a61d7ebadf73014d3c5c95db1', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:29:36', 1, '2026-06-10 10:27:36'),
(48, '15585904688bd151124efb1a166763ab95618ae5ce6cadd32552564e5b7b03cb', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:36:10', 1, '2026-06-10 10:34:10'),
(49, '84927b76adb8fa6005176106411d1d129b0ed26c7a35b00414ed60ccd8477bc2', 'SBS/LEC/008', 'clock_in', '2026-06-10 11:36:22', 1, '2026-06-10 10:34:22'),
(50, '0d2faf7604c8a750b13b004e660855dcedb2bd84ca38ac84d3778a683020df94', 'SBS/LEC/002', 'clock_in', '2026-06-10 12:24:28', 1, '2026-06-10 11:22:28'),
(51, 'b6c90e03607d613eeab15be0f975e108350cd12b7d38354ec3ffea26469efc11', 'SBS/LEC/008', 'clock_in', '2026-06-10 12:24:33', 0, '2026-06-10 11:22:33'),
(52, 'da529c7d8dbd0ab0bc33e8b421a7441f17a205a7409b132ec74731db72a255ba', 'SBS/LEC/002', 'clock_in', '2026-06-10 12:34:48', 0, '2026-06-10 11:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `webauthn_credentials`
--

CREATE TABLE `webauthn_credentials` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `credential_id` text NOT NULL,
  `public_key` text NOT NULL,
  `sign_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `webauthn_credentials`
--

INSERT INTO `webauthn_credentials` (`id`, `staff_id`, `credential_id`, `public_key`, `sign_count`, `created_at`) VALUES
(2, 'SBS/LEC/008', '64pr6GKmnLzZryzd7FQhIv4BltvBsXKKbaLMbA3aYi4', 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEnGuSFnkm6Mcts3XoX3pEhWzN6Hkx0TNiL-I-WOOxC1qvlZgBblegkHi4ozvp-ootkMF9lYZedU2_E1kISVB__Q', 0, '2026-06-10 08:54:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily` (`staff_id`,`att_date`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `employee` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD CONSTRAINT `qr_tokens_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `employee` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD CONSTRAINT `webauthn_credentials_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `employee` (`staff_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
