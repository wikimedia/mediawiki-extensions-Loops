<?php

/**
 * 'Loops' is a MediaWiki extension expanding the parser with loops functions
 *
 * Documentation: http://www.mediawiki.org/wiki/Extension:Loops
 * Support:       http://www.mediawiki.org/wiki/Extension_talk:Loops
 * Source code:   https://gerrit.wikimedia.org/r/#/admin/projects/mediawiki/extensions/Loops
 *
 * @license: GNU GPL v2 or higher
 * @author:  David M. Sledge
 * @author:  Daniel Werner < danweetz@web.de >
 *
 * @file Loops.php
 * @ingroup Loops
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

$wgExtensionCredits['parserhook'][] = [
	'path'           => __FILE__,
	'author'         => [
		'David M. Sledge',
		'[http://www.mediawiki.org/wiki/User:Danwe Daniel Werner]'
	],
	'name'           => 'Loops',
	'version'        => '1.0.0-beta',
	'descriptionmsg' => 'loops-desc',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Loops',
	'license-name'   => 'GPL-2.0-or-later',
];

// language files:
$wgMessagesDirs['Loops'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['LoopsMagic'] = __DIR__ . '/Loops.i18n.magic.php';

// hooks registration:
$wgHooks['ParserFirstCallInit'][] = 'ExtLoops::init';
$wgHooks['ParserLimitReportPrepare'][] = 'ExtLoops::onParserLimitReportPrepare';
$wgHooks['ParserClearState'][] = 'ExtLoops::onParserClearState';

// Include settings file and ExtLoops class:
$wgAutoloadClasses['ExtLoops'] = __DIR__ . '/ExtLoops.php';
require_once __DIR__ . '/Loops_Settings.php';
