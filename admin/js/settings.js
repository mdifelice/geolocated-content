jQuery( document ).ready( function() {
	if ( window.geolocation_settings &&
		 window.geolocation_settings.options ) {
		jQuery( '#geolocation-settings-modal' ).dialog( {
			autoOpen : false,
			modal    : true,
			width    : 800,
			height   : 640
		} );

		for ( var i in geolocation_settings.options ) {
			var input = jQuery( '[name^=' + i + ']' );
			
			if ( input.length ) {
				var container = input.parent();

				container.addClass( 'geolocation-settings-overwritable' );

				jQuery( 'body' ).addClass( 'geolocation-settings-has-editable-settings' );

				jQuery( container ).click( function( e ) {
					if( e.ctrlKey || e.metaKey ) {
						e.preventDefault();

						var modal   = jQuery( '#geolocation-settings-modal' );
						var setting = jQuery( this ).attr( 'name' ).replace( /\[.*$/, '' )
						
						modal.dialog( {
							title: geolocation_settings.i18n.modal_title.replace( '%s', setting )
						} );

						modal.empty().text( geolocation_settings.i18n.modal_loading );

						modal.dialog( 'open' );

						jQuery.ajax( {
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'geolocation_settings_load',
								_ajax_nonce: geolocation_settings.nonces.load,
								geolocation_settings_setting: setting
							},
							success: function( response ) {
								modal.empty();

								if ( ! response ) {
									modal.text( geolocation_settings.i18n.error_setting );
								} else {
									var table = jQuery( '<table/>' );

									table.addClass( 'widefat' );

									for ( var i in response ) {
										var market  = response[ i ];
										var matches = window.location.search.match( /[\?&]geolocation_market=(\d+)/ );
										var currentMarket;
										var marketElement;

										if ( matches ) {
											currentMarket = parseInt( matches[1] );
										} else {
											currentMarket = 0;
										}

										if ( currentMarket == market.id ) {
											marketElement = document.createTextNode( market.name );
										} else {
											var href = window.location.href;

											var geolocationMarketRegularExpression = new RegExp( /([\?&])geolocation_market=\d*/ );
											var replacement = 'geolocation_market=' + encodeURIComponent( market.id );

											if ( href.match( geolocationMarketRegularExpression ) ) {
												if ( market.id ) {
													href = href.replace( geolocationMarketRegularExpression, '$1' + replacement );
												} else {
													href = href.replace( geolocationMarketRegularExpression, '' );
												}
											} else {
												if ( market.id ) {
													href += ( window.location.search ? '&' : '?' ) + replacement;
												}
											}

											marketElement = jQuery( '<a/>' )
												.attr( 'href', href )
												.text( market.name );
										}

										table
											.append( jQuery( '<tr/>' )
												.data( 'market-id', market.id )
												.append( jQuery( '<td/>' )
													.append( marketElement )
												)
												.append( jQuery( '<td/>' )
													.addClass( 'geolocation-settings-setting' )
													.text( market.setting )
												)
												.append( jQuery( '<td/>' )
													.append( jQuery( '<a/>' )
														.addClass( 'button-secondary' )
														.attr( 'href', '#' )
														.text( geolocation_settings.i18n.delete )
														.click( function( e ) {
															e.preventDefault();

															var button = jQuery( this );

															if ( ! button.attr( 'disabled' ) ) {
																if ( confirm( geolocation_settings.i18n.confirm_delete ) ) {
																	button.attr( 'disabled', 'disabled' );

																	jQuery.ajax( {
																		url: ajaxurl,
																		method: 'POST',
																		data: {
																			action: 'geolocation_settings_delete',
																			_ajax_nonce: geolocation_settings.nonces.delete,
																			geolocation_settings_setting: setting,
																			geolocation_settings_market_id: button.closest( 'tr' ).data( 'market-id' ),
																		},
																		success: function( response ) {
																			console.log( geolocation_settings );
																			if ( ! response ) {
																				alert( geolocation_settings.i18n.error_delete );
																			} else {
																				button.closest( 'tr' ).find( '.geolocation-settings-setting' ).text( 'false' );

																			}
																		}.bind( button ),
																		complete: function() {
																			button.removeAttr( 'disabled' );
																		}.bind( button ),
																		error: function() {
																			alert( geolocation_settings.i18n.error_unknown );
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
								modal.empty().text( geolocation_settings.i18n.error_unknown );
							}
						} );
					}
				}.bind( input ) );
			}
		}
	}
} );
