CREATE TABLE `my_table` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `field_a` int(11) DEFAULT NULL,
  `field_b` varchar(255) DEFAULT NULL,
  `field_c` text DEFAULT NULL,
  `field_d` decimal(12,4) DEFAULT NULL,
  `field_e` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
