
# BoothBookingManager

BoothBookingManager is a PHP-based application designed for managing booth reservations and user interactions within an event management system. The project utilizes the Slim Framework for backend functionality and integrates with a MySQL database. Users can reserve booths in designated event zones, track their booking status, and manage payments. Administrators have the ability to oversee and manage booth reservations and bookings based on payment verification.

## Features

Member:
- Registration and Login: Users can create an account and log in using their email and password.
- View Booths and Zones: Users can browse available booths and zones for a specific event.
- Make a Reservation: Users can reserve booths and receive a booking confirmation.
- Track Reservation Status: Users can track the status of their reservation, such as reserved, payment, approved, or canceled.
- Payment: Users can upload proof of payment to confirm their reservation, after which an admin can approve the booking.

Admins:
- Manage Bookings: Admins can view and approve or reject booth reservations. Admin approval is based on the payment status being marked as paid.
- Change Booth Status: Admins can update the booth status from available to booked once payment is confirmed and approved.
- View and Manage Users: Admins can view registered users, their booking history, and manage their access.
- Monitor Events: Admins can manage events, update zones and booths, and track all bookings for a given event.


## Installation

Install BoothBookingManager with PHP and Composer

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```
Install Slim Framework 

```bash
composer require slim/slim "^4.0"
composer require slim/psr7
```

Install Database
```bash
CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `booking_date` timestamp NULL DEFAULT NULL,
  `products` text DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `payment_price` int(11) NOT NULL,
  `payment_slip` varchar(255) DEFAULT NULL,
  `status` enum('reserve','canceled','payment','approve') DEFAULT 'reserve',
  `user_id` int(11) DEFAULT NULL,
  `booth_id` int(11) DEFAULT NULL,
  `event_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booths` (
  `booth_id` int(11) NOT NULL,
  `booth_name` varchar(255) NOT NULL,
  `booth_size` varchar(50) DEFAULT NULL,
  `booth_products` varchar(255) NOT NULL,
  `booth_status` enum('available','under_review','booked') DEFAULT 'available',
  `price` decimal(10,2) DEFAULT NULL,
  `zone_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(250) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `date_end` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `title` enum('Mr.','Mrs.','Ms.') NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('general','member','admin') DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `zones` (
  `zone_id` int(11) NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `zone_info` text DEFAULT NULL,
  `number_of_booths` int(11) NOT NULL,
  `event_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Run BoothBookingManager 

```bash
php -S localhost:8080 -t public
```

## Tech Stack

**Server:** PHP, Slim Framework , MySQL

## License

This project is a collaboration between Tadchai Phisutthisakulchai under the rights of Kasetsart University, Chalermphrakiat Sakon Nakhon Province Campus. It is intended for educational and research purposes only.

Reproduction, modification, or distribution of any part of this project for commercial purposes without written permission from all contributors is prohibited.
