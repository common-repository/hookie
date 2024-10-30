<?php

/*
  Plugin Name: Hookie (Visual Hook Reference)
  Plugin URI: http://mircobabini.com/
  Description: Display filters/actions directly into the html source of the page, explore through DOM inspector, detect where the apply function is fired and check parameters.
  Version: 1.0.1
  Author: Mirco Babini
  Author URI: http://mircobabini.it/
  License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
/*
  Based on  Visual Hook Reference by Matty (http://matty.co.za/), Copyright 2012  WooThemes
  Copyright 2015 Mirco Babini  (email : mirkolofio@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if( !defined( 'ABSPATH' ) )
    exit;
if( is_admin() && ( 'plugins.php' == $pagenow ) )
    return;

function init_hookie(){
    $GLOBALS[ 'hookie' ] = new Hookie();
}

add_action( 'plugins_loaded', 'init_hookie' );

class Hookie{

    protected $hooks = array();

    public function __construct(){
        $this->init_hooks();
        $this->init_display();

        add_action( 'wp_print_styles', array( &$this, 'enqueue_styles' ) );
        add_action( 'admin_print_styles', array( &$this, 'enqueue_styles' ) );
    }

    private function init_hooks(){
        include( __DIR__.'/base-hooks.php' );

        if( is_admin() ){
            $hooks = array_merge( $hooks, array(
                'admin_init', 'admin_header', 'admin_notices', 'admin_menu', 'admin_footer'
            ) );
        }

        foreach( $hooks as $hook ){
            $this->add_hook( $hook, 'hookie' );
        }

        do_action( 'hookie_init_hooks', $this );
    }

    private function init_display(){
        foreach( $this->hooks as $hook => $whoami ){
            add_action( $hook, array( &$this, 'display_action' ), 1 );
        }
    }

    public function add_hook( $hook, $whoami ){
        $this->hooks[ $hook ] = $whoami;
    }

    public function remove_hook( $hook ){
        unset( $this->hooks[ $hook ] );
    }

    protected $queue = array();

    public function display_action(){
        $current_filter = current_filter();


        // $echo = !!headers_sent();
        // if( ! $echo ) echo '';
        // if( $echo && count( $this->queue ) > 0 ){
        //     echo "<!-- QUEUE -->";
        //     foreach( $this->queue as $item){
        //         echo $item;
        //     }
        //     echo "<!-- /QUEUE -->";
        //     $queue = array();
        // }

        $args = func_get_args();
        $accepted_args = func_num_args();

        $db = debug_backtrace();
        $filepath = str_replace( ABSPATH, '', $db[ 2 ][ 'file' ] );
        $fileline = $db[ 2 ][ 'line' ];

        if( $db[ 2 ][ 'function' ] != 'do_action' ){
            // var_dump($db[2]['function']); die; // apply_filters
            return;
        }

        // $html = "<!-- {$filepath}: " . current_filter() . '(' . $accepted_args . ')' . "\n";
        // if ( is_array( $args ) && ( 1 <= count( $args ) ) && ( '' != $args[0] ) ) {
        //     $html .= '<pre>' . print_r( $args, true ) . '</pre>' . "\n";
        // }
        // $html .= '-->' . "\n";
        // if( $echo ){
        //     echo $html;
        // } else {
        //     $this->queue[] = $html;
        // }

        echo_or_bufferize( "\n<!-- FIRED in $filepath at line $fileline: $current_filter -->\n" );
        echo_or_bufferize( "<!-- with args: " . _implode( $args ) . " -->" );

        global $wp_filter;
        $actions_by_prio = $wp_filter[ $current_filter ];
        foreach( $actions_by_prio as $prio => $actions ){
            foreach( $actions as $action => $action_details ){
                if( is_array( $action_details[ 'function' ] ) ){
                    $action_name = $action_details[ 'function' ][ 1 ];

                    if( is_object( $action_details[ 'function' ][ 0 ] ) ){
                        $action_class = get_class( $action_details[ 'function' ][ 0 ] );
                        if( $action_class === __CLASS__ )
                            continue;

                        $action_env = 'instance of ' . $action_class;
                    }else{
                        $action_class = $action_details[ 'function' ][ 0 ];
                        if( $action_class === __CLASS__ )
                            continue;

                        $action_env = $action_class;
                    }

                    echo_or_bufferize( "<!-- add_action( '$current_filter', array( '$action_env', '$action_name' ), '$prio' ); -->\n" );
                } else if( is_object( $action_details[ 'function' ] ) ){
                    echo_or_bufferize( "<!-- add_action( '$current_filter', Closure, '$prio' ); -->\n" );
                    // var_dump( $action_details['function'] ); die;
                }else{
                    $action_name = $action_details[ 'function' ];
                    echo_or_bufferize( "<!-- add_action( '$current_filter', '$action_name', '$prio' ); -->\n" );
                }
            }
        }
    }

// End display_action()

    /**
     * Load styling for the hook reference boxes.
     * @since  1.0.0
     * @return  void
     */
    public function enqueue_styles(){
        echo '<style type="text/css">' . "\n" .
        '.visual-hook-reference-box { font-size: 0.9em; border: 1px dashed #CC0033; color: #CC0033; padding: 0.2em 0.3em; margin: 0.2em; word-wrap: break-word; }' . "\n" .
        '.visual-hook-reference-box:hover { background: #CC0033; color: #FFFFFF; font-weight: bold; }' . "\n" .
        '</style>' . "\n";
    }

// End enqueue_styles()
}

// End Class

function _implode( $arr ){
    $arr = ( array ) $arr;

    $buffer = '';
    _implode_arr( $arr, $buffer );

    return $buffer;
}

function _implode_arr( $arr, &$buffer ){
    $buffer .= '( ';
    foreach( $arr as $key => $elem ){
        _implode_elem( $elem, $buffer );
    }

    $buffer .= ' )';
}

function _implode_elem( $elem, &$buffer ){
    if( is_array( $elem ) ){
        _implode_arr( $elem, $buffer );
    }else if( is_object( $elem ) ){
        $buffer .= get_class( $elem );
    }else{
        $buffer .= $elem;
    }
}

global $hookie_buffer, $get_header_done;
$hookie_buffer = '';
$get_header_done = false;
add_action( 'get_header', function(){
    global $get_header_done;
    $get_header_done = true;
} );

function echo_or_bufferize( $str ){
    global $hookie_buffer, $get_header_done;
    if( headers_sent() || $get_header_done ){
        if( $hookie_buffer ){
            echo $hookie_buffer;
            $hookie_buffer = false;
        }

        echo $str;
    }else{
        $hookie_buffer .= $str;
    }
}
