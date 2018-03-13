
( function( $, plugin ) {
	"use strict";

	// Delete Account Button AJAX function
	$('#delete_account_link').on('click', function() {
		var accountlink = this;
		$('#delete_dialogue').removeClass('hidden');
		$(accountlink).hide();
	
			$('#delete_dialogue').find('a').on('click', function(event) {
				event.preventDefault();
				var deleteValue = $(this).attr('value');
				
				if (deleteValue == true) {
					var data = {
						action: plugin.action_delete_user,
						senddata: $(this).attr('value')
					};
			
					$.post(plugin.url, data, function(response) {
						// console.log(response);
						if (response.data.response == "success") {
							$('#delete_dialogue').replaceWith('<h3>' + response.data.message  + '</h3>');
							// Redirect to home page
							setTimeout(document.location.replace('/'), 2000);
						}
						 
					});	
				}
				else {
					$('#delete_dialogue').addClass('hidden');
					$(accountlink).show();
				}
				
			});	
	});

	$('li[data="saved-search"] a[data="remove"]').on('click', function(event) {
			event.preventDefault();
			var indexRm = $(this).data('rm'),
				parent = $(this).parent();

			var data = {
				action: plugin.action_rm_search,
				index_rm: indexRm
			};
			$.post(plugin.url, data, function(response) {
				if (response.success = true) {
					parent.fadeOut(300, function() { $(this).remove(); });
				}
				else {
					parent.replaceWith('<p class="error">' + response.data.message + '</p>');
				}
			});
	});


	/** Save Searches and save results listings to user meta **/
	$('#save-search-button').on('click', function() {

		console.log(this);
		var listings = window.searchSave,
				windowUrl = window.location.search,
				saveError = false;
		// Save search URL

		var data = {
			action: plugin.action_saved_searches,
			searchURL: windowUrl,
			id_first_entry: $('#properties-holder > li:first-of-type').attr('id')
		};
		$.post(plugin.url, data, function(response) {
			if (response.success == true) {
				$('#save-search-button').replaceWith('<p>' + response.data.message + '</p>')
			}
			else {
				$('#save-search-button').replaceWith('<p class="error">' + response.data.message + '</p>');
				saveError = true;
				window.activatePopups();
			}
		}).fail(function(){
			$('#save-search-button').replaceWith('<p class="error">' + 'Something Went wrong! Please reload the page and try again' + '</p>');
		}).done(function() { // When the search has been saved go ahead and try to save the listings from the search
			//console.log(saveError);
			window.activatePopups();
			// Check if search isn't saved
			if ( saveError === false ) {
				var data = {
					action: plugin.action_save_search_listings,
					listings: listings
				};
				// Save listings returned from search into nasty serialized array
				$.post(plugin.url, data, function(response) { 
					// console.log(response);
				});
			}
		});
	});

	/**<3<3<3<3<3**/
	/** Check for saved listings and display a full heart instead of an empty heart **/
	if ($('main').attr('data-hearts') == "true") {
		// check if user is logged in
		var el = this,
			logged_in = false,
			data = {
				action: useraccountajaxObject.action_loggedin
			};

		$.post(useraccountajaxObject.url, data, function(response) {
			// Check to make sure if user logged in:
			// console.log(response); // should be 0 or 1

			// If user is not logged in, don't do anything
			// except for letting 
			if (response == true) {
				logged_in = true;
			}
		}).done(function() {
			if (logged_in === true) {
				var heartsLove = [];

				// Get arrays of all the IDS of each heart saved
				$("a[data='save-heart-listing']").each(function() {
					heartsLove.push($(this).attr('data-id'));
				});
				var data = {
					action: plugin.action_check_saved_listings
				}
				// Get all of the saved listings and check them against what is on the page
				$.post(plugin.url, data, function(response) {
					$.each(response, function() { 
						if ( this.listing ) {
							if (heartsLove.indexOf(this.listing.listing_id) >= 0) {
								$('.save-listing[data-id='+this.listing.listing_id+']').hide();
								$('.saved-listing[data-id='+this.listing.listing_id+']').show();
							}
						}
					});

				});
			}
		});
	}

	/**<3<3<3<3<3**/
	/** Front page <3 to save/unsave listings <3 **/
	$(".listing-save").each(function() {
		$(this).click(function(e) {
			e.stopPropagation();
			e.preventDefault();
			var el = this,
				logged_in = false,
				data = {
					action: useraccountajaxObject.action_loggedin
				};

			$.post(useraccountajaxObject.url, data, function(response) {
				// Check to make sure if user logged in:
				// console.log(response); // should be 0 or 1

				// If user is not logged in, don't do anything
				// except for letting 
				if (response == false) {
					$('a[href=#popup-login]').click();
				}
				else {
					logged_in = true;
				}
			}).done(function() {
				if ( logged_in === true ) {

					// <3 Save a listing
					if ($(el).attr('data') == "save-heart-listing") {
						var saveError = false;
						var data = {
							action: plugin.action_save_individual_listing,
							searchURL: $(el).attr('href'),
							listing: {
								'listing_id': $(el).attr('data-id')
							},
							addressTitle: $(el).attr('title')
						};

						$.post(plugin.url, data, function(response) {
							// console.log(response);
							if (response.success == true) {
								$(el).hide();
								$(el).next('.heart-full').show();
							}
							else {
								$(el).replaceWith('<p class="error">' + response.data.message + '</p>');
								saveError = true;
							}
						});
					}
					// <3 Remove listings from saved listings if already saved
					if ($(el).attr('data') == "saved-heart-listing") {
						var newData = {
							action: plugin.action_remove_listing_by_id,
							listing: {
								'listing_id': $(el).attr('data-id')
							}
						};

						$.post(plugin.url, newData, function(response) {
							if (response.success == true) {
								$(el).hide();
								$(el).prev('.heart-hollow').show();
							}
							else {
								window.location.reload();
							}
						});
					}
				}
			});
		});
	});

	$("a[data='save-listing']").on('click', function(e) {
		e.preventDefault();

		var el = this;
		var logged_in = false;
		var data = {
			action: useraccountajaxObject.action_loggedin
		};

		$.post(useraccountajaxObject.url, data, function(response) {
			// Check to make sure if user logged in:
			// console.log(response); // should be 0 or 1
			if (response == false) {
				var html = '<p> It looks like you are not logged in, please <a href="#popup-login" class="popup-opener">Login</a> or <a href="#popup-registration" class="popup-opener">Create an Account</a> to save listings.</p>';
				$(el).replaceWith(html);
			}
			else {
				logged_in = true;
			}
		}).done(function() {
			if (logged_in == false) {
				jQuery('.popup-opener').on('click', function()
				{
					jQuery($(this).attr('href')).addClass('active');
					jQuery('.popup').not($(this).attr('href')).removeClass('active');
					return false;
				});
				jQuery('.popup-closer').on('click', function()
				{
					jQuery('.popup').removeClass('active');
					return false;
				});
			}


			if ( logged_in == true ) {
				var windowUrl = window.location.search,
					saveError = false,
					listing = {
						'listing_id': $('#main').attr('id')
					};

				// Save search URL
				var data = {
					action: plugin.action_save_individual_listing,
					searchURL: windowUrl,
					listing: listing,
					addressTitle: $('.prop_address').text()
				};
				$.post(plugin.url, data, function(response) {
					// console.log(response);
					if (response.success == true) {
						$(el).replaceWith('<p>' + response.data.message + '</p>');
					}
					else {
						$(el).replaceWith('<p class="error">' + response.data.message + '</p>');
						saveError = true;
					}
				});
			}

		});


	});

	$('li[data="saved-listing"] a[data="remove"]').on('click', function(event) {
		event.preventDefault();
		var indexRm = $(this).data('rm'),
			parent = $(this).parent();
		var data = {
			action: plugin.action_rm_ind_listing,
			index_rm: indexRm
		};
		$.post(plugin.url, data, function(response) {
			if (response.success = true) {
				parent.fadeOut(300, function() { $(this).remove(); });
			}
			else {
				parent.replaceWith('<p class="error">' + response.data.message + '</p>');
			}
		});
	});
} )( jQuery, accounteditajaxObject || {} );