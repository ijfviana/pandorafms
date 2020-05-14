<?php
##########################################################################
# nagios_plugin_wrapper
#
# Executes the given nagios plugin and produces an XML with data for pandora
# to be used as agent plugin. This allows to have DATA based on the errorlevel
# and use the descriptive information on description for the module
#
# Usage: nagios_plugin_wrapper <module_name> <nagios plugin execution with its parameters>
##########################################################################
# Copyright (c) 2010 Artica Soluciones Tecnologicas S.L
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##########################################################################

define("VERSION", "0.1");
define("PROGNAME", "nagios_plugin_full_wrapper.php");


function  version()
{ 
	print(PROGNAME . " version : " . VERSION . "\n"); 
}

function  print_usage_line() 
{
	print("Usage: " . PROGNAME . " -m <module_name> -c <command> [-t <types>] [-s <cron>] [-g <group>] [-i <interval>] [-h]\n" );
}

function print_usage() {
    print_usage_line();
    print("For more details on options do: " . PROGNAME .  " --help\n");
}

function help() {


    print("Nagios wrapper for Prandorafms ".VERSION."\n");
    print(" by IFV - william(at)leibzon.org\n\n");
    print("This plugin convert nagios plugins output to pandorafms plugins output.\n");
    print("Mandatory arguments:\n");
    print("-m:\t Name of the pandorafms module that is going to be created\n.");    
    print("-c:\t Full path to the nagios plugin\n.");    
    print("Optional arguments:\n");
    print("-s:\t cron line\n.");    
    print("-g:\t group\n.");    
    print("-i:\t interval\n.");    
    print("-t:\t type. Cadena con el formato key0:value0,key1:value1,key2:value2 donde key* es cualquiera de las mediadas que se van a obtener y value es un valor entre 1 y 8 que representa los siguientes tipos:  '1' =>'generic_data', '2' =>'generic_data_inc', '3' =>'generic_data_inc_abs', '4' =>'generic_data_string', '5' =>'generic_proc', '6' =>'async_string', '7' =>'async_proc', '8' =>'async_data'\n.");    
    print("-p:\t convert performance measures\n.");    
    print("-h:\t show help\n.");    
}


$types = array(
	'1' =>'generic_data',
	'2' =>'generic_data_inc',
	'3' =>'generic_data_inc_abs',
	'4' =>'generic_data_string',
	'5' =>'generic_proc',
	'6' =>'async_string',
	'7' =>'async_proc',
	'8' =>'async_data',
);

$shortopts  = "";
$shortopts .= "m:";  // Nombre del módulo
$shortopts .= "c:";  // Comando 
$shortopts .= "s:";  // Valor opcional
$shortopts .= "t:";  // Grupo 
$shortopts .= "g:";  // Grupo 
$shortopts .= "i:";  // intervalo
$shortopts .= "h";   // Ayuda 
$shortopts .= "p";   // performance 


$options = getopt($shortopts/*, $longopts*/);


if (isset($options["h"]) || isset($options["help"])) {
    help();
    exit;
}

if (empty($options['c']) && empty($options['command']))
{
	fprintf(STDERR, "ERROR: command required\n");
	exit(0);
}


if (empty($options['m']) && empty($options['module']))
{
	fprintf(STDERR, "ERROR: modulename required\n");
	exit(0);
}

$module_name = (!empty($options['m'])) ? $options['m'] : "";
$command     = (!empty($options['c'])) ? $options['c'] : "";
$interval    = (!empty($options['i'])) ? $options['i'] : "";
$group       = (!empty($options['g'])) ? $options['g'] : "";
$cron        = (!empty($options['s'])) ? $options['s'] : "";
$type        = (!empty($options['t'])) ? $options['t'] : "";
$performance = (isset($options['p']))  ? "p" : "";


if (!empty($type))
{
	$convert_to_array = explode(',', $type);

	for($i=0; $i < count($convert_to_array ); $i++){
    	$key_value = explode(':', $convert_to_array [$i]);
	    $end_array[$key_value [0]] = $key_value [1];
	}
	
	$type = $end_array;
}
else
	$type = array();


$output = array();
$returnCode = 0;
$module_description=exec($command, $output, $returnCode);
	
	
if ($returnCode == 2){
    $module_data = 0;
} 
elseif ($returnCode == 3){
    $module_data = '';
} 
elseif ($ReturnCode == 0){
    $module_data = 1; 
} 
elseif ($returnCode == 1){
    $module_data = 2;  # need to be managed on module thresholds
} 
elseif ($ReturnCode == 4){
    $module_data = 3; # need to be managed on module thresholds
}


print("<module>\n");
print("<name><![CDATA[".$module_name."_status]]></name>\n");
print("<type><![CDATA[generic_proc]]></type>\n");
print("<data><![CDATA[".$module_data."]]></data>\n");
print("<description><![CDATA[" . $module_description . "]]></description>\n");
if (!empty($group))
	print("<module_group><![CDATA[" . $group .  "]]></module_group>\n");

if (!empty($cron))
	print("<module_crontab><![CDATA[" . $cron .  "]]></module_crontab>\n");
	
if (!empty($interval))
	print("<module_interval><![CDATA[" . $interval .  "]]></module_interval>\n");
	
print("</module>\n");


if (!empty($performance))
{
	list($trash, $variables) = explode("|", $module_description);

	$a_variables = array();

	if (preg_match_all('/\s*([^=]+)=(\S+)\s*/', $variables, $matches)) 
	   $a_variables = array_combine ( $matches[1], $matches[2] );

	foreach ($a_variables as $key => $value)
	{
		print("<module>\n");
		print("<name><![CDATA[".$module_name."_".$key."]]></name>\n");
		
		$type_value = (array_key_exists($key, $type)) ? $types[$type[$key]]: $types[1];
						
		print("<type><![CDATA[".$type_value."]]></type>\n");
		print("<data><![CDATA[".$value."]]></data>\n");

		if (!empty($group))
			print("<module_group><![CDATA[" . $group .  "]]></module_group>\n");

		if (!empty($cron))
			print("<module_crontab><![CDATA[" . $cron .  "]]></module_crontab>\n");
			
		if (!empty($interval))
			print("<module_interval><![CDATA[" . $interval .  "]]></module_interval>\n");

		print("</module>\n");
	}
}
