/**
 * Local Ads preview document. Loaded before public.js; renders with LocalAds.preview(),
 * which never tracks and never stores visitor state.
 */
( function ( window, document ) {
	'use strict';

	function json( id ) {
		var node = document.getElementById( id );
		try {
			return node ? JSON.parse( node.textContent ) : null;
		} catch ( e ) {
			return null;
		}
	}

	var data = json( 'la-preview-data' ) || {};
	window.LocalAdsConfig = json( 'la-preview-config' ) || { preview: 1 };
	var current = { ad: data.ad, global: data.global };

	function render() {
		var empty = document.querySelector( '[data-la-preview-empty]' );
		if ( ! current.ad || ! window.LocalAds ) {
			if ( window.LocalAds ) {
				window.LocalAds.close();
			}
			if ( empty ) {
				empty.hidden = false;
			}
			return;
		}
		if ( empty ) {
			empty.hidden = true;
		}
		var ad = {};
		for ( var k in current.ad ) {
			if ( Object.prototype.hasOwnProperty.call( current.ad, k ) ) {
				ad[ k ] = current.ad[ k ];
			}
		}
		window.LocalAds.preview( ad, current.global || {}, {
			onClose: function () {
				window.parent.postMessage( { type: 'local-ads-preview-closed' }, window.location.origin );
			},
		} );
	}

	window.addEventListener( 'message', function ( e ) {
		if ( e.origin !== window.location.origin || ! e.data || e.data.type !== 'local-ads-preview' ) {
			return;
		}
		if ( e.data.ad !== undefined ) {
			current.ad = e.data.ad;
		}
		if ( e.data.global ) {
			current.global = e.data.global;
		}
		render();
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		render();
		window.parent.postMessage( { type: 'local-ads-preview-ready' }, window.location.origin );
	} );
}( window, document ) );
