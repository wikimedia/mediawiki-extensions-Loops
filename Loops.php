<?php
/**
 * 'Loops' is a MediaWiki extension expanding the parser with loops functions
 * 
 * Documentation: http://www.mediawiki.org/wiki/Extension:Loops
 * Support:       http://www.mediawiki.org/wiki/Extension_talk:Loops
 * Source code:   http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/Loops
 * 
 * @version: 0.3.2
 * @license: GNU GPL v2 or higher
 * @author:  David M. Sledge
 * @author:  Daniel Werner < danweetz@web.de >
 *
 * @file Loops.php
 * @ingroup Loops
 */
 
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}
 
$wgHooks['ParserFirstCallInit'][] = $wgHooks['LanguageGetMagic'][]
	= $wgHooks['ParserLimitReport'][] = ExtLoops::getInstance();
 
// Extension credits that will show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'author' => 'David M. Sledge',
	'name' => 'Loops',
	'version' => ExtLoops::VERSION,
	'description' => 'Parser functions for performing loops',
	'url' => 'http://www.mediawiki.org/wiki/Extension:Loops',
);
 
class ExtLoops {
	const VERSION = '0.3.2';
	
	public static $maxLoops = 100;  // maximum number of loops allowed
	                                // (-1 = no limit).  #forargs is
	                                // not limited by this.
	private static $instance = null;
 
	private $parserFunctions = array(
		'dowhile' => array( 'dowhile', SFH_OBJECT_ARGS ),
		'while' => array( 'whileHook', SFH_OBJECT_ARGS ),
		'loop' => array( 'loop', SFH_OBJECT_ARGS ),
		'forargs' => array( 'forArgs', SFH_OBJECT_ARGS ),
		'fornumargs' => array( 'forNumArgs', SFH_OBJECT_ARGS ),
	);
 
	private $loopCount = 0;
 
	public static function getInstance() {
		// create the singleton if needed
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
 
		return self::$instance;
	}
 
	/**
	 * limited-access constructor to insure singleton
	 */
	protected function __construct() { }
 
	public function onParserFirstCallInit( &$parser ) {
		global $wgMessageCache, $wgHooks;
 
		// These functions accept DOM-style arguments
		foreach( $this->parserFunctions as $hook => $callAndFlags ) {
			$parser->setFunctionHook( $hook, array( $this, $callAndFlags[0] ),
				$callAndFlags[1] );
		}
 
		require_once( dirname( __FILE__ ) . '/Loops.i18n.php' );
 
		// @todo FIXME: $wgMessageCache and the addMessages method were
		// removed in http://www.mediawiki.org/wiki/Special:Code/MediaWiki/81027
		foreach( Loops_i18n::getInstance()->getMessages()
			as $lang => $messages )
		{
			$wgMessageCache->addMessages( $messages, $lang );
		}
 
		$wgHooks['ParserClearState'][] = $this;
 
		return true;
	}
 
	public function onLanguageGetMagic( &$magicWords, $langCode ) {
		require_once( dirname( __FILE__ ) . '/Loops.i18n.php' );
 
		foreach( Loops_i18n::getInstance()->magicWords( $langCode )
			as $word => $trans )
		{
			$magicWords[$word] = $trans;
		}
 
		return true;
	}
 
	public function onParserLimitReport( $parser, &$report ) {
		if ( isset( $this->loopCount ) ) {
			$report .= "ExtLoops count: {$this->loopCount}/" . self::$maxLoops . "\n";
		}
 
		return true;
	}
 
	public function whileHook( &$parser, $frame, $args ) {
		// bug 12842:  first argument is automatically
		//   expanded, so we ignore this one
		array_shift( $args );
		$test = array_shift( $args );
		$loopStatement = array_shift( $args );
		$output = '';
 
		while ( isset( $test ) && trim( $frame->expand( $test ) ) !== '' ) {
			if (
				self::$maxLoops >= 0 &&
				++$this->loopCount > self::$maxLoops
			)
			{
				return $output . wfMsgForContent( 'loops_max' );
			}
 
			$output .= isset( $loopStatement ) ?
				trim( $frame->expand( $loopStatement ) ) : '';
		}
 
		//return '<pre><nowiki>'. $output . '</nowiki></'. 'pre>';
		return $output;
	}
 
	public function dowhile( &$parser, $frame, $args ) {
		// bug 12842:  first argument is automatically
		//   expanded, so we ignore this one
		array_shift( $args );
		$test = array_shift( $args );
		$loopStatement = array_shift( $args );
		$output = '';
 
		do {
			if (
				self::$maxLoops >= 0 &&
				++$this->loopCount > self::$maxLoops
			)
			{
				return $output . wfMsgForContent( 'loops_max' );
			}
 
			$output .= isset( $loopStatement ) ?
				trim( $frame->expand( $loopStatement ) ) : '';
		} while ( isset( $test ) && trim( $frame->expand( $test ) ) !== '' );
 
		//return '<pre><nowiki>'. $output . '</nowiki></'. 'pre>';
		return $output;
	}
 
	public function forArgs( &$parser, $frame, $args ) {
		if ( !( $frame instanceof PPTemplateFrame_DOM ) ) {
			$arg = array_shift( $args );
			$arg = isset( $arg ) ? $frame->expand( $arg ) : '';
 
			// TODO: get the synonym for the content language
			$out = "{{#forargs:$arg";
 
			// expand and display each argument
			while ( ( $arg = array_shift( $args ) ) !== null ) {
				$out .= '|' . $frame->expand( $arg );
			}
 
			$out .= '}}';
 
			return $out;
		}
 
		global $wgExtVariables;
 
		// The first arg is already expanded, but this is a good habit to have.
		$filter = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		// name of the variable to store the argument name.  this
		// will be accessed in the loop by using {{#var:}}
		$keyVarName = isset( $args[1] ) ?
			trim( $frame->expand( $args[1] ) ) : '';
		// name of the variable to store the argument value.
		$valueVarName = isset( $args[2] ) ?
			trim( $frame->expand( $args[2] ) ) : '';
		$loopStatement = isset( $args[3] ) ? $args[3] : '';
		$output = '';
 
		$tArgs = preg_match( '/^([1-9][0-9]*)?$/', $filter ) > 0
			? $frame->getArguments() : $frame->getNamedArguments();
 
		foreach ( $tArgs as $argName => $argVal ) {
			if ( $filter == '' || strpos( $argName, $filter ) === 0 ) {
				if ( $keyVarName !== $valueVarName ) {
					$wgExtVariables->vardefine( $parser, $keyVarName,
					       trim( substr( $argName, strlen( $filter ) ) ) );
				}
 
				$wgExtVariables->vardefine( $parser, $valueVarName, trim( $argVal ) );
				$output .= trim( $frame->expand( $loopStatement ) );
			}
		}
 
		return $output;
	}
 
	public function forNumArgs( &$parser, $frame, $args ) {
		if ( !( $frame instanceof PPTemplateFrame_DOM ) ) {
			$arg = array_shift( $args );
			$arg = isset( $arg ) ? $frame->expand( $arg ) : '';
 
			// TODO: get the synonym for the content language
			$out = "{{#forargs:$arg";
 
			// expand and display each argument
			while ( ( $arg = array_shift( $args ) ) !== null ) {
				$out .= '|' . $frame->expand( $arg );
			}
 
			$out .= '}}';
 
			return $out;
		}
 
		global $wgExtVariables;
 
		// The first arg is already expanded, but this is a good habit to have.
		// name of the variable to store the argument name.  this
		// will be accessed in the loop by using {{#var:}}
		$keyVarName = isset( $args[0] ) ?
			trim( $frame->expand( $args[0] ) ) : '';
		// name of the variable to store the argument value.
		$valueVarName = isset( $args[1] ) ?
			trim( $frame->expand( $args[1] ) ) : '';
		$loopStatement = isset( $args[2] ) ? $args[2] : '';
		$output = '';
		$numArgs = $frame->getArguments();
		ksort( $numArgs );
 
		foreach ( $numArgs as $argNumber => $argVal ) {
			if ( is_string( $argNumber ) ) {
				continue;
			}
 
			if ( $keyVarName !== $valueVarName ) {
				$wgExtVariables->vardefine( $parser, $keyVarName, trim( $argNumber ) );
			}
 
			$wgExtVariables->vardefine( $parser, $valueVarName, trim( $argVal ) );
			$output .= trim( $frame->expand( $loopStatement ) );
		}
 
		return $output;
	}
 
	public function loop( &$parser, $frame, $args ) {
		global $wgExtVariables;
		// snag the variable name
		$varName = array_shift( $args );
		$varName = $varName === null ? '' : trim( $frame->expand( $varName ) );
		// grab the intitial value for the variable (default to 0)
		$startVal = array_shift( $args );
		$startVal = $startVal === null
			? 0 : intval( trim( $frame->expand( $startVal ) ) );
		// How many times are we gonna loop?
		$count = array_shift( $args );
 
		if ( $count === null ) {
			return '';
		}
 
		$endVal = $startVal + intval( trim( $frame->expand( $count ) ) );
 
		if ( $endVal == $startVal ) {
			return '';
		}
 
		// grab the unexpanded loop statement
		$loopStatement = array_shift( $args );
		$output = '';
 
		for ( ; $startVal != $endVal;
			$startVal < $endVal ? $startVal++ : $startVal-- )
		{
			if (
				self::$maxLoops >= 0 &&
				++$this->loopCount > self::$maxLoops
			) {
				return $output . wfMsgForContent( 'loops_max' );
			}
 
			$wgExtVariables->vardefine( $parser, $varName, trim( $startVal ) );
 
			$output .= isset( $loopStatement ) ?
				trim( $frame->expand( $loopStatement ) ) : '';
		}
 
		return $output;
	}
 
	public function onParserClearState( &$parser ) {
		$this->loopCount = 0;
 
		return true;
	}
}
