SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `eyeos`
--

-- --------------------------------------------------------

--
-- Table structure for table `token`
--

CREATE TABLE IF NOT EXISTS `token` (
  `userID` varchar(128) NOT NULL,
  `tkey` varchar(25) NOT NULL,
  `tsecret` varchar(25) NOT NULL,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `tkey` (`tkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

