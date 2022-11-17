#!/usr/bin/php
<?php

include( __DIR__.'/config.php' );


// init
{
	function usage( $err=null ) {
	  echo 'Usage: '.$_SERVER['argv'][0]." <combo list>\n";
	  if( $err ) {
	    echo 'Error: '.$err."!\n";
	  }
	  exit();
	}

	if( $_SERVER['argc'] != 2 ) {
	  usage();
	}

	$input_file = $_SERVER['argv'][1];
	if( !is_file($input_file) ) {
		usage( 'source file not found' );
	}

	if( !is_dir(OUTPUT_DIR) ) {
		@mkdir( OUTPUT_DIR, 0777, true );
	}
	if( !is_dir(OUTPUT_DIR) || !is_writable(OUTPUT_DIR) ) {
		exit( 'Error: cannot write in '.OUTPUT_DIR."!\n" );
	}

	posix_setsid();
	declare( ticks=1 );
	pcntl_signal( SIGCHLD, 'signal_handler' );

	$n_child = 0;
	$t_process = [];
	$t_signal_queue = [];

	copy( 'style.css', OUTPUT_DIR.'style.css' );
}
// ---


// main loop
{
	$t_input = file( $input_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$current_page = 0;

	//var_dump($t_input);
	$cnt = count( $t_input );
	echo "\n".$cnt." IPs loaded\n\n";

	for( $index=0 ; $index<$cnt ; )
	{
		$page = (int)($index / MAX_ITEM_PER_PAGE) + 1;
		if( $page != $current_page ) {
			$current_page = $page;
			render( HTML_HEADER, $current_page );
		}

		list( $ip, $port ) = explode( ':', $t_input[$index] );

		if( $n_child < MT_MAX_CHILD )
		{
			$pid = pcntl_fork();

			if( $pid == -1 ) {
				// fork error
			} elseif( $pid ) {
				// father
				$n_child++;
				$index++;
				$t_process[$pid] = uniqid();
		        if( isset($t_signal_queue[$pid]) ){
		        	$signal_handler( SIGCHLD, $pid, $t_signal_queue[$pid] );
		        	unset( $t_signal_queue[$pid] );
		        }
			} else {
				// child process
				connect( $ip, $port, $current_page, true );
				exit( 0 );
			}
		}

		usleep( MT_SLEEP );
	}

	echo "\n";
	for( $max_wait=0 ; $n_child && $max_wait<120 ; $max_wait++ ) {
		echo $n_child." childs remaining!\n";
		// try to surely leave the loop please :)
		usleep( 500000 );
	}
	echo "\n";
}
// ---


// footer
{
	$total_page = $current_page;

	$cmd = 'egrep -a -o \'data-service="(.*)"\' '.OUTPUT_DIR.'/*  | cut -d \'"\' -f 2';
	exec( $cmd, $output );
	$t_count = array_count_values( $output );
	$t_count = array_merge( ['all'=>array_sum($t_count)], $t_count );
	//var_dump( $t_count );
	$t_count = json_encode( $t_count );

	for( $i=1 ; $i<=$total_page ; $i++ ) {
		render( HTML_FOOTER, $i, ['OUTPUT_DIR'=>OUTPUT_DIR,'T_SERVICE'=>$t_count,'TOTAL_PAGE'=>$total_page,'CURRENT_PAGE'=>$i], false );
	}
}
// ---


exit();

?>