-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2025 at 11:05 AM
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
-- Database: `event`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `event_date` date NOT NULL,
  `event_start_time` time DEFAULT NULL,
  `event_end_time` time DEFAULT NULL,
  `package_name` varchar(100) NOT NULL,
  `theme_name` varchar(100) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','partially_paid','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `booking_reference`, `event_date`, `event_start_time`, `event_end_time`, `package_name`, `theme_name`, `total_amount`, `special_requests`, `status`, `payment_status`, `created_at`, `updated_at`) VALUES
(1, 3, 'BK20250331075523645', '2025-03-31', '13:00:00', '17:00:00', 'birthday package', 'superheroes', 35500.00, '', 'completed', 'paid', '2025-03-31 05:55:23', '2025-04-01 11:29:20'),
(4, 2, 'BK20250401110622533', '2025-05-01', '08:00:00', '12:00:00', 'christening package', 'little prince / little princess', 47000.00, '', 'completed', 'paid', '2025-04-01 09:06:22', '2025-05-04 05:16:59'),
(6, 3, 'BK20250402043353588', '2025-08-27', '11:00:00', '21:00:00', 'birthday package', 'custom', 222000.00, '', 'confirmed', 'paid', '2025-04-02 02:33:53', '2025-05-05 15:13:11'),
(19, 11, 'BK20250419161046547', '2025-10-24', '09:00:00', '18:00:00', 'christening package', 'angelic / heaven’s blessing', 25000.00, '', 'cancelled', 'unpaid', '2025-04-19 14:10:46', '2025-05-18 06:49:08'),
(32, 22, 'BK20250428103753542', '2025-09-02', '09:00:00', '16:00:00', 'wedding package', 'fairytale fantasy', 45000.00, '', 'confirmed', 'paid', '2025-04-28 08:37:53', '2025-05-05 15:13:10'),
(41, 30, 'BK20250428113649634', '2025-05-22', '08:00:00', '05:00:00', 'Birthday Package', 'Superheroes', 50000.00, '', 'confirmed', 'paid', '2025-04-28 09:36:49', NULL),
(44, 22, 'BK20250504105126379', '2025-05-05', '09:00:00', '15:00:00', 'wedding package', 'fairytale fantasy', 45000.00, '', 'completed', 'partially_paid', '2025-05-04 08:51:26', '2025-05-05 12:15:10'),
(45, 35, 'BK20250505031339343', '2025-10-06', '09:00:00', '19:00:00', 'birthday package', 'superheroes', 35000.00, '', 'confirmed', 'paid', '2025-05-05 01:13:39', '2025-05-05 01:15:35'),
(47, 35, 'BK20250505145638803', '2025-05-27', '15:00:00', '22:00:00', 'christening package', 'little prince / little princess', 25000.00, '', 'cancelled', 'unpaid', '2025-05-05 12:56:38', '2025-05-05 12:57:58'),
(60, 35, 'BK20250507130800801', '2025-05-27', '16:00:00', '20:00:00', 'corporate event party', 'retro diner / rock n’ roll', 60000.00, '', 'confirmed', 'paid', '2025-05-07 11:08:00', '2025-05-07 11:14:59'),
(62, 35, 'BK20250507132601152', '2025-05-16', '10:00:00', '15:00:00', 'corporate event party', 'retro diner / rock n’ roll', 60000.00, '', 'cancelled', 'unpaid', '2025-05-07 11:26:01', '2025-05-07 11:32:45'),
(69, 11, 'BK20250508015854715', '2025-12-11', '09:00:00', '16:00:00', 'corporate event party', 'retro diner / rock n’ roll', 60000.00, '', 'confirmed', 'paid', '2025-05-07 23:58:54', '2025-05-07 23:59:41'),
(70, 30, 'BK20250513163753642', '2025-11-11', '08:00:00', '05:00:00', 'Christening Package', '', 40000.00, '', 'confirmed', 'paid', '2025-05-13 14:37:53', NULL),
(74, 22, 'BK20250518073029106', '2025-05-19', '11:00:00', '18:00:00', 'christening package', 'angelic / heaven’s blessing', 25000.00, '', 'cancelled', 'unpaid', '2025-05-18 05:30:29', '2025-05-18 06:49:08'),
(76, 35, 'BK20250518075206357', '2025-05-18', '14:00:00', '21:00:00', 'wedding package', 'fairytale fantasy', 45000.00, '', 'confirmed', 'paid', '2025-05-18 05:52:06', '2025-05-18 05:53:09'),
(78, 22, 'BK20250518145547904', '2025-05-19', '15:00:00', '21:00:00', 'birthday package', 'superheroes', 35000.00, '', 'cancelled', 'unpaid', '2025-05-18 06:55:47', '2025-05-18 08:01:08'),
(80, 22, 'BK20250518160252109', '2025-10-13', '09:00:00', '18:00:00', 'christening package', 'teddy bear picnic', 25000.00, '', 'cancelled', 'unpaid', '2025-05-18 08:02:52', '2025-05-18 09:04:02');

-- --------------------------------------------------------

--
-- Table structure for table `booking_services`
--

CREATE TABLE `booking_services` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_services`
--

INSERT INTO `booking_services` (`id`, `booking_id`, `service_name`, `service_price`) VALUES
(10, 1, 'photography', 500.00),
(17, 4, 'event host (emcee)', 10000.00),
(18, 4, 'entertaiment acts', 12000.00),
(23, 6, 'live band', 15000.00),
(24, 6, 'photography', 10000.00),
(25, 6, 'videography', 20000.00),
(26, 6, 'event host (emcee)', 10000.00),
(27, 6, 'entertaiment acts', 12000.00),
(28, 6, 'photo booth', 120000.00),
(53, 32, 'photo booth', 12000.00),
(62, 41, 'Live Band', 15000.00),
(65, 44, 'live band', 15000.00),
(66, 45, 'live band', 15000.00),
(67, 45, 'videography', 20000.00),
(68, 45, 'photo booth', 12000.00),
(84, 70, 'Live Band', 15000.00),
(86, 74, 'live band', 15000.00),
(87, 76, 'live band', 15000.00),
(88, 78, 'live band', 15000.00),
(89, 80, 'photo booth', 12000.00),
(90, 80, 'catering', 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `booking_service_items`
--

CREATE TABLE `booking_service_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_service_items`
--

INSERT INTO `booking_service_items` (`id`, `booking_id`, `service_item_id`, `created_at`) VALUES
(12, 32, 5, '2025-04-28 08:37:53'),
(27, 41, 1, '2025-04-28 09:36:49'),
(29, 44, 1, '2025-05-04 08:51:26'),
(30, 45, 5, '2025-05-05 01:13:39'),
(38, 70, 1, '2025-05-13 14:37:53'),
(40, 74, 1, '2025-05-18 05:30:29'),
(41, 76, 1, '2025-05-18 05:52:06'),
(42, 78, 1, '2025-05-18 06:55:47'),
(43, 80, 5, '2025-05-18 08:02:52');

-- --------------------------------------------------------

--
-- Table structure for table `event_packages`
--

CREATE TABLE `event_packages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,0) DEFAULT NULL,
  `guest_capacity` int(11) DEFAULT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in hours',
  `description` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_packages`
--

INSERT INTO `event_packages` (`id`, `name`, `price`, `guest_capacity`, `duration`, `description`, `inclusions`, `exclusions`, `terms`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Wedding Package', 45000, 150, 6, 'Romantic and elegant wedding celebration designed for a stress-free experience.', 'Catering – Buffet for guests with 3-course meal\r\nAudio & Visual – Sound system, LED lights, and microphone\r\nEvent Coordination – Dedicated event planner and service staff\r\nExtras – Complimentary parking for guests', 'adsads', 'A 50% deposit is required upon booking to secure the date.\r\nPayments are non-transferable to other events or dates.\r\nAny damage to the venue, equipment, or decorations caused by guests will be charged to the client.\r\n\r\n\r\n📌 By booking this package, you agree to the terms & conditions above.', 'uploads/events/1743001895_2.jpg', 'active', '2025-03-16 01:17:02', '2025-04-19 05:53:36'),
(2, 'Birthday Package', 35000, 100, 2, 'A fun and stress-free birthday celebration with delicious food, stylish decorations, and a lively atmosphere.', 'Catering – Buffet for guests with a selection of main dishes and desserts\r\nThemed Decorations – Balloon arrangements, table settings, and a birthday backdrop\r\nEntertainment – Basic sound system, LED lights, and microphone\r\nHost/Emcee – To keep the party exciting and interactive\r\nEvent Staff – Dedicated waitstaff and event coordinator', 'adsasd', 'Payments are non-transferable to other events or dates.\r\nAny damage to the venue, equipment, or decorations caused by guests will be charged to the client.\r\nThe venue is not responsible for lost or stolen personal items.\r\n\r\n\r\n📌 By booking this package, you agree to the terms & conditions above.', 'uploads/events/1743001856_1.jpg', 'active', '2025-03-16 10:50:06', '2025-04-19 05:53:32'),
(3, 'Corporate Event Party', 60000, 150, 5, 'A professional and well-organized corporate event package tailored for business meetings, conferences, or company celebrations.', 'Setup & Branding – Conference-style, banquet, or theater seating with corporate branding options (banners, screens)\r\nAudio-Visual Equipment – Sound system, microphone, LED screen/projector for presentations\r\nTechnical Support – On-site technicians for A/V setup and troubleshooting\r\nEvent Coordination – Dedicated corporate event manager and service staff\r\nCatering – Buffet for guests with a selection of main dishes, desserts, and refreshments', 'asdads', 'Payments are non-transferable to other events or dates.\r\nNo wall drilling, taping, or damaging fixtures is allowed.\r\nAny damage to the venue, equipment, or decorations caused by guests will be charged to the client.\r\nThe venue is not responsible for lost or stolen personal items.\r\n\r\n📌 By booking this package, you agree to the terms & conditions above.', 'uploads/events/1743002549_e-3.jpg', 'active', '2025-03-16 11:31:52', '2025-04-19 05:53:28'),
(7, 'Christening Package', 25000, 50, 0, 'A beautifully arranged christening celebration perfect for welcoming your little one with family and friends.', 'Catering – Buffet for guests with a selection of main dishes and desserts\r\nThemed Decorations – Balloon arrangements, table settings, and a backdrop for photos\r\nChristening Cake – One-tier custom cake\r\nAudio-Visual Setup – Basic sound system and microphone\r\nEvent Coordination – On-site event manager and service staff', NULL, 'Payments are non-transferable to other events or dates.\r\nClients may bring additional decorations, but no wall drilling, taping, or damaging fixtures.\r\nAny damage to the venue, equipment, or decorations caused by guests will be charged to the client.\r\nThe venue is not responsible for lost or stolen personal items.\r\n\r\n📌 By booking this package, you agree to the terms & conditions above.', 'uploads/events/1743035446_e-4.jpg', 'active', '2025-03-27 00:30:46', '2025-05-07 07:42:47'),
(8, 'Debut Package', 50000, 50, 0, 'Celebrate an unforgettable 18th birthday with our all-inclusive Debut Package! This package covers everything you need for a magical and elegant celebration, from venue setup to entertainment and catering. Let us take care of the details while you enjoy your special day!', 'Catering: Full-course meal or buffet with drinks\r\nDebut Essentials: 18 Roses, 18 Candles, 18 Treasures setup\r\nMakeup & Hair Styling: For the debutante\r\nVenue: Decoration, tables, chairs, stage setup\r\n', NULL, 'Payments are non-transferable to other events or dates.\r\nAny damage to the venue, equipment, or decorations caused by guests will be charged to the client.\r\nThe venue is not responsible for lost or stolen personal items.\r\n\r\n\r\n\r\n📌 By booking this package, you agree to the terms & conditions above.', 'uploads/events/1743557258_75210688_2491981627740885_6646236026819837952_n.jpg', 'active', '2025-04-02 01:27:38', '2025-05-07 23:37:16');

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `unique_code` varchar(255) DEFAULT NULL,
  `invitation_sent` tinyint(1) NOT NULL DEFAULT 0,
  `invitation_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`id`, `booking_id`, `name`, `email`, `phone`, `unique_code`, `invitation_sent`, `invitation_sent_at`, `created_at`, `updated_at`) VALUES
(19, 69, 'John Hanssen N. Eya', 'eddiemagtanggol@gmail.com', '9629737496', 'ES631203CG', 1, '2025-05-13 13:32:23', '2025-05-07 23:58:50', '2025-05-13 13:32:23'),
(20, 69, 'Mike Acenas', 'mikeacenas2715@gmail.com', '9512315612', 'XC090911IN', 1, '2025-05-13 13:32:26', '2025-05-07 23:58:50', '2025-05-13 13:32:26'),
(23, 74, 'John Hanssen N. Eya', 'eddiemagtanggol@gmail.com', '9629737496', 'YT179425UG', 0, NULL, '2025-05-18 05:30:26', '2025-05-18 05:30:29'),
(24, 74, 'Mike Acenas', 'mikeacenas2715@gmail.com', '9512315612', 'FQ923858GW', 0, NULL, '2025-05-18 05:30:26', '2025-05-18 05:30:29'),
(25, 76, 'John Hanssen N. Eya', 'eddiemagtanggol@gmail.com', '9629737496', 'GC501320MD', 1, '2025-05-18 05:53:21', '2025-05-18 05:52:03', '2025-05-18 05:53:21'),
(26, 76, 'Mike Acenas', 'mikeacenas2715@gmail.com', '9512315612', 'VB887831PX', 1, '2025-05-18 05:53:25', '2025-05-18 05:52:03', '2025-05-18 05:53:25'),
(27, 78, 'John Hanssen N. Eya', 'eddiemagtanggol@gmail.com', '9629737496', 'GE771714BZ', 0, NULL, '2025-05-18 06:55:45', '2025-05-18 06:55:47'),
(28, 78, 'Mike Acenas', 'mikeacenas2715@gmail.com', '9512315612', 'JM807265OZ', 0, NULL, '2025-05-18 06:55:45', '2025-05-18 06:55:47'),
(29, 80, 'John Hanssen N. Eya', 'eddiemagtanggol@gmail.com', '9629737496', 'TF644322FP', 0, NULL, '2025-05-18 08:02:49', '2025-05-18 08:02:52'),
(30, 80, 'Mike Acenas', 'mikeacenas2715@gmail.com', '9512315612', 'VD573790NQ', 0, NULL, '2025-05-18 08:02:49', '2025-05-18 08:02:52');

-- --------------------------------------------------------

--
-- Table structure for table `guest_attendance`
--

CREATE TABLE `guest_attendance` (
  `id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `checked_in_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guest_attendance`
--

INSERT INTO `guest_attendance` (`id`, `guest_id`, `booking_id`, `check_in_time`, `checked_in_by`, `notes`) VALUES
(3, 26, 76, '2025-05-18 06:12:46', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_transactions`
--

INSERT INTO `payment_transactions` (`id`, `booking_id`, `amount`, `payment_method`, `account_name`, `phone_number`, `status`, `reference_number`, `receipt_path`, `created_at`, `updated_at`) VALUES
(1, 1, 36000.00, 'cash', NULL, NULL, 'paid', NULL, NULL, '2025-03-31 05:57:47', NULL),
(4, 4, 69000.00, 'gcash', 'Kyla Ampo', '09922030939', 'paid', NULL, '../uploads/receipts/receipt_67ebace431a25.png', '2025-04-01 09:07:48', NULL),
(6, 6, 204500.00, 'gcash', 'MIke Acenas', '09626125986', 'paid', NULL, '../uploads/receipts/receipt_67eca24ec1479.png', '2025-04-02 02:34:54', NULL),
(23, 32, 28500.00, 'paymaya', 'Sample Name', '09214892131', 'paid', NULL, '../uploads/receipts/receipt_680f3ede14315.jpg', '2025-04-28 08:39:58', NULL),
(25, 41, 50000.00, 'cash', NULL, NULL, 'paid', NULL, NULL, '2025-04-28 09:36:49', NULL),
(28, 44, 30000.00, 'gcash', 'Sample Name', '09214892131', 'paid', NULL, '../uploads/receipts/receipt_68172abe76653.png', '2025-05-04 08:52:14', NULL),
(29, 45, 82000.00, 'gcash', 'Arnel ROmero', '09629737496', 'paid', NULL, '../uploads/receipts/receipt_68181137b133e.jpg', '2025-05-05 01:15:35', NULL),
(32, 32, 45000.00, 'cash', NULL, NULL, '', NULL, NULL, '2025-05-05 15:13:10', NULL),
(33, 6, 222000.00, 'cash', NULL, NULL, '', NULL, NULL, '2025-05-05 15:13:11', NULL),
(37, 60, 60000.00, 'gcash', 'John Hanssen N. Eya', '09214892131', 'paid', NULL, '../uploads/receipts/receipt_681b40b3b393c.jpg', '2025-05-07 11:14:59', NULL),
(41, 69, 60000.00, 'paymaya', 'John Hanssen N. Eya', '9949092463', 'paid', NULL, '../uploads/receipts/receipt_681bf3ed08684.jpg', '2025-05-07 23:59:41', NULL),
(42, 70, 40000.00, 'cash', NULL, NULL, 'paid', NULL, NULL, '2025-05-13 14:37:53', NULL),
(45, 76, 60000.00, 'paymaya', 'Arnel Romero', '09922030939', 'paid', NULL, '../uploads/receipts/receipt_682975c554e16.webp', '2025-05-18 05:53:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `review_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `package_id`, `rating`, `review_text`, `created_at`) VALUES
(8, 2, 1, 5, 'The wedding was absolutely beautiful and filled with so much love and joy. Every detail, from the stunning décor to the heartfelt ceremony, was thoughtfully planned and made the day feel truly magical. It was such a privilege to witness such a special moment and celebrate the start of a new chapter with so many wonderful people. Wishing the newlyweds a lifetime of happiness, laughter, and love—thank you for letting us be part of such an unforgettable day💍❤️.', '2025-04-24 02:58:44'),
(10, 11, 8, 5, 'The debut was a truly unforgettable celebration, filled with elegance, joy, and love. Every moment—from the grand entrance to the heartfelt speeches—was beautifully planned and perfectly captured the spirit of such a special milestone. It was amazing to witness the debutante shine with grace and confidence, surrounded by family and friends who clearly adore her. Wishing her all the best as she steps into this exciting new chapter of her life!', '2025-04-24 02:59:52'),
(11, 34, 7, 5, 'It\'s nice and cool wow', '2025-05-05 01:00:44'),
(12, 35, 2, 1, 'kalain', '2025-05-05 06:26:31');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `created_at`) VALUES
(10, 'Live Band', '2025-04-19 07:31:15'),
(11, 'Videography', '2025-04-19 07:36:31'),
(12, 'Photography', '2025-04-19 07:38:22'),
(13, 'Host(Emcee)', '2025-04-19 07:38:31'),
(14, 'Photo Booth', '2025-04-19 07:38:42'),
(15, 'Entertainment Acts', '2025-04-19 07:38:50'),
(16, 'Catering', '2025-05-05 05:32:39');

-- --------------------------------------------------------

--
-- Table structure for table `service_items`
--

CREATE TABLE `service_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `price_range` varchar(100) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_items`
--

INSERT INTO `service_items` (`id`, `name`, `phone`, `email`, `price_range`, `image_path`, `service_id`, `service_type`, `created_at`, `updated_at`) VALUES
(1, 'Agaw Band', '09541251215', 'agawband@gmail.com', '15,000', '476229831_1059937392816743_9157507941706404720_n.jpg', 10, 'band', '2025-04-19 07:42:53', '2025-04-19 07:42:53'),
(2, 'BlancSpace Creative Studio ', '09621413525', 'BlancSpace@gmail.com', '20,000', '480338963_1275855793898192_7543433790394981097_n.jpg', 11, 'generic', '2025-04-19 07:47:30', '2025-04-19 07:47:30'),
(3, 'BlancSpace Creative Studio', '09621413525', 'BlancSpace@gmail.com', '20,000', '1745048924_480338963_1275855793898192_7543433790394981097_n.jpg', 12, 'photographer', '2025-04-19 07:48:44', '2025-04-19 07:48:44'),
(4, 'Missfire Janeo', '09510151226', 'janeo@gmail.com', '10,000', '470222697_10162054880544547_1822246352232944164_n.jpg', 13, 'generic', '2025-04-19 07:50:29', '2025-04-19 07:50:29'),
(5, 'Wenz Photobooth', '09632152112', 'wenz@gmail.com', '12,000', '462451590_122160738878050091_1651856993400719893_n.jpg', 14, 'photographer', '2025-04-19 07:51:23', '2025-04-19 07:51:23'),
(6, 'Magic Entertainment  ', '09512512122', 'magic@gmail.com', '10,000', '461962799_515457154574949_6040580408550904689_n.jpg', 15, 'generic', '2025-04-19 07:52:46', '2025-04-19 07:52:46'),
(7, 'Jo\'s Dinner ', '09103793664', 'josdinner@gmail.com', '₱10,000', 'catering.jpg', 16, 'generic', '2025-05-05 05:35:02', '2025-05-05 05:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_name`, `setting_value`, `updated_at`) VALUES
(1, 'maintenance_mode', '0', '2025-05-04 13:25:24');

-- --------------------------------------------------------

--
-- Table structure for table `themes`
--

CREATE TABLE `themes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `packages` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `themes`
--

INSERT INTO `themes` (`id`, `name`, `description`, `packages`, `image_path`, `created_at`) VALUES
(2, 'Rustic Barn', 'A warm and charming countryside setup with wooden accents, string lights, and earthy tones.', '1', 'DSC_8272-scaled-1.jpg', '2025-03-31 05:27:45'),
(3, 'Fairytale Fantasy', 'A magical and whimsical setting with enchanted décor, soft pastels, and a royal touch.', '1', 'website-fairytaleindoorwedding-profile.jpg', '2025-03-31 05:36:34'),
(4, 'Modern Minimalist', 'A sleek and elegant event with clean lines, neutral colors, and sophisticated simplicity.', '1', 'Wedding-reception.jpeg', '2025-03-31 05:37:19'),
(5, 'Carnival / Circus', 'A fun-filled event with bright colors, carnival games, and circus-style entertainment.', '2', 'ac098ba35985432fd8dcc358bbe26324.jpg', '2025-03-31 05:38:11'),
(6, 'Superheroes', 'An action-packed theme with bold decorations, comic-style props, and heroic adventures.', '2', '1562151788_large.jpg', '2025-03-31 05:39:13'),
(7, 'Royal Princess or Prince', 'A regal celebration with elegant décor, crowns, and a fairytale royal setting.', '2', 'images.jpg', '2025-03-31 05:40:24'),
(8, 'Black & White Gala', 'A classy and timeless event with a monochrome color scheme and elegant décor.', '3', 'Black-and-white-room-Decor-1024x683.jpg', '2025-03-31 06:00:00'),
(9, 'Retro Diner / Rock n’ Roll', 'A nostalgic throwback with jukeboxes, checkered floors, and ‘50s rock vibes.\r\n\r\n', '3', 'b001ce2c-0a98-4e7c-8490-522c7b433aaf.webp', '2025-03-31 06:01:37'),
(10, 'Eco-Friendly / Green Theme', 'A sustainable event with nature-inspired décor, recycled materials, and organic elements.', '3', 'Eventologists-Illuminated-Themed-Event-Green-Foliage-Neon-Tunnel-Hire-1024x683.jpg', '2025-03-31 06:06:11'),
(11, 'Angelic / Heaven’s Blessing', 'A serene and heavenly celebration with white, gold, and soft pastel décor, symbolizing purity and blessings.', '7', 'photo-wall-featuring-angel-wings-white-gold-balloons-hall-restaurant-celebrating-baptism-concept-366815540.webp', '2025-03-31 06:07:53'),
(12, 'Little Prince / Little Princess', 'A royal-themed event with charming storybook elements, crowns, and elegant decorations fit for a young prince or princess.', '7', '301bf5e0580f44caaef2a083782fb001-goods.webp', '2025-03-31 06:14:40'),
(13, 'Teddy Bear Picnic', 'A cozy and playful gathering with picnic-style setups, plush teddy bears, and soft pastel accents for a sweet and heartwarming atmosphere.', '7', '12698658_984120421636527_2564015385781742207_o-1024x681.jpg', '2025-03-31 06:15:30'),
(14, ' Classic Cinderella', 'The Classic Cinderella theme brings a fairytale atmosphere to life, featuring elegant decor inspired by the iconic story. Think sparkling glass slippers, fairy-tale chandeliers, soft pastel colors, and twinkling lights, creating a magical and regal setting. ', '8', '1743558948_1.jpg', '2025-04-02 01:55:29'),
(15, 'Hollywood Glam', 'Radiates sophistication and star-studded luxury. With a red carpet entrance, gold accents, and glitzy décor, this theme brings the allure of Tinseltown to life.', '8', '3.jpg', '2025-04-02 02:05:06'),
(16, 'Alice in Wonderland', 'Brings the whimsical and fantastical world of Lewis Carroll’s classic tale to life. With oversized flowers, vibrant tea party settings, and quirky decor, the venue is transformed into a magical, upside-down world.', '8', '5.jpg', '2025-04-02 02:11:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `birthday` date NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `birthday`, `password`, `created_at`) VALUES
(2, 'Kyla Ampo', 'kyla@gmail.com', '09123123321', '1998-11-11', '$2y$10$G3dC5keuepThnfXi6ndCquzs2XbV7.w7NSxJdfdig9Yhck9XEf3M2', '2025-03-31 03:12:11'),
(3, 'Mike Acenas', 'mike@gmail.com', '09123123412', '1996-07-18', '$2y$10$xUbk/DQcw1wwXhCwYVce8uwnTQIIGhVYi1SbKnlfgJwdg3qZ2hL96', '2025-03-31 03:14:42'),
(11, 'Beverly Batisanan', 'beverly@gmail.com', '09856561079', '2025-10-09', '$2y$10$SLW7.2X5ukyO/Nzx4kWef.Ia2TfbtdLLNjc32Mb/Jr8ssY5ECoDya', '2025-04-19 14:09:55'),
(22, 'Emil Jan Abordo', 'bords@gmail.com', '09949092463', '2025-04-28', '$2y$10$cnGaZyquQf/Znbt13DwiqOFNlfi35cM0tiXdRX7pTRmaC7k2dckOC', '2025-04-28 08:35:15'),
(30, 'John Hanssen Eya', 'mikeacenas2715@gmail.com', '09624114552', '2025-04-28', '$2y$10$ZuL88y.E60.kI/Nbrb2Lnuovd4Q8E6ECq/i3Cnq1zBxGI788Zw5Hi', '2025-04-28 09:24:43'),
(34, 'Laurencce Papna', 'papna@gmail.com', '09166290960', '2000-06-16', '$2y$10$Hzxplte10ph2q2uSij/AKu/jlhnd7dglzE5IScZO44RxJtskTrsdG', '2025-05-05 00:58:45'),
(35, 'Arnel Romero', 'arnel@gmail.com', '09758942745', '2009-05-13', '$2y$10$qicUtd90z3Bfle9crcG5G.7y7k2goVcWgKZr9z5FgYKibtgUARcXq', '2025-05-05 01:10:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `booking_services`
--
ALTER TABLE `booking_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `booking_service_items`
--
ALTER TABLE `booking_service_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`,`service_item_id`),
  ADD KEY `service_item_id` (`service_item_id`);

--
-- Indexes for table `event_packages`
--
ALTER TABLE `event_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_qr_code` (`unique_code`(191));

--
-- Indexes for table `guest_attendance`
--
ALTER TABLE `guest_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `package_id` (`package_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_items`
--
ALTER TABLE `service_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `booking_services`
--
ALTER TABLE `booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `booking_service_items`
--
ALTER TABLE `booking_service_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `event_packages`
--
ALTER TABLE `event_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `guest_attendance`
--
ALTER TABLE `guest_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `service_items`
--
ALTER TABLE `service_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=700;

--
-- AUTO_INCREMENT for table `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_services`
--
ALTER TABLE `booking_services`
  ADD CONSTRAINT `booking_services_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_service_items`
--
ALTER TABLE `booking_service_items`
  ADD CONSTRAINT `booking_service_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_service_items_ibfk_2` FOREIGN KEY (`service_item_id`) REFERENCES `service_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guests`
--
ALTER TABLE `guests`
  ADD CONSTRAINT `guests_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `event_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_items`
--
ALTER TABLE `service_items`
  ADD CONSTRAINT `service_items_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
