jQuery( document ).ready( function() {
	if ( window.geolocation_settings && window.geolocation_settings.setting_names ) {
		var setting_names = geolocation_settings.settings_names;
		var i18n          = geolocation_settings.i18n;
		var nonces        = geolocation_settings.nonces;

		jQuery( '#geolocation-settings-modal' ).dialog( {
			autoOpen : false,
			modal    : true,
			width    : 800,
			height   : 640
		} );

		for ( var i in setting_names ) {
			var setting_name = setting_names[ i ];
			var input        = jQuery( '[name^=' + setting_name + ']' );
			
			if ( input.length ) {
				var container = input.parent();

				container.addClass( 'geolocation-settings-overwritable' );

				jQuery( 'body' ).addClass( 'geolocation-settings-active' );

				jQuery( container ).click( function( e ) {
					if( e.ctrlKey || e.metaKey ) {
						e.preventDefault();

						var modal        = jQuery( '#geolocation-settings-modal' );
						var setting_name = jQuery( this ).attr( 'name' ).replace( /\[.*$/, '' )
						
						modal.dialog( {
							title: i18n.modal_title.replace( '%s', setting )
						} );

						modal.empty().text( i18n.modal_loading );

						modal.dialog( 'open' );

						jQuery.ajax( {
							url     : ajaxurl,
							method  : 'POST',
							data    : {
								action                            : 'geolocation_settings_load',
								_ajax_nonce                       : nonces.load,
								geolocation_settings_setting_name : setting_name
							},
							success : function( response ) {
								modal.empty();

								if ( ! response ) {
									modal.text( i18n.error_setting );
								} else {
									var table = jQuery( '<table/>' );

									table.addClass( 'widefat' );

									for ( var i in response ) {
										var location = response[ i ];
										var matches  = window.location.search.match( /[\?&]geolocation_location_id=(\d+)/ );
										var currentLocationId;
										var locationElement;

										if ( matches ) {
											currentLocationId = parseInt( matches[1] );
										} else {
											currentLocationId = 0;
										}

										if ( currentLocationId == location.id ) {
											locationElement = document.createTextNode( location.name );
										} else {
											var href = window.location.href;

											var geolocationLocationRegularExpression = new RegExp( /([\?&])geolocation_location_id=\d*/ );
											var replacement = 'geolocation_location_id=' + encodeURIComponent( location.id );

											if ( href.match( geolocationLocationRegularExpression ) ) {
												if ( location.id ) {
													href = href.replace( geolocationLocationRegularExpression, '$1' + replacement );
												} else {
													href = href.replace( geolocationLocationRegularExpression, '' );
												}
											} else {
												if ( location.id ) {
													href += ( window.location.search ? '&' : '?' ) + replacement;
												}
											}

											locationElement = jQuery( '<a/>' )
												.attr( 'href', href )
												.text( location.name );
										}

										table
											.append( jQuery( '<tr/>' )
												.data( 'location-id', location.id )
												.append( jQuery( '<td/>' )
													.append( locationElement )
												)
												.append( jQuery( '<td/>' )
													.addClass( 'geolocation-settings-setting' )
													.text( location.setting_value )
												)
												.append( jQuery( '<td/>' )
													.append( jQuery( '<a/>' )
														.addClass( 'button-secondary' )
														.attr( 'href', '#' )
														.text( i18n.delete )
														.click( function( e ) {
															e.preventDefault();

															var button = jQuery( this );

															if ( ! button.attr( 'disabled' ) ) {
																if ( confirm( i18n.confirm_delete ) ) {
																	button.attr( 'disabled', 'disabled' );

																	jQuery.ajax( {
																		url      : ajaxurl,
																		method   : 'POST',
																		data     : {
																			action                            : 'geolocation_settings_delete',
																			_ajax_nonce                       : nonces.delete,
																			geolocation_settings_setting_name : setting_name,
																			geolocation_settings_location_id  : button.closest( 'tr' ).data( 'location-id' ),
																		},
																		success  : function( response ) {
																			if ( ! response ) {
																				alert( i18n.error_delete );
																			} else {
																				button.closest( 'tr' ).find( '.geolocation-settings-setting' ).text( 'false' );

																			}
																		}.bind( button ),
																		complete : function() {
																			button.removeAttr( 'disabled' );
																		}.bind( button ),
																		error    : function() {
																			alert( i18n.error_unknown );
																		}
																	} );
																}
															}
														} )
													)
												)
											);
									}

									modal.append( table );
								}
							},
							error: function() {
								modal.empty().text( i18n.error_unknown );
							}
						} );
					}
				}.bind( input ) );
			}
		}
	}
} );
