<?php

	/* 
	 * phpBasics
	 * ---------
	 * 
	 * Script:        nCounter
	 * 
	 * Version:       1.0
	 * Release:       01.10.2019
	 * 
	 * Author:        numaek   
	 * Copyright (c): 2004-2019 by www.numaek.de
	 * 
	 * *************************************************************************************************************************************************************************************************
	 */


	// nCounter Konfiguration
	// ======================

	define('NCOUNTER_DB_DATABASE',  'phpbasics');			// Datenbank Name
	define('NCOUNTER_DB_HOST',      'localhost');			// Datenbank Hostname
	define('NCOUNTER_DB_USER',      'root');			// Datenbank Username
	define('NCOUNTER_DB_PASS',      '');				// Datenbank Passwort

	define('NCOUNTER_DB_TAB_DATA',  'ncounter_data');		// Tabellenname für Zählwerte	- wird automatisch erzeugt!
	define('NCOUNTER_DB_TAB_IP',    'ncounter_ip');			// Tabellenname für IP-Adressen - wird automatisch erzeugt!

	define('NCOUNTER_RELOADSPERRE', 1800);				// Reloadsperre Counter & Useronline in Sekunden
	define('NCOUNTER_STELLEN',      5);				// Anzahl der Stellen - default-Wert, falls nichts angegeben wird


	// =================================================================================================================================================================================================


	class nCounter
	{
		/*
		 * Methoden:
		 * =========
		 * 
		 * MEINCOUNTER = new nCounter(SEITENNAME);		// Klasse initialisieren und zählen, SEITENNAME = beliebiger Name des Counters. Neue werden automatisch erzeugt.
		 * 
		 * MEINCOUNTER->show(WERT, STELLEN);			// Zeigt den Wert des Counters von SEITENNAME. Mögliche Werte: 'visits', 'views' oder 'online' - Stellen werden mit Nullen gefüllt.
		 * 
		 * nCounter::showAll(WERT, STELLEN);			// Zeigt den Wert aller Counters zusammen.     Mögliche Werte: 'visits', 'views' oder 'online' - Stellen werden mit Nullen gefüllt.
		 * 
		 * nCounter::showList();				// Zeigt eine Liste aller Counterwerte.
		 * 
		 * nCounter::setValue(SEITENNAME, MODUS, WERT)		// Gibt einem Counter einen Wert vor. MODUS = 'visits' oder 'views'
		 * 
		 * nCounter::deleteSite(SEITENNAME)			// Löscht den Counter einer Seite
		 * 
		 * nCounter::uninstall()				// Löscht die Tabellen aller Counter aus der Datenbank
		 * 
		 * 
		 * Eine Demo befindet sich unterhalb dieser Klasse!
		 * ------------------------------------------------
		 * 
		 */


		public $db;

		public $site              = "";
		public $time              = 0;

		public $visits            = 0;
		public $views             = 0;
		public $online            = 0;

		public static $reloadTime = NCOUNTER_RELOADSPERRE;
		public static $length     = NCOUNTER_STELLEN;


		public function __construct($page)
		{
			$this->site = $page;
			$this->time = time();

			try
			{
				// Falls die Datenbankverbinmdung steht, weiter zum Zählen
				// =======================================================
				$this->db = self::db_connect();
				$this->count();

			} catch (PDOException $e)
			  {
				return false;
			  }
		}


		// Hauptfunktion: zählen der Besucher
		// **********************************
		public function count()
		{
			/* 
			 * Die IP eines neuen Besuchers wird mit einem Zeitstempel gespeichert und es wird ein neuer Besuch sowie ein Seitenaufruf erfasst.
			 * Ist die IP es Besuchers bereits gespeichert, handelt es sich nur um einen weiteren Seitenaufruf.
			 * 
			 * Nach der Ablaufzeit der Reloadsperre werden die abgelaufenen IP-Adressen wieder gelöscht.
			 * 
			 * Die derzeitgen Onlineuser ergeben sich somit aus der Anzahl der aktuell gespeicherten IP-Adressen.
			 * 
			 */


			// Aktuelle Daten des Counters auslesen
			// ====================================
			    $counterData = $this->db->query("SELECT * FROM `".NCOUNTER_DB_TAB_DATA."` WHERE `site` = '".$this->site."' LIMIT 0,1")->fetch();
			if( $counterData == true )
			{
				$this->visits = $counterData['visits'];
				$this->views  = $counterData['views'];
			} else
			  {
				$this->visits = 0;
				$this->views  = 0;
			  }


			// Abgelaufene IP-Adressen aller Counter löschen
			// =============================================
			$this->db->query("DELETE FROM `".NCOUNTER_DB_TAB_IP."` WHERE `time` < '".( $this->time - self::$reloadTime )."'");


			// IP-Adressen für Reloadsperre speichern oder die Zeit aktualisieren
			// ==================================================================
			    $checkMyIp = $this->db->query("SELECT * FROM `".NCOUNTER_DB_TAB_IP."` WHERE `ip` = '".$_SERVER['REMOTE_ADDR']."' && `site` = '".$this->site."' LIMIT 0,1")->fetch();
			if( $checkMyIp == true )
			{
				// Alter Besucher
				$this->db->query("UPDATE `".NCOUNTER_DB_TAB_IP."` SET `time` = '".$this->time."' WHERE `id` = '".$checkMyIp['id']."'");

				$this->views++;
			} else
			  {
				// Neuer Besucher
				$sql = "INSERT INTO `".NCOUNTER_DB_TAB_IP."`
					(`id`, `ip`, `time`, `site`)
				 VALUES (NULL, '".$_SERVER['REMOTE_ADDR']."', '".$this->time."', '".$this->site."')";

				$this->db->query($sql);

				$this->visits++;
				$this->views++;
			  }


			// Neue Werte speichern, neuen Counter ggf. automatisch erzeugen
			// =============================================================
			if( $counterData == true )
			{
				$this->db->query("UPDATE `".NCOUNTER_DB_TAB_DATA."` SET `visits` = '".$this->visits."', `views` = '".$this->views."' WHERE `site` = '".$this->site."'");
			} else
			  {
				$sql = "INSERT INTO `".NCOUNTER_DB_TAB_DATA."`
					(`id`, `site`, `visits`, `views`)
				 VALUES (NULL, '".$this->site."', '".$this->visits."', '".$this->views."')";

				$this->db->query($sql);
			  }
		}


		// Zeigt einen ausgewählten Counter einer Site an
		// **********************************************
		public function show($mode, $digits = -1 )
		{
			// User auf der gewählten $site zählen
			// ===================================
			$useronline = $this->db->query("SELECT count(id) AS `online` FROM `".NCOUNTER_DB_TAB_IP."` WHERE `site` = '".$this->site."'")->fetch();


			// Aktuelle Daten des Counters auslesen
			// ====================================
			    $counterData = $this->db->query("SELECT * FROM `".NCOUNTER_DB_TAB_DATA."` WHERE `site` = '".$this->site."' LIMIT 0,1")->fetch();
			if( $counterData == true )
			{
				$this->visits = $counterData['visits'];
				$this->views  = $counterData['views'];
			} else
			  {
				$this->visits = 0;
				$this->views  = 0;
			  }


			// Daten zur Anzeige selektieren
			// =============================
			switch( $mode )
			{
				case "visits":
					$getVal = $this->visits;
					break;
				case "views":
					$getVal = $this->views;
					break;
				default:
					$getVal = $useronline['online'];
					break;
			}

			$maxLength = nCounter::getLength($digits, $getVal);
			$retVal    = str_pad($getVal, $maxLength, "0", STR_PAD_LEFT);

			return $retVal;
		}


		// Zeigt die Summenwerte aller Counter an
		// **************************************
		public static function showAll($mode, $digits = -1)
		{

			// Datenbank-Verbindung herstellen
			// ===============================
			try { $dbStatic = self::db_connect(); } catch (PDOException $e) { return false; }


			// Aktuelle Daten zur Anzeige selektieren
			// ======================================
			switch( $mode )
			{
				case "visits":
					$allVisits = $dbStatic->query("SELECT   sum(visits) AS `all_visits` FROM `".NCOUNTER_DB_TAB_DATA."`")->fetch();
					$getVal    = $allVisits['all_visits'];
					break;
				case "views":
					$allViews  = $dbStatic->query("SELECT   sum(views)  AS `all_views`  FROM `".NCOUNTER_DB_TAB_DATA."`")->fetch();
					$getVal    = $allViews['all_views'];
					break;
				default:
					$AllOnline = $dbStatic->query("SELECT count(id)     AS `all_online` FROM `".NCOUNTER_DB_TAB_IP."`")->fetch();
					$getVal    = $AllOnline['all_online'];
					break;
			}

			$maxLength = self::getLength($digits, $getVal);
			$retVal    = str_pad($getVal, $maxLength, "0", STR_PAD_LEFT);

			$dbStatic  = NULL;

			return $retVal;
		}


		// Zeigt eine Tabelle aller Counter
		// ********************************
		public static function showList()
		{

			// Datenbank-Verbindung herstellen
			// ===============================
			try { $dbStatic = self::db_connect(); } catch (PDOException $e) { return false; }


			// Daten aller Seiten auflisten
			// ============================
			$retVal = "<table border=\"1\"><th>Counter</th><th>Besucher</th><th>Seitenaufrufe</th><th>Useronline</th>\n";

			$alleCounter       = $dbStatic->query("SELECT * FROM `".NCOUNTER_DB_TAB_DATA."`");
			while( $einCounter = $alleCounter->fetch() )
			{
				$ecOnline  = $dbStatic->query("SELECT count(id) AS `site_online` FROM `".NCOUNTER_DB_TAB_IP."` WHERE `site` = '".$einCounter['site']."'")->fetch();

				$retVal   .= "<tr>
					<td>".$einCounter['site']."</td>
					<td style=\"text-align: right;\">".$einCounter['visits']."</td>
					<td style=\"text-align: right;\">".$einCounter['views']."</td>
					<td style=\"text-align: right;\">".$ecOnline['site_online']."</td>
				</tr>\n";
			}

			$retVal  .= "</table>\n";

			$dbStatic = NULL;

			return $retVal;
		}


		// Gibt einem Counter einen Wert vor
		// *********************************
		public static function setValue($page, $mode, $value)
		{

			// Datenbank-Verbindung herstellen
			// ===============================
			try { $dbStatic = self::db_connect(); } catch (PDOException $e) { return false; }


			// Den neuen Wert speichern, den anderen übernehmen
			// ================================================
			    $counterData = $dbStatic->query("SELECT * FROM `".NCOUNTER_DB_TAB_DATA."` WHERE `site` = '".$page."' LIMIT 0,1")->fetch();
			if( $counterData == true )
			{
				if( $mode == "visits" )
				{
					$setVisits = $value;
					$setViews  = $counterData['views'];
				} else
				  {
					$setVisits = $counterData['visits'];
					$setViews  = $value;
				  }

				$dbStatic->query("UPDATE `".NCOUNTER_DB_TAB_DATA."` SET `visits` = '".$setVisits."', `views` = '".$setViews."' WHERE `site` = '".$page."'");
			}

			$dbStatic  = NULL;

			return ( $counterData == true ) ? 1 : 0;
		}


		// Löscht den Counter einer Seite
		// ******************************
		public static function deleteSite($page)
		{

			// Datenbank-Verbindung herstellen
			// ===============================
			try { $dbStatic = self::db_connect(); } catch (PDOException $e) { return false; }


			    $counterData = $dbStatic->query("SELECT `id` FROM `".NCOUNTER_DB_TAB_DATA."` WHERE `site` = '".$page."' LIMIT 0,1")->fetch();
			if( $counterData == true )
			{
				$dbStatic->query("DELETE FROM `".NCOUNTER_DB_TAB_DATA."` WHERE `site` = '".$page."'");
			}

			$dbStatic  = NULL;

			return ( $counterData == true ) ? 1 : 0;
		}


		// Löscht die Tabellen aller Counter aus der Datenbank
		// ***************************************************
		public static function uninstall()
		{

			// Datenbank-Verbindung herstellen
			// ===============================
			try { $dbStatic = self::db_connect(); } catch (PDOException $e) { return false; }


			$dbStatic->query("DROP TABLE `".NCOUNTER_DB_TAB_DATA."`");
			$dbStatic->query("DROP TABLE `".NCOUNTER_DB_TAB_IP."`");

			$dbStatic = NULL;
		}


		// Ermitelt die Länge der Ausgabe je nach Vorgaben und tatsächlichem Wert
		// **********************************************************************
		public static function getLength($digits, $retVal)
		{
			// Anzahl der Stellen ermitteln
			// ============================
			$digits    = ( $digits != -1             ) ? $digits         : self::$length;
			$maxLength = ( $digits < strlen($retVal) ) ? strlen($retVal) : $digits;

			return $maxLength;
		}


		// Verbindet zur Datenbank und prüft die Installation
		// **************************************************
		public static function db_connect()
		{

			// Datenbank-Verbindung herstellen
			// ===============================
			try
			{
				$dbConn = new PDO("mysql:host=".NCOUNTER_DB_HOST.";dbname=".NCOUNTER_DB_DATABASE, NCOUNTER_DB_USER, NCOUNTER_DB_PASS);


				// Tabelleninstallation prüfen und ggf. erzeugen
				// =============================================
				$tabOk_data     = 0;
				$tabOk_ip       = 0;
				$tabellen       = $dbConn->query("SHOW TABLES FROM `".NCOUNTER_DB_DATABASE."`");
				while( $tabelle = $tabellen->fetch() )
				{
					if( $tabelle[0] == NCOUNTER_DB_TAB_DATA )
					{
						$tabOk_data = 1;
					}
					if( $tabelle[0] == NCOUNTER_DB_TAB_IP )
					{
						$tabOk_ip   = 1;
					}
				}

				if( $tabOk_data == 0 )
				{
					// Tabelle zur Speicherung der Zählwerte erzeugen
					// ==============================================
					$tabCreate = "CREATE TABLE `".NCOUNTER_DB_TAB_DATA."` (
							`id`     int(11)      NOT NULL,
							`site`   varchar(255) NOT NULL DEFAULT '',
							`visits` int(11)      NOT NULL DEFAULT '0',
							`views`  int(11)      NOT NULL DEFAULT '0'
							)";

					$dbConn->query( $tabCreate );
					$dbConn->query("ALTER TABLE `".NCOUNTER_DB_TAB_DATA."` ADD PRIMARY KEY (`id`)");
					$dbConn->query("ALTER TABLE `".NCOUNTER_DB_TAB_DATA."` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1; COMMIT;");
				}

				if( $tabOk_ip == 0 )
				{
					// Tabelle zur Speicherung der IP-Adressen erzeugen
					// ================================================
					$tabCreate = "CREATE TABLE `".NCOUNTER_DB_TAB_IP."` (
							`id`   int(11)      NOT NULL,
							`ip`   varchar(15)  NOT NULL DEFAULT '0',
							`time` int(11)      NOT NULL DEFAULT '0',
							`site` varchar(255) NOT NULL DEFAULT ''
							)";

					$dbConn->query( $tabCreate );
					$dbConn->query("ALTER TABLE `".NCOUNTER_DB_TAB_IP."` ADD PRIMARY KEY (`id`)");
					$dbConn->query("ALTER TABLE `".NCOUNTER_DB_TAB_IP."` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1; COMMIT;");
				}

				return $dbConn;

			} catch (PDOException $e)
			  {
				echo "<span style=\"color: red;\">nCounter-&gt;DB-Error!</span>";
				return false;
			  }
		}
	}


	// DEMO ############################################################################################################################################################################################


	if( 1 == 2 )
	{
		$homeCounter    = new nCounter('home');

		$seitenBesucher = $homeCounter->show('visits');
		$seitenAufrufe  = $homeCounter->show('views', 10);
		$SeitenUser     = $homeCounter->show('online', 3);

		echo "Seitenbesucher von 'home' = ".$seitenBesucher."<br>\n";
		echo "Seitenaufrufe von 'home'  = ".$seitenAufrufe."<br>\n";
		echo "Useronline auf 'home'     = ".$SeitenUser."<br>\n";

		echo "<br>";

		echo "Seitenbesucher gesamt = ".nCounter::showAll('visits')."<br>\n";
		echo "Seitenaufrufe gesamt  = ".nCounter::showAll('views')."<br>\n";
		echo "Useronline gesamt     = ".nCounter::showAll('online')."<br>\n";

		echo "<br>";

		echo "&Uuml;bersicht:<br>".nCounter::showList();

		// nCounter::setValue('home', 'views', 38524);

		// nCounter::deleteSite('home');
	}


?>