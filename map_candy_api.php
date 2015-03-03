<?php
/**
 * A RESTful API which serves information about various map points. Currently data
 * can be sent to storage in a MySQL database via the POST method and then retrieved
 * from the database using the GET method and a pin's unique ID.
 *
 * Users can also list all of the pins within a certain radius of a unique pin.
 *
 * Users can send a street address and receive latitude and longitude coordinates (which
 * can then be used in the creation of a new map pin)
 *
 * GET /v1/pins/{pinID}/data
 * POST /v1/pins/new/full
 * GET /v1/pins/{pinID}/data/{pinID}?radius=100
 *
 * Dan Davis-Boxleitner 3-1-2015
 * @package map-candy-api
 */

abstract class mapCandyBase
{
	/**
	 * Property: method
	 * The HTTP method of the current request: GET, POST, PUT, DELETE
	 */
	protected $method = "";
	/**
	 * Property: endpoint
	 * The endpoint defined in the current request
	 */
	protected $endpoint = "";
	/**
	 * Property: verb
	 * The verb/action specified in the current request
	 */
	protected $verb = "";
	/**
	 * Property: args
	 * The arguments specified in the URI when endpoint and verb have been
	 * removed from the string. 
	 */
	protected $args = Array();
	/**
	 * Property: file
	 * For storing input send with the PUT request method
	 */
	protected $file = Null;

	/**
	 * Constructor: __construct
	 * Pull the data from the request and allow for CORS
	 */
	public function __construct( $request )
	{
			header( "Access-Control-Allow-Origin: *" );
			header( "Access-Control-Allow-Methods: *" );
			header( "Content-type: application/json" );
			
			$this->args = explode( '/', rtrim( $request, '/' ) );
			$this->endpoint = array_shift( $this->args );
			if( array_key_exists( 0, $this->args ) && !is_numeric( $this->args[0] ) )
			{
				$this->verb = array_shift( $this->args );
			}
			
			$this->method = $_SERVER['REQUEST_METHOD'];
			if( $this->method == 'POST' && array_key_exists( 'HTTP_X_HTTP_METHOD', $server ) )
			{
				if( $_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE' )
				{
					$this->method = 'DELETE';
				} else if ( $_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT' )
				{
					$this->method = 'PUT';
				} else
				{
					throw new Exception( "Unexpected header." );
				}
			}
			
			switch( $this->method ) 
			{
				case 'DELETE':
				case 'POST':
					$this->request = $this->_cleanInputs( $_POST );
					break;
				case 'GET':
					$this->request = $this->_cleanInputs( $_GET );
					break;
				case 'PUT':
					$this->request = $this->_cleanInputs( $_GET );
					$this->file = file_get_contents( "php://input" );
					break;
				default:
					$this->_response( 'Invalid Method', 405 );
					break;
			}
	}
	
	/**
	 * Function: processAPI
	 * Process the request
	 */
	public function processAPI() 
	{
		if( (int)method_exists( $this, $this->endpoint ) > 0 )
		{
			return $this->_response( $this->{$this->endpoint}($this->args) );
		}
		return $this->_response( "No endpoint: $this->endpoint", 404 );
	}

	/**
	 * Function: _response
	 * Provide an HTTP response and fill with JSON encoded data
	 */
	private function _response( $data, $status=200 )
	{
		header( "HTTP/1.1 " . $status . " " . $this->_requestStatus( $status ) );
		return json_encode( $data );
	}
	
	/**
	 * Function: _cleanInputs
	 * Provide filtering for supplied input data
	 */
	private function _cleanInputs( $data )
	{
		$clean_input = Array();
		if( is_array( $data ) )
		{
			foreach( $data as $k => $v )
			{
				$clean_input[$k] = $this->_cleanInputs( $v );
			}
		} else {
			$clean_input = trim( strip_tags( $data ) );
		}
		return $clean_input;
	}
	
	/**
	 * Function: _requestStatus
	 * Provide filtering for supplied input data
	 */
	private function _requestStatus( $code )
	{
		$status = array(
			200 => 'OK',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			500 => 'Internal Server Error'
			);
		return ( $status[ $code ] ) ? $status[ $code ] : $status[ 500 ];
	}
}

class mapCandyApi extends mapCandyBase
{
	protected $MapPin;
	
	public function __construct( $request, $origin )
	{
		parent::__construct( $request );

		// Perform any authentication process here..
	}

	/**
	 * Function: pins
	 * An endpoint to allow access to map pin related data functions
	 */
	protected function pins()
	{
		if( $this->method == 'GET' )
		{
			if ( sizeof( $this->args ) < 2 )
			{
				// GET /v1/pins/{pinID}/data
				$this->MapPin = new MapPin( $this->verb );
				
				return json_encode( $this->MapPin->listPinData() );
			} elseif ( sizeof( $this->args ) < 3 ) {
				// GET /v1/pins/{pinID}/data/{pinID}?radius=100
				$this->MapPin = new MapPin( $this->args[0] );
				
				// Pull the pinID and radius from the arguments supplied
				$explodedString = explode( "?", $this->args[2] );
				$explodedString2 = explode( "=", $explodedString );
				
				// Make a call to listPinsRadius() using the pinID and supplied radius
				return json_encode( $this->MapPin->listPinsRadius( $explodedString[0], $explodedString2[1] ) );
			}
		
		} else if ( $this->method == 'POST' ) {
		
			// POST /v1/pins/new/full

			// Create an empty MapPin object
			$this->MapPin = new MapPin( Null );
			
			parse_str( $this->file, $post_vars );

			// Pass the data from the POST variables in to generate a database entry
			$this->MapPin->addNewPin( $post_vars );

			return "New pin added.";
		}
	}
	
}

class dbConnection
{
	/**
	 * Property: mysqli
	 * Holds the database connection
	 */
	private $mysqli;

	/**
	 * Function: __construct
	 * Initialize the database connection
	 */
	public function __construct()
	{
		// Attempt to connect to database.
		$this->mysqli = new mysqli( "localhost", "test_user", "DBtestuser1", "database_alpha" );
		
		// Test for database connection errors.
		if ( $this->mysqli->errno ) {
			die( "Database connection failed: " . $this->mysqli->error );
		}
	}

	/**
	 * Function: queryDatabase
	 * Query the database using the supplied query string
	 */
	public function queryDatabase( $queryString )
	{
		return $this->mysqli->query( $queryString, MYSQLI_STORE_RESULT );
	}
	
	/**
	 * Function: __destruct
	 * Drop the database connection
	 */
	public function __destruct()
	{
		// End our use of the database connection
		$this->mysqli->close();
	}
	
}

class MapPin
{
	/**
	 * Property: dbConnection
	 * The database connection class
	 */
	private $dbConnection = Null;

	/**
	 * Property: pinId
	 * The pin's ID
	 */
	private $pinId = Null;

	/**
	 * Property: latitude
	 * The pin's latitude value
	 */
	private latitude = Null;

	/**
	 * Property: longitude
	 * The pin's longitude value
	 */
	private longitude = Null;

	/**
	 * Property: address1
	 * The pin's first address line
	 */
	private address1 = Null;

	/**
	 * Property: address2
	 * The pin's second address line
	 */
	private address2 = Null;

	/**
	 * Function: __construct
	 * Initialize the database connection
	 */
	public function __construct( $pinId )
	{
		$this->pinId = $pinId;
		
		$this->dbConnection = new dbConnection;

		// If we are using an existing pin, fill the object from the database
		if( $pinId != Null )
		{
			$query = "SELECT latitude, longitude, address1, address2, city, state, zip, note ";
			$query = $query . "FROM pins WHERE id ='{$this->pinId}'";
			$result = $this->dbConnection->queryDatabase( $query );
			
			while( list( $latitude, $longitude, $address1, $address2, $city, $state, $zip, $note ) = $result->fetch_row() )
			{
				$this->latitude = $latitude;
				$this->longitude = $longitude;
				$this->address1 = $address1;
				$this->address2 = $address2;
				$this->city = $city;
				$this->state = $state;
				$this->zip = $zip;
				$this->note = $note;
			}
		}
		
		// Otherwise leave the properties of the pin set to Null
	}

	 /**
	 * Function: addNewPin
	 * This function handles a request to the API to generate a new Pin entry
	 */
	public function addNewPin( $data )
	{
		// Construct an INSERT query to add the pin data to the MySQL database
		$query = "INSERT INTO pins (latitude, longitude, address1, address2, city, state, zip, note) ";
		$query .= "VALUES ('{$data['latitude']}','{$data['longitude']}','{$data['address1']}','{$data['address2']}',";
		$query .= "'{$data['city']}','{$data['state']}','{$data['zip']}','{$data['note']}')";
		
		// Submit the query to the database object
		$result = $this->dbConnection->queryDatabase( $query );
	}
	
	 /**
	 * Function: listPinData
	 * This function handles the retrieving of a pin's data from the MySQL database
	 */
	public function listPinData()
	{
		$query = "SELECT latitude, longitude, address1, address2, city, state, zip, note ";
		$query .= "FROM pins WHERE user_id = '{$this->pinId}'";
		$result = $this->dbConnection->queryDatabase( $query );
		$returnData = Array();
		
		while( list( $latitude, $longitude, $address1, $address2, $city, $state, $zip, $note ) = $result->fetch_row() )
		{
			$returnData[ "latitude" ] = $latitude;
			$returnData[ "longitude" ] = $longitude;
			$returnData[ "address1" ] = $address1;
			$returnData[ "address2" ] = $address2;
			$returnData[ "city" ] = $city;
			$returnData[ "state" ] = $state;
			$returnData[ "zip" ] = $zip;
			$returnData[ "note" ] = $note;
		}
		
		return $returnData;
	}

	 /**
	 * Function: listPinsRadius
	 * Given the ID of a map pin that exists in the database, return a list of all the
	 * other pins which are within the supplied radius of the first pin.
	 */
	public function listPinsRadius( $args )
	{
		// Pull the pin ID and radius from the args
		
		// Return a list of all the pins that are within the radius
	}
	 
}
