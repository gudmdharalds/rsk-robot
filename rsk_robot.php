<?php

require_once( 
	dirname( __FILE__ ) . '/rsk_robot_config.php'
);

/*
 * Check if configuration is OK
 */
if ( empty( $recipient_email ) ) {
	die( 'No recipient defined!' );
}


/*
 * Make sure if we are called via webserver,
 * that the caller has a correct RPC-key to
 * call us -- mainly to avoid spamming users.
 */
if ( php_sapi_name() !== 'cli' ) { 
	if ( empty( $rpc_key ) ) {
		die( 
			'No RPC key defined, please define one so ' .
			'this script is secured' 
		);
	}


	if ( 
		( empty( $_GET[ 'rpc_key' ] ) ) || 
		( $_GET[ 'rpc_key' ] !== $rpc_key ) 
	) {
		die( 'Invalid RPC-key' );
	}
} 

/* 
 * Define day in seconds
 */
define( 'DAY_IN_SECONDS', 60 * 60 * 24 );


/*
 * Try to get data from calendar-server
 */
$html = file_get_contents( 
		'https://www.rsk.is/um-rsk/skattadagatal/' . 
			urlencode(  
				date( 'Y' )
			) 
);


/*
 * No data, report error
 */
if ( false === $html ) {
	die( 'Could not fetch calendar-data from server' );
}


/*
 * Load up HTML in DOMDocument, so
 * we can extract the data properly
 */
$doc = new DOMDocument();
$doc->validateOnParse = true;

$doc->loadHTML( $html );

if ( strlen( $html ) <= 0 ) {
	die( 'Did not get data from calendar-data server' );
}


/*
 * Start by getting all HTML-tags
 * which are of the 'span' type
 */
$months_dates = $doc->getElementsByTagName( 'span' );

$dates_arr = array();


/*
 * Now go through all the <span>-tags
 * in the HTML we got
 */
foreach ( $months_dates as $month_date ) {
	/*
	 * Make sure we got the right
	 * type of a node; one that
	 * is of type '<span>' and has
	 * the 'date' class assigned to it
	 */

	if ( $month_date
		->attributes
		->getNamedItem( 'class' )
		->nodeValue != 'date' 
	) {
		continue;
	}


	/*
	 * So, if we got a proper node here,
	 * we should be able to traverse up
	 * the parent-nodes, and find there
	 * all the child-nodes we need, with
	 * rest of the information.
	 */
	$month = $month_date
			->parentNode
			->parentNode
			->parentNode
			->childNodes;

	/*
	 * From the 'month'-node, extract 
	 * UNIX-timestamp and a description,
	 * we save for further usage -- do this
	 * after we trim the data.
	 */
	$dates_arr[] = array(
		'unixtime'	=> trim(
			strtotime(
				$month[1]->childNodes[1]->textContent
			)
		),

		'description'	=> trim(
			$month[3]->textContent
		),
	);
}


/*
 * Get current UNIX-time
 */
$timestamp_now = time();


/*
 * Loop through the dates we got now,
 * and construct an email to our recipient.
 */

$email_txt = '';

foreach ( $dates_arr as $date_item ) {

	/*
	 * Filter out those that occured in the past
	 */
	if ( $date_item[ 'unixtime' ] < $timestamp_now ) {
		continue;
	}

	/*
	 * Filter those that more than ~8 days into the future
	 */
	if ( 
		$date_item[ 'unixtime' ] - ( 8 * DAY_IN_SECONDS ) > 
			$timestamp_now 
	) {
		continue;
	} 

	/*
	 * Construct an email...
	 */

	$tmp_date = ' * ' . 
		date( 
			'd-m-Y',
			$date_item[ 'unixtime' ]
		) .
		': ';

	$tmp_description_arr = explode(
		"\n", 
		$date_item[ 'description' ] 
	);

	foreach ( $tmp_description_arr as $aa ) {
		if ( trim( $aa ) == '' ) {
			continue;
		}

		$email_txt .= $tmp_date . $aa . "\n";
	}

	$email_txt .= "\n\r";
}

/*
 * Send out the email
 */

mail(
	$recipient_email,
	'RSK dagsetningar vikunnar',
	"Hæ!\n" .
	"\n" .
	"Hér eru helstu dagsetningar varðandi Ríkisskattstjóra þessa vikuna:\n" .
	"\n" .
	"\n" .
	$email_txt .
	"\n" .
	"Bestu kveðjur,\n" .
	"RSK-vélmennið"
);
