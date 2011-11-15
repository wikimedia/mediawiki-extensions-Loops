<?php

/**
 * 'Loops' is a MediaWiki extension expanding the parser with loops functions
 * 
 * Documentation: http://www.mediawiki.org/wiki/Extension:Loops
 * Support:       http://www.mediawiki.org/wiki/Extension_talk:Loops
 * Source code:   http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/Loops
 * 
 * @version: 0.4 alpha
 * @license: GNU GPL v2 or higher
 * @author:  David M. Sledge
 * @author:  Daniel Werner < danweetz@web.de >
 *
 * @file Loops.php
 * @ingroup Loops
 */

if ( ! defined( 'MEDIAWIKI' ) ) { die( ); }
 
$wgExtensionCredits['parserhook'][] = array(
	'path'          => __FILE__,
	'author' => array( 'David M. Sledge', '[http://www.mediawiki.org/wiki/User:Danwe Daniel Werner]' ),
	'name' => 'Loops',
	'version' => ExtLoops::VERSION,
	'descriptionmsg' => 'loops-desc',
	'url' => 'http://www.mediawiki.org/wiki/Extension:Loops',
);

// language files:
$wgExtensionMessagesFiles['Loops'     ] = ExtLoops::getDir() . '/Loops.i18n.php';
$wgExtensionMessagesFiles['LoopsMagic'] = ExtLoops::getDir() . '/Loops.i18n.magic.php';

// hooks registration:
$wgHooks['ParserFirstCallInit'][] = 'ExtLoops::init';
$wgHooks['ParserClearState'   ][] = 'ExtLoops::onParserClearState';


/**
 * Class representing extension 'Loops', containing all parser functions and other
 * extension logic stuff.
 */
class ExtLoops {
		
	const VERSION = '0.4 alpha';
	
	/**
	 * Configuration variable defining maximum allowed number of loops ('-1' => no limit).
	 * '#forargs' and '#fornumargs' are not limited by this.
	 * 
	 * @var int
	 */
	public static $maxLoops = 100;

	/**
	 * Returns the extensions base installation directory.
	 *
	 * @since 0.4
	 * 
	 * @return boolean
	 */
	public static function getDir() {
		static $dir = null;

		if( $dir === null ) {
			$dir = dirname( __FILE__ );
		}
		return $dir;
	}

	/**
	* Sets up parser functions
	* 
	* @since 0.4
	*/
	public static function init( Parser &$parser ) {

		/*
		 * store for loops count per parser object. This will solve several bugs related to
		 * 'ParserClearState' hook resetting the count early in combination with certain
		 * other extensions or special page inclusion. (since v0.4)
		 */
		$parser->mExtLoopsCounter = 0;

		self::initFunction( $parser, 'while' );
		self::initFunction( $parser, 'dowhile' );
		self::initFunction( $parser, 'loop' );
		self::initFunction( $parser, 'forargs' );
		self::initFunction( $parser, 'fornumargs' );
		
		return true;
	}
	private static function initFunction( Parser &$parser, $name ) {		
		$functionCallback = array( __CLASS__, 'pfObj_' . $name );
		$parser->setFunctionHook( $name, $functionCallback, SFH_OBJECT_ARGS );		
	}
	

	public function onParserLimitReport( $parser, &$report ) {
		// add performed loops to limit report:
		$report .= 'ExtLoops count: ' . self::getLoopsCount( $parser );
		
		if( self::$maxLoops > -1 ) {
			// if limit is set, communicate the limit as well:
			$report .= '/' . self::$maxLoops . "\n";
		}
		return true;
	}
	
	
	####################
	# Parser Functions #
	####################

	public static function pfObj_while( Parser &$parser, $frame, $args ) {
		return self::perform_while( $parser, $frame, $args, false );
	}
	
	public static function pfObj_dowhile( Parser &$parser, $frame, $args ) {
		return self::perform_while( $parser, $frame, $args, true );
	}
	
	/**
	 * Generic function handling '#while' and '#dowhile' as one
	 */
	protected static function perform_while( Parser &$parser, $frame, $args, $dowhile = false ) {
		// #(do)while: | condition | code
		$rawCond = isset( $args[1] ) ? $args[1] : ''; // unexpanded condition
		$rawCode = isset( $args[2] ) ? $args[2] : ''; // unexpanded loop code
		
		if(
			$dowhile === false
			&& trim( $frame->expand( $rawCond ) ) === ''
		) {
			// while, but condition not fullfilled from the start
			return '';
		}
		
		$output = '';
		
		do {
			// limit check:
			if( ! self::incrCounter( $parser ) ) {
				return self::msgLoopsLimit( $output );
			}
			$output .= trim( $frame->expand( $rawCode ) );
			
		} while( trim( $frame->expand( $rawCond ) ) );
		
		return $output;
	}
	
	public static function pfObj_loop( Parser &$parser, PPFrame $frame, $args ) {
		// #loop: var | start | count | code
		$varName  = isset( $args[0] ) ?      trim( $frame->expand( $args[0] ) ) : '';
		$startVal = isset( $args[1] ) ? (int)trim( $frame->expand( $args[1] ) ) : 0;
		$loops    = isset( $args[2] ) ? (int)trim( $frame->expand( $args[2] ) ) : 0;
		$rawCode  = isset( $args[3] ) ? $args[3] : ''; // unexpanded loop code
		
		if( $loops === 0 ) {
			// no loops to perform
			return '';
		}
		
		global $wgExtVariables;
		
		$output = '';
		$endVal = $startVal + $loops;
		
		while( $startVal !== $endVal ) {
			// limit check:
			if( ! self::incrCounter( $parser ) ) {
				return self::msgLoopsLimit( $output );
			}
			
			$wgExtVariables->vardefine( $parser, $varName, (string)$startVal );
			
			$output .= trim( $frame->expand( $rawCode ) );
			
			// in-/decrease loop count (count can be negative):
			( $startVal < $endVal ) ? $startVal++ : $startVal--;
		}
		return $output;
	}
	
	/**
	 * #forargs: filter | keyVarName | valVarName | code
	 */
	public static function pfObj_forargs( Parser &$parser, $frame, $args ) {		
		// The first arg is already expanded, but this is a good habit to have...
		$filter = array_shift( $args );
		$filter = $filter !== null ? trim( $frame->expand( $filter ) ) : '';
		
		// if prefix contains numbers only or isn't set, get all arguments, otherwise just non-numeric
		$tArgs = ( preg_match( '/^([1-9][0-9]*)?$/', $filter ) > 0 )
				? $frame->getArguments()
				: $frame->getNamedArguments();
		
		return self::perform_forargs( $parser, $frame, $args, $tArgs, $filter );
	}
	
	/**
	 * #fornumargs: keyVarName | valVarName | code
	 */
	public static function pfObj_fornumargs( Parser &$parser, $frame, $args ) {
		/*
		 * get numeric arguments, don't use PPFrame::getNumberedArguments because it would
		 * return explicitely numbered arguments only.
		 */
		$tNumArgs = $frame->getArguments();
		foreach( $tNumArgs as $argKey => $argVal ) {
			// allow all numeric, including negative values!
			if( is_string( $argKey ) ) {
				unset( $tNumArgs[ $argKey ] );
			}
		}
		ksort( $tNumArgs ); // sort from lowest to highest
		
		return self::perform_forargs( $parser, $frame, $args, $tNumArgs, '' );
	}
	
	/**
	 * Generic function handling '#forargs' and '#fornumargs' as one
	 */
	protected static function perform_forargs( Parser &$parser, PPFrame $frame, array $funcArgs, array $templateArgs, $prefix = '' ) {
		// if not called within template instance:
		if( !( $frame instanceof PPTemplateFrame_DOM ) ) {
			return array( 'found' => false );
		}
		
		// name of the variable to store the argument name:
		$keyVar  = array_shift( $funcArgs );
		$keyVar  = $keyVar  !== null ? trim( $frame->expand( $keyVar ) )  : '';
		// name of the variable to store the argument value:
		$valVar  = array_shift( $funcArgs );
		$valVar  = $valVar  !== null ? trim( $frame->expand( $valVar ) )  : '';
		// unexpanded code:
		$rawCode = array_shift( $funcArgs );
		$rawCode = $rawCode !== null ? $rawCode : '';
		
		global $wgExtVariables;
		$output = '';
		
		// if prefix contains numbers only or isn't set, get all arguments, otherwise just non-numeric
		$tArgs = preg_match( '/^([1-9][0-9]*)?$/', $prefix ) > 0
				? $frame->getArguments() : $frame->getNamedArguments();
		
		foreach( $templateArgs as $argName => $argVal ) {
			// if no filter or prefix in argument name:
			if( $prefix !== '' && strpos( $argName, $prefix ) !== 0 ) {		
				continue;
			}
			if ( $keyVar !== $valVar ) {
				// variable with the argument name as value
				$wgExtVariables->vardefine(
						$parser,
						$keyVar,
						trim( substr( $argName, strlen( $prefix ) ) )
				);
			}
			// variable with the arguments value
			$wgExtVariables->vardefine( $parser, $valVar, trim( $argVal ) );

			// expand current run:
			$output .= trim( $frame->expand( $rawCode ) );			
		}
		
		return $output;
	}
	
	
	###############
	# Loops Count #
	###############
	
	/**
	 * Returns how many loops have been performed for a given Parser instance.
	 * 
	 * @since 0.4
	 * 
	 * @param Parser $parser
	 * @return int
	 */
	public static function getLoopsCount( Parser &$parser ) {
		return $parser->mExtLoopsCounter;
	}
	
	/**
	 * Returns whether the maximum number of loops for the given Parser instance have
	 * been performed already.
	 * 
	 * @since 0.4
	 * 
	 * @param Parser $parser
	 * @return bool 
	 */
	public static function maxLoopsPerformed( Parser &$parser ) {
		$count = $parser->mExtLoopsCounter;
		return $count > -1 && $count >= self::$maxLoops;
	}
	
	/**
	 * If limit has not been exceeded already, this will increase the counter. If
	 * exceeded false will be returned, otherwise the new counter value
	 * 
	 * @return false|int
	 */
	protected static function incrCounter( Parser &$parser ) {
		if( self::maxLoopsPerformed( $parser ) ) {
			return false;
		}
		return ++$parser->mExtLoopsCounter;
	}
	
	/**
	 * div wrapped error message stating maximum number of loops have been performed.
	 */
	protected static function msgLoopsLimit( $output = '' ) {
		if( trim( $output ) !== '' ) {
			$output .= "\n";
		}
		return $output .= '<div class="error">' . wfMsgForContent( 'loops_max' ) . '</div>';
	}
	
	
	##################
	# Hooks handling #
	##################
	
	public static function onParserClearState( Parser &$parser ) {
		// reset loops counter since the parser process finished one page
		$parser->mExtLoopsCounter = 0;
		return true;
	}
}
