<?php

/**
 * File defining the settings for the 'Loops' extension.
 * More info can be found at http://www.mediawiki.org/wiki/Extension:Loops#Configuration
 *
 * NOTICE:
 * =======
 * Changing one of these settings can be done by copying and placing
 * it in LocalSettings.php, AFTER the inclusion of 'Loops'.
 *
 * @file Loops_Settings.php
 * @ingroup Loops
 * @since 0.4
 *
 * @author Daniel Werner
 */

/**
 * Allows to define which functionalities provided by 'Loops' should be disabled for the wiki.
 * 
 * @example
 * # disable 'fornumargs' and 'forargs' parser functions:
 * $egLoopsDisabledFunctions = array( 'fornumargs', 'forargs' );
 * 
 * @since 0.4
 * @var array
 */
$egLoopsDisabledFunctions = array();
