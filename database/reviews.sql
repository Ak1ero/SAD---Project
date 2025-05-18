-- phpMyAdmin SQL Dump
-- Table structure for table `reviews`

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `review_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `package_id` (`package_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `event_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample data for testing (optional)
INSERT INTO `reviews` (`user_id`, `package_id`, `rating`, `review_text`, `created_at`) VALUES
(1, 1, 5, 'Our wedding day was absolutely perfect! The Barn & Backyard team took care of everything, and the venue looked stunning. Highly recommend their wedding package for anyone looking to have a stress-free and beautiful celebration.', '2025-04-15 08:30:00'),
(2, 2, 4, 'The birthday party for my son was amazing! The decorations were exactly what we wanted, and the staff was very attentive. The only reason I'm giving 4 stars instead of 5 is because the event ran a bit behind schedule.', '2025-04-16 14:45:00'),
(3, 3, 5, 'Our corporate event was a huge success! The venue was perfect, and the team handled all the technical aspects flawlessly. Our clients were impressed, and we'll definitely be booking again for future events.', '2025-04-17 11:20:00'),
(4, 7, 5, 'The christening package exceeded our expectations! The decorations were beautiful, and the staff was incredibly helpful. Our guests couldn't stop complimenting the venue and the food. Will definitely recommend to friends and family!', '2025-04-18 09:15:00'); 