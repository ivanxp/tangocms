<?php

/**
 * Zula Framework SQL Exceptions
 *
 * @patches submit all patches to patches@tangocms.org
 *
 * @author Alex Cartwright
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Alex Cartwright
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html GNU/LGPL 2.1
 * @package Zula_Sql
 */

	class SQL_InvalidDriver extends Zula_Exception {}
	class SQL_UnableToConnect extends PDOException {}
	class SQL_QueryFailed extends PDOException {}
	class SQL_InvalidName extends Zula_Exception {} # Invalid Database table, column etc name

	class Sql_InvalidFile extends Zula_Exception {}

?>
