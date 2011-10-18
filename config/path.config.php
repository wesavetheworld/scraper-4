<?php
	
	// ===========================================================================// 
	// ! Server path and argument settings	                                      //
	// ===========================================================================//

	// Checked for in all othere files to prevent direct access   
	define('HUB', TRUE);

	// Get passed server arguments
	$argv = $_SERVER['argv'];	

   	// Set working directory for correct file includes etc 
	chdir(dirname($argv[0]));		