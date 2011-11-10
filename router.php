<?php 

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** ROUTER - When a server boots up, it runs this file (called from rc.local) 
// ** to bootstrap itself, and then again (from supervisord) to run the core 
// ** daemon associated with it's purpose.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-17
// ** @access	private
// ** @param	
// ** @return	Main controller router     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

// ===========================================================================// 
// ! Define the core to use (core == purpose/action)                         //
// ===========================================================================//

// Checked for in all othere files to prevent direct access   
define('CORE', $_SERVER['argv'][1]);if(!CORE){exit("No core specified\n");}	

// ===========================================================================// 
// ! Set server path and load needed files                                    //
// ===========================================================================//

// Set working directory for correct file includes etc 
chdir(dirname($_SERVER['argv'][0]));

// Include all required core files (Settings and helper classes)
include('core/includes.core.php'); 

// ===========================================================================// 
// ! Route to correct core daemon                                             //
// ===========================================================================// 

// Define the core class to instantiate
$class = CORE."Core";

// Instantiate core
$type = new $class();	

// ********************************** END **********************************// 
