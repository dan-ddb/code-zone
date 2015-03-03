Map Candy API
-------------

 A RESTful API which serves information about various map points. Currently data
 can be sent to storage in a MySQL database via the POST method and then retrieved
 from the database using the GET method and a pin's unique ID.

 Users can also list all of the pins within a certain radius of a unique pin.

 Users can send a street address and receive latitude and longitude coordinates (which
 can then be used in the creation of a new map pin)

 GET /v1/pins/{pinID}/data
 POST /v1/pins/new/full
 GET /v1/pins/{pinID}/data/{pinID}?radius=100

 Dan Davis-Boxleitner 3-1-2015
 @package map-candy-api

 Files:
 
 map_candy_api.php - Core file for sending GET and POST requests to