-- Extrait réduit d'un dump mysqldump, calqué sur storage/database/backup.sql.
-- Les identités sont fictives : le test vérifie précisément qu'aucune d'elles n'atteint la base.
--
-- Instruments : PUST.PA et CVX sont détenus, NVDA ne l'est pas et ne doit donc pas être valorisé.

INSERT INTO `users` VALUES (1,'Alice Dupont','alice.dupont@exemple-reel.fr','admin',NULL,'$2y$12$hashfictifadministrateur','tokenfictif1','2026-02-23 09:08:38','2026-02-23 09:46:43'),(2,'Bruno Martin','bruno.martin@exemple-reel.fr','user',NULL,'$2y$12$hashfictifutilisateur','tokenfictif2','2026-02-23 09:28:09','2026-02-23 09:45:13');
INSERT INTO `wallets` VALUES (1,1,'PEA','2026-03-16 17:47:26','2026-03-16 17:47:26'),(2,1,'CTO','2026-03-16 17:47:26','2026-03-16 17:47:26'),(4,2,'PEA','2026-03-16 17:47:26','2026-03-16 17:47:26');
INSERT INTO `securities` VALUES (1,'FR0011871110','PUST.PA','Amundi PEA Nasdaq-100 UCITS ETF Acc','2026-02-21 13:08:48','2026-03-03 16:05:37'),(3,'US1667641005','CVX','Chevron Corporation','2026-02-21 14:01:53','2026-03-03 12:55:12'),(22,'US67066G1040','NVDA','NVIDIA Corporation','2026-03-19 14:58:11','2026-03-19 14:58:11');
INSERT INTO `security_prices` VALUES (1,1,'2026-01-02',99.0000,101.0000,98.0000,100.0000,1000,NULL,NULL),(2,1,'2026-01-05',100.0000,112.0000,99.0000,110.0000,1200,NULL,NULL),(3,3,'2026-01-02',150.0000,156.0000,149.0000,155.0000,900,NULL,NULL),(4,22,'2026-01-02',10.0000,11.0000,9.0000,10.5000,500,NULL,NULL);
INSERT INTO `security_sectors` VALUES (1,1,'technology',0.536500,'2026-06-04 12:17:09','2026-06-04 12:17:09'),(2,1,'healthcare',0.041800,'2026-06-04 12:17:09','2026-06-04 12:17:09'),(3,3,'energy',1.000000,'2026-02-24 20:02:59','2026-06-06 00:00:07');
INSERT INTO `transactions` VALUES (1,1,1,'2026-01-02','buy',1,NULL,2.0000,100.0000,1.00,NULL,NULL,'2026-01-02 10:00:00','2026-01-02 10:00:00'),(2,1,1,'2026-01-05','buy',1,NULL,3.0000,110.0000,1.00,NULL,NULL,'2026-01-05 10:00:00','2026-01-05 10:00:00'),(3,1,2,'2026-01-06','buy',3,'IBKR',1.0000,155.0000,3.00,NULL,'Ligne d\'exemple','2026-01-06 10:00:00','2026-01-06 10:00:00'),(4,2,4,'2026-01-07','buy',3,NULL,4.0000,150.0000,2.00,NULL,NULL,'2026-01-07 10:00:00','2026-01-07 10:00:00');
INSERT INTO `wallet_fees` VALUES (2,2,'Flat tax',31.4000,'percentage','realized_gain',NULL,'2026-03-17 19:54:05','2026-03-17 19:54:05');
