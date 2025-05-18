-- Drop existing tables if they exist
DROP TABLE IF EXISTS bands;
DROP TABLE IF EXISTS photographers;
DROP TABLE IF EXISTS service_items;

-- Single table for all service items
CREATE TABLE IF NOT EXISTS service_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    price_range VARCHAR(100),
    image_path VARCHAR(255),
    service_id INT,
    service_type VARCHAR(50),  -- 'band', 'photographer', 'generic', etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 

-- Table to store booking service items relationships
CREATE TABLE IF NOT EXISTS booking_service_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    service_item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_item_id) REFERENCES service_items(id) ON DELETE CASCADE,
    UNIQUE KEY (booking_id, service_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 