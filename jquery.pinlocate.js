/**
 *  jquery.pinlocate.js - jQuery plugin
 *  Dan Davis-Boxleitner 3-1-2015
 *
 *	This javascript add-on takes a pin ID and calls the map candy API in order
 *  to retrieve data about that pin, returning it to each page object on the
 *  selector's list of objects.
 *
 *   @param options object
 */
(function($) {

    $.fn.pinlocate = function( options ) {

		/**
		 *	Object: options
		 *  This object contains options for the calling of the plugin
		*/
		var settings = $.extend({
			pinId		: null,
			radius		: null,
			newPin		: false
		}, options);
		
		/**
		 *	Function: return
		 *  For each match in the selector list, we invoke the .text() to
		 *  add the information retrieved from the pin.
		*/
		return this.each( function() { 
			// If not a new pin, send call to API with pinId
			// Using the form: GET /v1/pins/{pinID}/data

			// Make request to REST API and stick results into text
			$.ajax({
				url: "http://localhost/v1/pins/" + options.pinId + "/data"
			}).then(function(data) {
				$(this).text( data.content );
			}
		});
	
    }

}(jQuery));