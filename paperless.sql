START TRANSACTION;
 
CREATE TABLE `tblPaperlessView` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL DEFAULT '0',
  `view` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tblDocumentLinks_user` (`userID`),
  CONSTRAINT `tblPaperlessView_user` FOREIGN KEY (`userID`) REFERENCES `tblUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

COMMIT;
