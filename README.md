PHP SDK for the Weather Source API Version 1.5
==============================================



Requirements
------------

Requires PHP version 5.3.0 or greater.




Installation
------------

1. Save api.weathersource.sdk directory to your application
2. Rename api.weathersource.sdk/sdk/config_sample.php to api.weathersource.sdk/sdk/config.php
3. Update api.weathersource.sdk/sdk/config.php with your credentials and preferences
4. Include api.weathersource.sdk/sdk/weathersource_api.sdk.php in your application
5. Use the SDK at will

To acquire valid credentials, please contact Dave Smith at <dave@weathersource.com>.  Eventually, you will be able to sign up for an API account directly at <http://weathersource.com>, but this is not currently available.



Weather_Source_API class
------------------------

Instantiating the Weather_Source_API class is as easy as this:

    include_once('/path/to/api.weathersource.sdk/sdk/weathersource_api.sdk.php');
    $sdk = new Weather_Source_API;

With the proper options set in api.weathersource.sdk/sdk/config.php, this class can:

1. Retry requests N times until success
2. Log errors to specified directory
3. Convert imperial measurement results to metric values
4. Convert Fahrenheit results to Celsius values
5. Suppress HTTP error codes
6. Return diagnostic information with responses



Weather_Source_API methods
--------------------------

There are only four public methods in the Weather_Source_API class:

1. `request()`
2. `is_ok()`
3. `get_response_code()`
4. `get_error_message()`



### The `request()` method ###

The `request()` method accepts 3 parameters:

1. $method          (string)  REQUIRED  The HTTP method for the request (allowed: 'GET', 'POST', 'PUT', 'DELETE')
2. $resource_path   (string)  REQUIRED  The resource path for the request (i.e. 'history_by_postal_code')
3. $parameters      (array)   REQUIRED  The resource parameters

See the [API documentation](http://developer.weathersource.com/) to determine the values for these parameters that will meet your need.

The `request()` method will return a PHP array with the requested response.

Use of the `request()` method may look like this:

    $method         = 'GET';
    $resource_path  = 'history_by_postal_code';
    $parameters     = array(
                        	    'period'            => 'day',
                        	    'postal_code_eq'    => '22222',
                        	    'country_eq'        => 'US',
                        	    'timestamp_between' => '2011-01-01,2011-01-05',
                        	    'fields'            => 'tempMax',
                        	);
    $response = $api->request( $method, $resource_path, $parameters );



### The `is_ok()` method ###

The `is_ok()` method has no parameters.

The `is_ok()` method will return TRUE if the previous request returned a 200 HTTP response code, FALSE otherwise.

Use of the `is_ok()` method may look like this:

    if( $api->is_ok() ) {
    	echo "The request was successful";
    }



### The `get_response_code()` method ###

The `get_response_code()` method has no parameters.

The `get_response_code()` method will return the HTTP response code for the previous request as an integer, NULL when there is not a previous request.

Use of the `get_response_code()` method may look like this:

    echo "The HTTP Response Code for the last request is: ";
    echo $api->get_response_code();



### The `get_error_message()` method ###

The `get_error_message()` method has no parameters.

The `get_error_message()` method will return the error message for the previous request as a string, NULL when not in error state.

Use of the `get_error_message()` method may look like this:

    echo "The error message for the last request is: ";
    echo $api->get_error_message();
