-- phpMyAdmin SQL Dump
-- version 4.8.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 25, 2019 at 10:38 AM
-- Server version: 5.7.23
-- PHP Version: 7.1.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `data_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `stripe_subscriptions`
--

CREATE TABLE `stripe_subscriptions` (
  `cod_id` int(11) NOT NULL,
  `subscr_id` varchar(50) NOT NULL,
  `subscr_status` varchar(20) NOT NULL,
  `subscr_created` datetime NOT NULL,
  `trial_start` datetime NOT NULL,
  `trial_end` datetime NOT NULL,
  `isTrial` tinyint(1) NOT NULL,
  `notification_type` varchar(60) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_update` datetime NOT NULL,
  `status_paid` tinyint(1) NOT NULL,
  `total_paid` int(11) NOT NULL,
  `currency_code` int(11) NOT NULL,
  `current_period_end` datetime NOT NULL,
  `current_period_start` datetime NOT NULL,
  `nickname_subscr` varchar(50) NOT NULL,
  `invoice_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `stripe_subscriptions`
--
ALTER TABLE `stripe_subscriptions`
  ADD PRIMARY KEY (`cod_id`),
  ADD KEY `subscr_id` (`subscr_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `stripe_subscriptions`
--
ALTER TABLE `stripe_subscriptions`
  MODIFY `cod_id` int(11) NOT NULL AUTO_INCREMENT;
