<?php

/**
 *
 * @author Jeffrey D. King
 * @copyright 2012- Weather Source, LLC
 * @since Version 2.0
 *
 */


/*  initiate our API class instance  */

require_once( __DIR__ . '/../sdk/weather_source_api_requests.php' );


/*  define a callback function to handle requests as they complete  */

/**
 *
 *  User defined callback function to process results as individual requests complete
 *
 *  @param   $result     string  The response to the cURL response. Prepend with '&' to pass by reference.
 *  @param   $http_code  string  The HTTP code generated by the cURL request
 *  @param   $latency    float   Seconds elapsed since node added accurate to the nearest microsecond
 *  @param   $url        string  User provided URL
 *  @param   $opts       array   The cURL transfer options
 *
 *  @return  NULL
 *
**/
function user_defined_callback( &$result, $http_code, $latency, $url, $opts ) {
    echo "<pre>callback \$result = ";
    print_r( $result );
    echo "</pre>";

    // unset the key field. Since $result was passed by reference, this will change the final $results
    unset($result['key']);
}


/*  make multiple API requests  */

for($i=0; $i<10; $i++) {


    /*  make an API request  */

    $request = new Weather_Source_API_Request(
        $request_method     = 'GET',
        $request_path       = 'account',
        $request_parameters = array( 'fields' => 'key,username,first_name,last_name,email' ),
        $callback_name      = 'user_defined_callback' // optional
    );

    // $request->get_status() will return 'queued', 'processing', 'complete', or 'unknown'
    echo "request status = {$request->get_status()}</br>";
}


/*  wait for all requests to complete  */

Weather_Source_API_Requests::finish();


/*  get the results ad do something with them  */

$results = Weather_Source_API_Requests::get_results();

echo "<pre>summary \$results = ";
print_r( $results );
echo "</pre>";


/*  get the last submitted request's result  */

if( 'complete' == $request->get_status() ) {
    $result = $request->get_result();
    echo "<pre>last request \$result = ";
    print_r( $result );
    echo "</pre>";
}

?>