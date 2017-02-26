#!/usr/bin/env php
<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A PHP program to scramble names in GEDCOM files (for privacy, e.g. when
 * using online GEDCOM syntax checker tools.
 * @copyright  2016 onwards Nicolas Martignoni <nicolas@martignoni.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

date_default_timezone_set("Europe/Zurich");
setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR.UTF-8');
ini_set("auto_detect_line_endings", true);

$key = openssl_random_pseudo_bytes(32);
$iv = openssl_random_pseudo_bytes(16);

// Fixed keys for testing
// Comment out for using in production
// $key = '6407983';
// $iv = 'fedcba9876543210';


$currentdir = dirname(__FILE__);
$inputfile = $currentdir . "/original-gedcom.ged";
$outputfile = $currentdir . "/scrambled-gedcom.ged";

// see http://stackoverflow.com/questions/6407983/utf-8-in-php-regular-expressions
$familyNamePattern = "#^\d NAME [\p{L} \-,']*/([\p{L} \-,']+)/#u";
$firstNamePattern = "#^\d NAME ([\p{L} \-,']+)( /)*#u";
$akaPattern = "#^\d _AKA ([\p{L} \-,']+)#u";
$placeNamePattern = "#^\d _?PLAC (\d+ )?([\p{L} \-',]+)#u";

$names = array();
// read input file line by line, check for pattern matching and populate $names array
$filehandler = @fopen($inputfile, 'r') or die($php_errormsg);
if ($filehandler) {
    while (($line = fgets($filehandler, 4096)) !== false) {
        if (preg_match($familyNamePattern, $line, $matches)) { // family name found
            $names[] = preg_split("/[\(\),]+/", $matches[1]); // add all parts of name to array $names
        }
        if (preg_match($firstNamePattern, $line, $matches)) { // first name found
            $names[] = preg_split("/[\(\)\s,]+/", $matches[1]);
        }
        if (preg_match($akaPattern, $line, $matches)) { // alternate name found
            $names[] = preg_split("/[\(\)\s,]+/", $matches[1]);
        }
        if (preg_match($placeNamePattern, $line, $matches)) { // place name found
            $names[] = preg_split("/[\(\),]+\s?/", $matches[2]);
        }
    }
    if (!feof($filehandler)) {
        echo "Error: fgets() failed\n";
    }
    fclose($filehandler);
}

// sanitize $names array
$names = flatten_array($names);
usort($names, 'stringlengthsort');
$names = array_unique($names);
// keep only names of 2 letters or more, excluding middle name initials and other non relevant terms
$names = array_filter($names, function($str) {
//     return mb_strlen($str) > 2 && !ctype_lower($str);
    return mb_strlen($str) > 1;
});
// reindex array $names
$names = array_values($names);

// print_r($names); exit;

// populate $scramblednames with scrambled names
$scramblednames = array();
foreach ($names as $name) {
//     $scrambledname = mb_strtolower($name, 'UTF-8');
//     $scrambledname = mb_str_split($scrambledname);
//     shuffle($scrambledname);
//     $scrambledname = implode("", $scrambledname);
//     $scrambledname = mb_convert_case($scrambledname, MB_CASE_TITLE, "UTF-8");
    $scrambledname = bin2hex(openssl_encrypt($name, 'aes128', $key, OPENSSL_RAW_DATA, $iv));
    $scrambledname = substr($scrambledname, 0, 12);
    $scramblednames[] = $scrambledname;
}

// dictionnary of names and corresponding scrambled names
$dictionnary = array_combine($names, $scramblednames);
// print_r($dictionnary); exit;

// replace names with scrambled names in input file
$filecontents = file_get_contents($inputfile);
foreach ($dictionnary as $name => $scrambledname) {
    $filecontents = str_replace($name, $scrambledname, $filecontents);
}
// print($filecontents); exit;
file_put_contents($outputfile, $filecontents);

// helper functions
function stringlengthsort ($a, $b) {
    return mb_strlen($b) - mb_strlen($a);
}

function flatten_array (array $array) {
  $flattened_array = array();
  array_walk_recursive($array, function($a) use (&$flattened_array) { $flattened_array[] = $a; });
  return $flattened_array;
}

function mb_str_split ($string) {
  $strlen = mb_strlen($string);
  while ($strlen) {
    $array[] = mb_substr($string,0,1,"UTF-8");
    $string = mb_substr($string,1,$strlen,"UTF-8");
    $strlen = mb_strlen($string);
  }
  return $array;
}
// the end