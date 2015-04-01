<?php
namespace app\src;
/**
 * Liten - PHP 5 micro framework
 * 
 * @link        http://www.litenframework.com
 * @version     1.0.0
 * @package     Liten
 * 
 * The MIT License (MIT)
 * Copyright (c) 2015 Joshua Parker
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

if ( ! defined('BASE_PATH') )
    exit('No direct script access allowed');

class Hooks
{
	
	/**
     * @access private
	 * @var array
	 *
	*/
	private static $filters = array();
	
	/**
     * @access private
	 * @var string
	 *
	*/
	private static $actions = array();
	
	/**
     * @access private
	 * @var array
	 *
	*/
	private static $merged_filters = array();
	
	/**
     * @access private
	 * @var string
	 *
	*/
	private static $current_filter = array();
	
	/**
	 * all plugins header information in an array.
	 * 
     * @access private
	 * @var array
	 */
	private static $plugins_header = array ();
	
	/**
     * @access private
	 * @var string
	 *
	*/
	private static $error = array();
	
	/**
    * __construct class constructor
    * 
    * @access public
    * @since 1.0.1
    */
    public function __construct($args = null) {
        self::$filters = array();
        self::$merged_filters = array();
        self::$actions = array();
        self::$current_filter = array();
    }
	
	/**
	 * Returns the plugin header information
	 *
     * @access public
     * @since 1.0.1
	 * @param string (optional) $plugin_folder Loads plugins from specified folder
	 * @return mixed
	 *
	*/
	public static function get_plugins_header($plugin_folder = '') {
		
		if ($handle = opendir ( $plugin_folder )) {
			
			while ( $file = readdir ( $handle ) ) {
				if (is_file ( $plugin_folder . $file )) {
					if (strpos ( $plugin_folder . $file, '.plugin.php' )) {
						$fp = fopen ( $plugin_folder . $file, 'r' );
						// Pull only the first 8kiB of the file in.
						$plugin_data = fread ( $fp, 8192 );
						fclose ( $fp );
						
						preg_match ( '|Plugin Name:(.*)$|mi', $plugin_data, $name );
						preg_match ( '|Plugin URI:(.*)$|mi', $plugin_data, $uri );
						preg_match ( '|Version:(.*)|i', $plugin_data, $version );
						preg_match ( '|Description:(.*)$|mi', $plugin_data, $description );
						preg_match ( '|Author:(.*)$|mi', $plugin_data, $author_name );
						preg_match ( '|Author URI:(.*)$|mi', $plugin_data, $author_uri );
						preg_match ( '|Plugin Slug:(.*)$|mi', $plugin_data, $plugin_slug );
						
						foreach ( array ('name', 'uri', 'version', 'description', 'author_name', 'author_uri', 'plugin_slug' ) as $field ) {
							if (! empty ( ${$field} ))
								${$field} = trim ( ${$field} [1] );
							else
								${$field} = '';
						}
						$plugin_data = array ('filename' => $file, 'Name' => $name, 'Title' => $name, 'PluginURI' => $uri, 'Description' => $description, 'Author' => $author_name, 'AuthorURI' => $author_uri, 'Version' => $version );
						self::$plugins_header [] = $plugin_data;
					}
				} else if ((is_dir ( $plugin_folder . $file )) && ($file != '.') && ($file != '..')) {
					self::get_plugins_header( $plugin_folder . $file . '/' );
				}
			}
			
			closedir ( $handle );
		}
		return self::$plugins_header;
	}
	
	/**
 	 * Registers a filtering function
 	 * 
 	 * Typical use: hooks::add_filter('some_hook', 'function_handler_for_hook');
 	 *
     * @access public
     * @since 1.0.1
 	 * @global array $filters Storage for all of the filters
 	 * @param string $hook the name of the PM element to be filtered or PM action to be triggered
 	 * @param callback $function the name of the function that is to be called.
 	 * @param integer $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default=10, lower=earlier execution, and functions with the same priority are executed in the order in which they were added to the filter)
 	 * @param int $accepted_args optional. The number of arguments the function accept (default is the number provided).
 	*/
	public static function add_filter( $hook, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		
		// At this point, we cannot check if the function exists, as it may well be defined later (which is OK)
		$id = self::filter_unique_id( $hook, $function_to_add, $priority );
	
		self::$filters[$hook][$priority][$id] = [ 'function'       => $function_to_add,'accepted_args'  => $accepted_args ];
        unset( self::$merged_filters[$hook] );
        return true;
	}
	
	
	/**
	 * add_action
	 * Adds a hook
	 *
     * @access public
     * @since 1.0.1
	 * @param string $hook
	 * @param string $function
	 * @param integer $priority (optional)
	 * @param integer $accepted_args (optional)
	 *
	*/
	public static function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
		return self::add_filter( $hook, $function_to_add, $priority, $accepted_args );
	}
    
    /**
     * remove_action Removes a function from a specified action hook.
     * 
     * @access public
     * @since 1.0.1
     * @param string $hook The action hook to which the function to be removed is hooked.
     * @param callback $function_to_remove The name of the function which should be removed.
     * @param int $priority optional The priority of the function (default: 10).
     * @return boolean Whether the function is removed.
     */
    public static function remove_action( $hook, $function_to_remove, $priority = 10 ) {
      return self::remove_filter( $hook, $function_to_remove, $priority );
    }
    
    /**
     * remove_all_actions Remove all of the hooks from an action.
     * 
     * @access public
     * @since 1.0.1
     * @param string $hook The action to remove hooks from.
     * @param int $priority The priority number to remove them from.
     * @return bool True when finished.
     */
    public static function remove_all_actions($hook, $priority = false) {
      return self::remove_all_filters($hook, $priority);
    }
	
	/**
 	* Build Unique ID for storage and retrieval.
 	*
 	* Simply using a function name is not enough, as several functions can have the same name when they are enclosed in classes.
 	*
    * @access public
    * @since 1.0.1
    * @param string $hook
 	* @param string|array $function used for creating unique id
 	* @param int|bool $priority used in counting how many hooks were applied.  If === false and $function is an object reference, we return the unique id only if it already has one, false otherwise.
 	* @return string unique ID for usage as array key
 	*/	
	public static function filter_unique_id( $hook, $function, $priority ) {
	    static $filter_id_count = 0;

        // If function then just skip all of the tests and not overwrite the following.
        if ( is_string($function) )
            return $function;
        if ( is_object($function) ) {
        // Closures are currently implemented as objects
        $function = array( $function, '' );
        } else {
            $function = (array) $function;
        }
    
        if (is_object($function[0]) ) {
            // Object Class Calling
            if ( function_exists('spl_object_hash') ) {
                return spl_object_hash($function[0]) . $function[1];
            } else {
                $obj_idx = get_class($function[0]).$function[1];
                if ( !isset($function[0]->_filters_id) ) {
                    if ( false === $priority )
                        return false;
                    $obj_idx .= isset(self::$filters[$hook][$priority]) ? count((array)self::$filters[$hook][$priority]) : $filter_id_count;
                    $function[0]->_filters_id = $filter_id_count;
                    ++$filter_id_count;
                } else {
                    $obj_idx .= $function[0]->_filters_id;
                }
    
                return $obj_idx;
            }
        } else if ( is_string($function[0]) ) {
            // Static Calling
            return $function[0] . '::' . $function[1];
        }
    }
	
	/**
 	* Performs a filtering operation on a PM element or event.
 	*
 	* Typical use:
 	*
 	* 		1) Modify a variable if a function is attached to hook 'hook'
 	*		$var = "default value";
 	*		$var = hooks::apply_filter( 'hook', $var );
 	*
 	*		2) Trigger functions is attached to event 'pm_event'
 	*		hooks::apply_filter( 'event' );
 	*       (see hooks::do_action() )
 	* 
 	* Returns an element which may have been filtered by a filter.
 	*
    * @access public
    * @since 1.0.1
 	* @global array $filters storage for all of the filters
 	* @param string $hook the name of the the element or action
 	* @param mixed $value the value of the element before filtering
 	* @return mixed
 	*/
	public static function apply_filter( $hook, $value ) {
	    $args = array();
        
		if ( isset( self::$filters['all'] ) ) {
			self::$current_filter[] = $hook;
            $args = func_get_args();
            self::_call_all_hook($args);
        }
        
        if ( !isset( self::$filters[$hook] ) ) {
            if( isset(self::$filters['all']) )
                array_pop(self::$current_filter);
            return $value;
        }
        
        if ( !isset( self::$filters['all'] ) ) {
            self::$current_filter[] = $hook;
        }
        
        if ( !isset( self::$merged_filters[$hook] ) ) {
            ksort( self::$filters[$hook] );
            self::$merged_filters[$hook] = true;
        }
	
		// Loops through each filter
		reset( self::$filters[$hook] );
        
        if(empty($args)) {
            $args = func_get_args();
        }
        
		do {
			foreach( (array) current(self::$filters[$hook]) as $the_ )
				if ( !is_null($the_['function']) ){
					$args[1] = $value;
					$value = call_user_func_array($the_['function'], array_slice($args, 1, (int) $the_['accepted_args']));
				}

		} while ( next(self::$filters[$hook]) !== false );
        
        array_pop( self::$current_filter );
	
		return $value;
	}
	
	public static function do_action( $hook, $arg = '' ) {
		
		if ( ! isset(self::$actions) )
        self::$actions = array();

        if ( ! isset(self::$actions[$hook]) )
            self::$actions[$hook] = 1;
        else
            ++self::$actions[$hook];
    
        // Do 'all' actions first
        if ( isset(self::$filters['all']) ) {
            self::$current_filter[] = $hook;
            $all_args = func_get_args();
            self::_call_all_hook($all_args);
        }
    
        if ( !isset(self::$filters[$hook]) ) {
            if ( isset(self::$filters['all']) )
                array_pop(self::$current_filter);
            return;
        }
    
        if ( !isset(self::$filters['all']) )
            self::$current_filter[] = $hook;
    
        $args = array();
        if ( is_array($arg) && 1 == count($arg) && isset($arg[0]) && is_object($arg[0]) ) // array(&$this)
            $args[] =& $arg[0];
        else
            $args[] = $arg;
        for ( $a = 2; $a < func_num_args(); $a++ )
            $args[] = func_get_arg($a);
    
        // Sort
        if ( !isset( self::$merged_filters[ $hook ] ) ) {
            ksort(self::$filters[$hook]);
            self::$merged_filters[ $hook ] = true;
        }
    
        reset( self::$filters[ $hook ] );
    
        do {
            foreach ( (array) current(self::$filters[$hook]) as $the_ )
                if ( !is_null($the_['function']) )
                    call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));
    
        } while ( next(self::$filters[$hook]) !== false );
    
        array_pop(self::$current_filter);
	}
	
	public static function _call_all_hook($args) {
	
		reset( self::$filters['all'] );
		do {
			foreach( (array) current(self::$filters['all']) as $the_ )
				if ( !is_null($the_['function']) )
					call_user_func_array($the_['function'], $args);
	
		} while ( next(self::$filters['all']) !== false );
	}
	
	public static function do_action_array($hook, $args) {
	
		if ( ! isset(self::$actions) )
			self::$actions = array();
	
		if ( ! isset(self::$actions[$hook]) )
			self::$actions[$hook] = 1;
		else
			++self::$actions[$hook];
	
		// Do 'all' actions first
		if ( isset(self::$filters['all']) ) {
			self::$current_filter[] = $hook;
			$all_args = func_get_args();
			self::_call_all_hook($all_args);
		}
	
		if ( !isset(self::$filters[$hook]) ) {
			if ( isset(self::$filters['all']) )
				array_pop(self::$current_filter);
			return;
		}
	
		if ( !isset(self::$filters['all']) )
			self::$current_filter[] = $hook;
	
		// Sort
		if ( !isset( self::$merged_filters[ $hook ] ) ) {
			ksort(self::$filters[$hook]);
			self::$merged_filters[ $hook ] = true;
		}
	
		reset( self::$filters[ $hook ] );
	
		do {
			foreach( (array) current(self::$filters[$hook]) as $the_ )
				if ( !is_null($the_['function']) )
					call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));
	
		} while ( next(self::$filters[$hook]) !== false );
	
		array_pop(self::$current_filter);
	}
	
	/**
 	* Removes a function from a specified filter hook.
 	*
 	* This function removes a function attached to a specified filter hook. This
 	* method can be used to remove default functions attached to a specific filter
 	* hook and possibly replace them with a substitute.
 	*
 	* To remove a hook, the $function_to_remove and $priority arguments must match
 	* when the hook was added.
 	*
 	* @global array $filters storage for all of the filters
 	* @param string $hook The filter hook to which the function to be removed is hooked.
 	* @param callback $function_to_remove The name of the function which should be removed.
 	* @param int $priority optional. The priority of the function (default: 10).
 	* @param int $accepted_args optional. The number of arguments the function accepts (default: 1).
 	* @return boolean Whether the function was registered as a filter before it was removed.
 	*/
	public static function remove_filter( $hook, $function_to_remove, $priority = 10 ) {
	    
		$function_to_remove = self::filter_unique_id($hook, $function_to_remove, $priority);
		
		$remove = isset (self::$filters[$hook][$priority][$function_to_remove]);

		if ( true === $remove ) {
			unset (self::$filters[$hook][$priority][$function_to_remove]);
			if ( empty(self::$filters[$hook][$priority]) )
				unset (self::$filters[$hook][$priority]);
            unset(self::$merged_filters[$hook]);
		}
		return $remove;
	}
    
    /**
     * remove_all_filters Remove all of the hooks from a filter.
     * @access public
     * @since 1.0.1
     * @param string $hook The filter to remove hooks from.
     * @param int $priority The priority number to remove.
     * @return bool True when finished.
     */
    public static function remove_all_filters($hook, $priority = false) {
      if( isset(self::$filters[$hook]) ) {
        if( false !== $priority && isset(self::$filters[$hook][$priority]) )
          unset(self::$filters[$hook][$priority]);
        else
          unset(self::$filters[$hook]);
      }

      if( isset(self::$merged_filters[$hook]) )
        unset(self::$merged_filters[$hook]);

      return true;
    }
	
	/**
 	* Check if any filter has been registered for a hook.
 	*
 	* @global array $filters storage for all of the filters
 	* @param string $hook The name of the filter hook.
 	* @param callback $function_to_check optional.  If specified, return the priority of that function on this hook or false if not attached.
 	* @return int|boolean Optionally returns the priority on that hook for the specified function.
 	*/
	public static function has_filter( $hook, $function_to_check = false ) {

		$has = !empty(self::$filters[$hook]);
		if ( false === $function_to_check || false == $has ) {
			return $has;
		}
		if ( !$idx = self::filter_unique_id($hook, $function_to_check, false) ) {
		return false;
		}

		foreach ( (array) array_keys(self::$filters[$hook]) as $priority ) {
			if ( isset(self::$filters[$hook][$priority][$idx]) )
				return $priority;
		}
		return false;
	}
	
	public static function has_action( $hook, $function_to_check = false ) {
		return self::has_filter( $hook, $function_to_check );
	}
	
	// Serialize data if needed. Stolen from WordPress
	public static function maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) )
			return serialize( $data );

		if ( self::is_serialized( $data ) )
			return serialize( $data );

		return $data;
	}

	// Check value to find if it was serialized. Stolen from WordPress
	public static function is_serialized( $data ) {
		// if it isn't a string, it isn't serialized
		if ( !is_string( $data ) )
			return false;
		$data = trim( $data );
		if ( 'N;' == $data )
			return true;
		if ( !preg_match( '/^([adObis]):/', $data, $badions ) )
			return false;
		switch ( $badions[1] ) {
			case 'a' :
			case 'O' :
			case 's' :
				if ( preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data ) )
					return true;
				break;
			case 'b' :
			case 'i' :
			case 'd' :
				if ( preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data ) )
					return true;
				break;
		}
		return false;
	}

	// Unserialize value only if it was serialized. Stolen from WP
	public static function maybe_unserialize( $original ) {
		if ( self::is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
			return unserialize( $original );
		return $original;
	}
}