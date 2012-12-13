<?

/**
 *
 * @author Jeffrey D. King
 * @copyright 2012- Weather Source, LLC
 * @since Version 2.0
 *
 */


#
#  The Curl_Node class handles multithreaded cURL requests
#
#  Use of the class may looks something like this:
#
#       // Process results as individual requests complete (rather than waiting
#       // for all requests to complete) by defining a callback function.
#
#       /**
#        *
#        *  User defined callback function to process results as the individual request completes
#        *
#        *  @param   $response   string  The response to the cURL response. Prepend with '&' to pass by reference.
#        *  @param   $metadata   string  User defined metadata associated with the request
#        *  @param   $http_code  string  The HTTP code generated by the cURL request
#        *  @param   $latency    float   Seconds elapsed since node added accurate to the nearest microsecond
#        *  @param   $url        string  User provided URL
#        *  @param   $opts       array   The cURL transfer options
#        *
#        *  @return  whatever you define
#        *
#       **/
#       function user_defined_callback( &$response, $metadata, $http_code, $latency, $url, $opts ) {
#
#           // do something with $response
#       }
#
#       // URLs to requests
#       $urls = array(
#           'http://www.example.com',
#           'http://www1.example.com',
#           'http://www2.example.com',
#       );
#
#       // Pace your requests: add one new thread every .05 seconds
#       Curl_Node::set_request_interval_delay( .05 );
#
#       // Only allow 10 threads at a time
#       Curl_Node::set_max_threads( 10 );
#
#       foreach( $urls as $url ) {
#
#           // Add a cURL node to be processed as thread availability allows
#           new Curl_Node(
#               $url,
#               array( CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 5 ),
#               'user_defined_callback'
#           );
#       }
#
#       // Once all the nodes are added, wait for requests to complete
#       Curl_Node::finish();
#
#       // Get an array of results
#       // $results format:
#       //     array(
#       //        array(
#       //            'response'  => string,    // Response message
#       //            'metadata'  => string,    // User defined metadata associated with the request
#       //            'http_code' => mixed,     // HTTP response code, 0 for cURL error
#       //            'latency'   => float,     // Seconds elapsed since node added accurate to the nearest microsecond
#       //            'url'       => string,    // User provided URL
#       //            'opts'      => array,     // User provided cURL transfer options
#       //        ),
#       //        ...
#       //     ),
#       $results = Curl_Node::get_results();
#


class Curl_Node {

    static private
        $queue,
        $nodes,
        $results,
        $multi_handle,
        $threads,
        $status,
        $max_threads            = 1,
        $max_retries            = 5,
        $retry_delay            = 2,  // seconds to wait before readding node to queue
        $request_interval_delay = 0;  // seconds between launching new threads

    private
        $handle_string;


    /**
     *
     *  Initiate a class instance and add a request to multithreaded cURL handler
     *
     *  @param   $url       string  REQUIRED  URL for the cURL request
     *  @param   $opts      array   OPTIONAL  An array of cURL options formatted like: array( CURLOPT_URL => 'http://example.com' )
     *  @param   $callback  mixed   OPTIONAL  A string for a function name, and array [array(object, string)] for a clase method
     *  @param   $metadata  mixed   OPTIONAL  A container to attach any information you want to associate with this request
     *                                        If provided, the user defined callback function of this name will be called as this
     *                                        individual request completes. You don't need to wait for everything to finish!
     *
     *  @return  NULL
     *
    **/
    public function __construct( $url, $opts = array(), $callback = '', $metadata = '' ) {

        // force return of output
        $opts[CURLOPT_HEADER]         = 0;
        $opts[CURLOPT_RETURNTRANSFER] = 1;
        $opts[CURLOPT_URL]            = $url;

        $handle = curl_init();

        foreach($opts as $option => $value) {
            curl_setopt( $handle, $option, $value );
        }

        $node['handle']       = $handle;
        $node['url']          = $url;
        $node['opts']         = $opts;
        $node['start']        = microtime(TRUE);
        $node['latency']      = 0;
        $node['retries']      = 0;
        $node['http_code']    = '';
        $node['response']     = '';
        $node['callback']     = $callback;
        $node['metadata']     = $metadata;

        self::$queue[(string) $handle] = $node;

        self::request();

        $this->handle_string = (string) $handle;
    }


    /**
     *
     *  Set the maximum number of threads that may be utilized at any one time
     *
     *  @param   $max_threads  integer  REQUIRED  The maximum number of threads
     *
     *  @return  NULL
     *
    **/
    static public function set_max_threads( $max_threads ) {

        self::$max_threads = $max_threads;
    }


    /**
     *
     *  Set the number of seconds to wait between launching new threads to managably ramp up requests
     *
     *  @param   $request_interval_delay  integer  REQUIRED  The interval in seconds between thread launches
     *
     *  @return  NULL
     *
    **/
    static public function set_request_interval_delay( $request_interval_delay ) {

        self::$request_interval_delay = $request_interval_delay;
    }


    /**
     *
     *  Set the maximum number of times to retry a recoverable error
     *
     *  @param   $max_retries  integer  REQUIRED  The maximum number of times to retry a recoverable error
     *
     *  @return  NULL
     *
    **/
    static public function set_max_retries($max_retries) {

        self::$max_retries = $max_retries;
    }


    /**
     *
     *  Set the delay in seconds between retry requests
     *
     *  @param   $retry_delay  integer  REQUIRED  The delay in seconds between retry requests
     *
     *  @return  NULL
     *
    **/
    static public function set_retry_delay($retry_delay) {

        self::$retry_delay = $retry_delay;
    }


    /**
     *
     *  Get the status of a node associated with the cURL handle $handle
     *
     *  @return  string   Possible values: "queued", "processing", "complete", "unknown"
     *
    **/
    public function get_status() {

        if( is_array(self::$results) && isset(self::$results[$this->handle_string]) ) {

            return 'complete';

        } elseif( is_array(self::$nodes) && isset(self::$nodes[$this->handle_string]) ) {

            return 'processing';

        } elseif( is_array(self::$queue) && isset(self::$queue[$this->handle_string]) ) {

            return 'queued';
        }

        return 'unknown';
    }


    /**
     *
     *  Get all results from all completed requests
     *
     *  @return  If request has completed, returns an associative array containing these keys:
     *           'response' (string), 'http_code' (string), 'latency' (float), 'url' (string),
     *           'opts' (array). If request has not completed, returns FALSE
     *
    **/
    public function get_result() {

        if( isset(self::$results[$this->handle_string]) ) {

            return self::$results[$this->handle_string];

        } else {

            return FALSE;
        }
    }


    /**
     *
     *  Get all results from all completed requests
     *
     *  @return  array  An indexed array of associative arrays containing these keys:
     *                  'response' (string), 'http_code' (string), 'latency' (float), 'url' (string), 'opts' (array)
     *
    **/
    static public function get_results() {

        return isset(self::$results) ? self::$results : array();
    }


    /**
     *
     *  Wait for all request nodes to complete and process the results
     *
     *  @return  NULL
     *
    **/
    static public function finish() {

        if( isset(self::$multi_handle) ) {
            do {

                self::wait_for_result();
                self::process_results();

            } while( 0 < self::$threads && CURLM_OK == self::$status );

            curl_multi_close(self::$multi_handle);
        }
    }



    /**
     *
     *  Add a node and process any results that may have completed
     *
     *  @return  NULL
     *
    **/
    static private function request() {

        self::add_nodes();
        self::process_results();
    }



    /**
     *
     *  Add a node
     *
     *  @return  NULL
     *
    **/
    static private function add_nodes() {

        if( 0 == count(self::$queue) ) {

            return;
        }

        self::$multi_handle = empty(self::$multi_handle) ? curl_multi_init() : self::$multi_handle;
        self::$threads      = empty(self::$threads)      ? 0                 : self::$threads;
        self::$status       = !isset(self::$status)      ? CURLM_OK          : self::$status;


        /*  add nodes to curl requests until $max_threads added or $queue exhausted  */

        while( self::$threads < self::$max_threads && 0 < count(self::$queue) && CURLM_OK == self::$status ) {

            // add node to $nodes
            $node = array_shift(self::$queue);

            self::$nodes[(string) $node['handle']] = $node;
            curl_multi_add_handle(self::$multi_handle, $node['handle']);

            usleep( self::$request_interval_delay / 1000000 );

            do {
                self::$status = curl_multi_exec(self::$multi_handle, self::$threads);
            } while( CURLM_CALL_MULTI_PERFORM == self::$status );
        }
    }


    /**
     *
     *  Wait until activity on a node occurs
     *
     *  @return  NULL
     *
    **/
    static private function wait_for_result() {


            if( CURLM_OK == self::$status && 0 < self::$threads && -1 != curl_multi_select(self::$multi_handle) ) {

                do {
                    self::$status = curl_multi_exec(self::$multi_handle, self::$threads);
                } while (CURLM_CALL_MULTI_PERFORM == self::$status);
            }
    }


    /**
     *
     *  Process any results that may have completed
     *
     *  @return  NULL
     *
    **/
    static private function process_results() {

        $processed = FALSE;

        while( FALSE !== ($info = curl_multi_info_read(self::$multi_handle)) ) {

            $handle   = $info['handle'];

            $node = self::$nodes[(string) $handle];
            unset(self::$nodes[(string) $handle]);

            $http_code = curl_getinfo( $node['handle'], CURLINFO_HTTP_CODE );

            if( in_array($http_code, array(0,500,503,504)) &&  $node['retries'] < self::$max_retries) {

                // we have an error that may be recovered from
                sleep(self::$retry_delay);
                $node['retries']++;
                self::$queue[] = $node;
                continue;

            } else {

                $callback = $node['callback'];

                $node['latency']    = microtime(TRUE) - $node['start'];
                $node['http_code']  = (string) $http_code;
                $node['response']   = ( $http_code == 200 ) ? curl_multi_getcontent($handle) : self::http_response_message($http_code, curl_error($handle));
                unset($node['handle']);
                unset($node['start']);
                unset($node['retries']);
                unset($node['callback']);

                // we will for a $result array that allows $node['response'] to be passed by reference to user defined callback
                $callback_params = array(
                    &$node['response'],
                    $node['metadata'],
                    $node['http_code'],
                    $node['latency'],
                    $node['url'],
                    $node['opts']
                );

                call_user_func_array( $callback, $callback_params );

                self::$results[(string) $handle] = $node;

                curl_multi_remove_handle(self::$multi_handle, $handle);
            }

            $processed = TRUE;
        }

        if( $processed && 0 < count(self::$queue) && CURLM_OK == self::$status ) {

            self::add_nodes();
        }
    }


    /**
     *
     *  Get the HTTP Response Message for a givin HTTP Response Code
     *
     *  @param  integer  $response_code  REQUIRED  The HTTP Response Code for most recent request
     *  @param  string   $curl_error     OPTIONAL  The text of the cURL error when $response_code == 0
     *
     *  @return string   HTTP Response Message
     */
    static private function http_response_message( $response_code, $curl_error = '' ) {

        $curl_error = empty($curl_error) ?  '' : ": $curl_error";

        if( !is_null($response_code) ) {
            switch ($response_code) {
                case 0:   $text = 'Connection Error' . $curl_error; break;
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:  $text = 'Unknown status'; break;
            }
        } else {
            $text = 'Unknown status'; break;
        }

        return $text;
    }
}


?>