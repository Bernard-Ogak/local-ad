/**
 * Local Ads frontend engine.
 *
 * Flow: fetch live eligible ads (uncached) -> verify schedule against server time ->
 * apply visitor frequency -> pick ONE ad by rotation -> wait for trigger -> show popup ->
 * record impression -> animate / shake -> click, close or auto-close -> store state.
 *
 * No dependencies. Exposes window.LocalAds.preview() for the admin preview.
 */
( function ( window, document ) {
	'use strict';

	var cfg = window.LocalAdsConfig || {};
	var i18n = cfg.i18n || {};
	var PREFIX = 'localAds:';
	var VISIT_GAP = 30 * 60 * 1000;
	var memory = {};
	var offset = 0;
	var tzOffset = 0;
	var currentVisit = '';
	var active = null;
	var cssPromise = null;

	function log() {
		if ( cfg.debug && window.console && window.console.info ) {
			window.console.info.apply( window.console, [ '[Local Ads]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	/* ---------- Storage (with in-memory fallback when storage is blocked) ---------- */

	function probe( type ) {
		try {
			var s = window[ type ];
			s.setItem( PREFIX + 't', '1' );
			s.removeItem( PREFIX + 't' );
			return s;
		} catch ( e ) {
			return null;
		}
	}
	var session = probe( 'sessionStorage' );
	var local = probe( 'localStorage' );

	function read( store, key ) {
		try {
			if ( store ) {
				var raw = store.getItem( PREFIX + key );
				return raw === null ? null : JSON.parse( raw );
			}
		} catch ( e ) {}
		return Object.prototype.hasOwnProperty.call( memory, key ) ? memory[ key ] : null;
	}

	function write( store, key, value ) {
		memory[ key ] = value;
		try {
			if ( store ) {
				store.setItem( PREFIX + key, JSON.stringify( value ) );
			}
		} catch ( e ) {}
	}

	/* ---------- Clock: server time and site calendar day ---------- */

	function nowSec() {
		return Math.floor( Date.now() / 1000 ) + offset;
	}

	function siteDay() {
		return new Date( ( nowSec() + tzOffset ) * 1000 ).toISOString().slice( 0, 10 );
	}

	function visitId() {
		var v = read( local, 'visit' );
		var t = Date.now();
		if ( ! v || ! v.id || t - v.last > VISIT_GAP ) {
			v = { id: t.toString( 36 ) + Math.random().toString( 36 ).slice( 2, 7 ), last: t };
		} else {
			v.last = t;
		}
		write( local, 'visit', v );
		return v.id;
	}

	/* ---------- Eligibility ---------- */

	function inSchedule( ad ) {
		var now = nowSec();
		return ( ! ad.start || ad.start <= now ) && ( ! ad.end || ad.end >= now );
	}

	function globalAllows( g ) {
		if ( g.cap === 'session' && read( session, 'gclosed' ) ) {
			return false;
		}
		if ( g.cap === 'hours' ) {
			var closed = read( local, 'gclosed' );
			if ( closed && nowSec() - closed < g.capH * 3600 ) {
				return false;
			}
		}
		return true;
	}

	function frequencyAllows( ad ) {
		switch ( ad.freq ) {
			case 'always':
				return true;
			case 'visit':
				return read( local, 'v' + ad.id ) !== currentVisit;
			case 'day':
				return read( local, 'd' + ad.id ) !== siteDay();
			case 'hours':
				var last = read( local, 'h' + ad.id );
				return ! last || nowSec() - last >= ad.freqH * 3600;
			default: // session
				return ! read( session, 's' + ad.id );
		}
	}

	function markShown( ad ) {
		switch ( ad.freq ) {
			case 'always':
				break;
			case 'visit':
				write( local, 'v' + ad.id, currentVisit );
				break;
			case 'day':
				write( local, 'd' + ad.id, siteDay() );
				break;
			case 'hours':
				write( local, 'h' + ad.id, nowSec() );
				break;
			default:
				write( session, 's' + ad.id, 1 );
		}
	}

	function markClosed( g ) {
		if ( g.cap === 'session' ) {
			write( session, 'gclosed', 1 );
		} else if ( g.cap === 'hours' ) {
			write( local, 'gclosed', nowSec() );
		}
	}

	/* ---------- Rotation ---------- */

	function weightedPick( ads ) {
		var total = 0;
		var i;
		for ( i = 0; i < ads.length; i++ ) {
			total += Math.max( 1, ads[ i ].weight || 1 );
		}
		var r = Math.random() * total;
		for ( i = 0; i < ads.length; i++ ) {
			r -= Math.max( 1, ads[ i ].weight || 1 );
			if ( r < 0 ) {
				return ads[ i ];
			}
		}
		return ads[ ads.length - 1 ];
	}

	function choose( ads, mode ) {
		if ( ads.length < 2 ) {
			return ads[ 0 ] || null;
		}
		var sorted;
		switch ( mode ) {
			case 'sequential':
				sorted = ads.slice().sort( function ( a, b ) {
					return a.id - b.id;
				} );
				var last = read( local, 'seq' ) || 0;
				for ( var i = 0; i < sorted.length; i++ ) {
					if ( sorted[ i ].id > last ) {
						return sorted[ i ];
					}
				}
				return sorted[ 0 ];
			case 'priority':
				var top = Math.max.apply( null, ads.map( function ( a ) {
					return a.priority;
				} ) );
				return weightedPick( ads.filter( function ( a ) {
					return a.priority === top;
				} ) );
			case 'weighted':
				return weightedPick( ads );
			default:
				return ads[ Math.floor( Math.random() * ads.length ) ];
		}
	}

	/* ---------- Tracking ---------- */

	/**
	 * Tells other scripts on the page (for example Blue Lens Analytics) that an ad was shown or
	 * clicked: document receives a "localads:track" event with detail { id, type }.
	 */
	function announce( ad, type ) {
		try {
			document.dispatchEvent( new window.CustomEvent( 'localads:track', { detail: { id: ad.id, type: type } } ) );
		} catch ( e ) {}
	}

	function track( ad, type ) {
		if ( ! cfg.ajaxUrl ) {
			return;
		}
		var body = 'action=local_ads_track&ad=' + encodeURIComponent( ad.id ) + '&type=' + encodeURIComponent( type ) + '&sig=' + encodeURIComponent( ad.sig || '' );
		log( 'track', type, ad.id );
		try {
			if ( window.navigator.sendBeacon ) {
				var blob = new window.Blob( [ body ], { type: 'application/x-www-form-urlencoded' } );
				if ( window.navigator.sendBeacon( cfg.ajaxUrl, blob ) ) {
					return;
				}
			}
		} catch ( e ) {}
		try {
			if ( window.fetch ) {
				window.fetch( cfg.ajaxUrl, {
					method: 'POST',
					body: body,
					keepalive: true,
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				} ).catch( function () {} );
				return;
			}
		} catch ( e ) {}
		var xhr = new window.XMLHttpRequest();
		xhr.open( 'POST', cfg.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.send( body );
	}

	/* ---------- Styles (loaded lazily so they never block rendering) ---------- */

	function loadCss() {
		if ( cssPromise ) {
			return cssPromise;
		}
		cssPromise = new Promise( function ( resolve ) {
			if ( ! cfg.cssUrl || document.querySelector( 'link[data-local-ads-css]' ) ) {
				resolve();
				return;
			}
			var link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = cfg.cssUrl;
			link.setAttribute( 'data-local-ads-css', '1' );
			link.onload = function () {
				resolve();
			};
			link.onerror = function () {
				resolve();
			};
			window.setTimeout( resolve, 3000 );
			document.head.appendChild( link );
		} );
		return cssPromise;
	}

	function reducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	/* ---------- Popup ---------- */

	function el( tag, className, attrs ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( attrs ) {
			for ( var k in attrs ) {
				if ( Object.prototype.hasOwnProperty.call( attrs, k ) && attrs[ k ] !== null && attrs[ k ] !== undefined ) {
					node.setAttribute( k, attrs[ k ] );
				}
			}
		}
		return node;
	}

	/**
	 * Renders a popup. Resolves to a controller once the image is loaded and visible,
	 * or rejects if the image fails (so no impression is counted for a failed popup).
	 */
	function open( ad, g, opts ) {
		opts = opts || {};
		var preview = !! opts.preview;
		var doc = opts.document || document;

		return new Promise( function ( resolve, reject ) {
			var img = new window.Image();
			img.onload = function () {
				resolve();
			};
			img.onerror = function () {
				reject( new Error( 'image' ) );
			};
			img.src = ad.img;
		} ).then( function () {
			return preview ? null : loadCss();
		} ).then( function () {
			if ( active ) {
				active.close( 'replaced' );
			}
			var reduce = reducedMotion();
			var anim = reduce ? 'none' : ad.anim || 'fade';
			var intensity = { low: '4px', medium: '8px', high: '14px' }[ ad.shakeInt ] || '8px';

			var root = el( 'div', 'local-ads-root local-ads-entering' + ( g.overlay ? '' : ' local-ads-no-overlay' ), {
				'data-anim': anim,
				'data-close-pos': g.closePos || 'top-right',
				'data-close-size': g.closeSize || 'medium',
				'data-ad': ad.id,
			} );
			root.style.setProperty( '--local-ads-z', String( g.z || 999999 ) );
			root.style.setProperty( '--local-ads-width', ( ad.width || 600 ) + 'px' );
			root.style.setProperty( '--local-ads-mwidth', ( ad.mWidth || 92 ) + 'vw' );
			root.style.setProperty( '--local-ads-radius', ( g.radius || 0 ) + 'px' );
			root.style.setProperty( '--local-ads-opacity', String( g.opacity === undefined ? 0.55 : g.opacity ) );
			root.style.setProperty( '--local-ads-speed', ( reduce ? 1 : g.speed || 400 ) + 'ms' );
			root.style.setProperty( '--local-ads-shake-distance', intensity );

			var overlay = null;
			if ( g.overlay ) {
				overlay = el( 'div', 'local-ads-overlay', { 'aria-hidden': 'true' } );
				root.appendChild( overlay );
			}

			var popup = el( 'div', 'local-ads-popup', {
				role: 'dialog',
				'aria-modal': g.overlay ? 'true' : 'false',
				'aria-label': i18n.label || 'Advertisement',
				tabindex: '-1',
			} );
			var frame = el( 'div', 'local-ads-frame' );
			var closeBtn = el( 'button', 'local-ads-close' + ( g.closeDelay > 0 ? ' local-ads-close-pending' : '' ), {
				type: 'button',
				'aria-label': i18n.close || 'Close advertisement',
			} );
			closeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';

			var image = el( 'img', 'local-ads-image', {
				src: ad.img,
				alt: ad.alt || '',
				width: ad.w || null,
				height: ad.h || null,
				decoding: 'async',
			} );

			var media = image;
			var link = null;
			if ( ad.url ) {
				link = el( 'a', 'local-ads-link', { href: ad.url } );
				if ( ad.newTab ) {
					link.setAttribute( 'target', '_blank' );
					link.setAttribute( 'rel', 'noopener noreferrer sponsored' );
				} else {
					link.setAttribute( 'rel', 'sponsored' );
				}
				var name = ( ad.alt || i18n.label || 'Advertisement' ) + ( ad.newTab ? ' ' + ( i18n.opens || '(opens in a new tab)' ) : '' );
				link.setAttribute( 'aria-label', name );
				link.appendChild( image );
				media = link;
			}

			frame.appendChild( media );
			if ( g.label ) {
				var label = el( 'span', 'local-ads-label', { 'aria-hidden': 'true' } );
				label.textContent = i18n.label || 'Advertisement';
				frame.appendChild( label );
			}
			frame.appendChild( closeBtn );
			popup.appendChild( frame );
			root.appendChild( popup );

			var timers = [];
			var closed = false;
			var previousFocus = doc.activeElement;

			function later( fn, ms ) {
				timers.push( window.setTimeout( fn, ms ) );
			}

			function focusables() {
				return [].slice.call( popup.querySelectorAll( 'a[href], button:not([disabled])' ) ).filter( function ( n ) {
					return ! n.classList.contains( 'local-ads-close-pending' );
				} );
			}

			function onKey( e ) {
				if ( e.key === 'Escape' || e.key === 'Esc' ) {
					e.preventDefault();
					close( 'manual' );
				} else if ( e.key === 'Tab' ) {
					var items = focusables();
					if ( ! items.length ) {
						e.preventDefault();
						popup.focus();
						return;
					}
					var first = items[ 0 ];
					var lastItem = items[ items.length - 1 ];
					if ( e.shiftKey && ( doc.activeElement === first || doc.activeElement === popup ) ) {
						e.preventDefault();
						lastItem.focus();
					} else if ( ! e.shiftKey && doc.activeElement === lastItem ) {
						e.preventDefault();
						first.focus();
					} else if ( ! popup.contains( doc.activeElement ) ) {
						e.preventDefault();
						first.focus();
					}
				}
			}

			function close( reason ) {
				if ( closed ) {
					return;
				}
				closed = true;
				timers.forEach( window.clearTimeout );
				doc.removeEventListener( 'keydown', onKey, true );
				root.classList.add( 'local-ads-closing' );
				window.setTimeout( function () {
					if ( root.parentNode ) {
						root.parentNode.removeChild( root );
					}
				}, reduce ? 0 : 180 );
				if ( previousFocus && previousFocus.focus && doc.contains( previousFocus ) ) {
					try {
						previousFocus.focus( { preventScroll: true } );
					} catch ( e ) {}
				}
				if ( active === controller ) {
					active = null;
				}
				log( 'closed', ad.id, reason );
				if ( ! preview && reason !== 'replaced' ) {
					markClosed( g );
				}
				if ( opts.onClose ) {
					opts.onClose( reason );
				}
			}

			closeBtn.addEventListener( 'click', function () {
				close( 'manual' );
			} );
			if ( overlay && g.overlayClose ) {
				overlay.addEventListener( 'click', function () {
					close( 'manual' );
				} );
			}
			if ( link ) {
				var onActivate = function ( e ) {
					if ( e.type === 'auxclick' && e.button !== 1 ) {
						return;
					}
					if ( ! preview ) {
						announce( ad, 'click' );
						track( ad, 'click' );
					} else {
						e.preventDefault();
					}
					if ( ad.closeOnClick ) {
						window.setTimeout( function () {
							close( 'click' );
						}, 0 );
					}
				};
				link.addEventListener( 'click', onActivate );
				link.addEventListener( 'auxclick', onActivate );
			}
			doc.addEventListener( 'keydown', onKey, true );

			doc.body.appendChild( root );
			// Force layout so the entrance animation always runs.
			void root.offsetWidth; // eslint-disable-line no-void
			root.classList.add( 'local-ads-open' );
			later( function () {
				root.classList.remove( 'local-ads-entering' );
			}, ( reduce ? 1 : g.speed || 400 ) + 50 );

			try {
				( g.closeDelay > 0 ? popup : closeBtn ).focus( { preventScroll: true } );
			} catch ( e ) {}

			if ( g.closeDelay > 0 ) {
				later( function () {
					closeBtn.classList.remove( 'local-ads-close-pending' );
				}, g.closeDelay * 1000 );
			}

			if ( ad.shake && ! reduce ) {
				later( function () {
					frame.classList.add( 'local-ads-shaking' );
					later( function () {
						frame.classList.remove( 'local-ads-shaking' );
					}, Math.max( 1, ad.shakeDur ) * 1000 );
				}, Math.max( 0, ad.shakeDelay ) * 1000 );
			}

			if ( ad.autoClose > 0 ) {
				later( function () {
					close( 'auto' );
				}, ad.autoClose * 1000 );
			}

			// Stop at the scheduled end even if the popup is still open.
			if ( ! preview && ad.end ) {
				var remaining = ( ad.end - nowSec() ) * 1000;
				if ( remaining > 0 && remaining < 86400000 ) {
					later( function () {
						close( 'expired' );
					}, remaining );
				}
			}

			var controller = { close: close, root: root, ad: ad };
			active = controller;
			return controller;
		} );
	}

	/* ---------- Triggers ---------- */

	function arm( ad, g, fire ) {
		var fired = false;
		var lastCheck = 0;
		var pending = null;
		var timers = [];

		function go() {
			if ( fired ) {
				return;
			}
			fired = true;
			window.removeEventListener( 'scroll', onScroll );
			window.clearTimeout( pending );
			timers.forEach( window.clearTimeout );
			fire();
		}

		function scrollMax() {
			var de = document.documentElement;
			var body = document.body;
			var height = Math.max( de.scrollHeight, body ? body.scrollHeight : 0 );
			return Math.max( 0, height - window.innerHeight );
		}

		function check() {
			lastCheck = Date.now();
			var y =window.pageYOffset || document.documentElement.scrollTop || 0;
			var max = scrollMax();
			var atBottom = max > 0 && y >= max - 2;
			if ( ad.trigger === 'scroll_start' ) {
				if ( y > 0 ) {
					go();
				}
			} else if ( ad.trigger === 'pixels' ) {
				if ( y >= ad.tPx || atBottom ) {
					go();
				}
			} else if ( ad.trigger === 'percent' ) {
				if ( ( max > 0 && ( y / max ) * 100 >= ad.tPct ) || atBottom ) {
					go();
				}
			}
		}

		// Throttled to one check per 100ms, with a trailing check so the final position counts.
		function onScroll() {
			if ( Date.now() - lastCheck >= 100 ) {
				check();
			} else if ( ! pending ) {
				pending = window.setTimeout( function () {
					pending = null;
					check();
				}, 100 );
			}
		}

		function noScrollFallback() {
			if ( ! fired && g.noScroll > 0 && scrollMax() <= 0 ) {
				log( 'page cannot scroll, using fallback delay', g.noScroll );
				timers.push( window.setTimeout( go, g.noScroll * 1000 ) );
			}
		}

		switch ( ad.trigger ) {
			case 'immediate':
				go();
				break;
			case 'delay':
				timers.push( window.setTimeout( go, Math.max( 0, ad.tDelay ) * 1000 ) );
				break;
			default:
				window.addEventListener( 'scroll', onScroll, { passive: true } );
				if ( document.readyState === 'complete' ) {
					noScrollFallback();
				} else {
					window.addEventListener( 'load', noScrollFallback );
				}
		}
	}

	/* ---------- Main ---------- */

	function start( data ) {
		var g = data.global || {};
		offset = Math.round( ( data.now || Date.now() / 1000 ) - Date.now() / 1000 );
		tzOffset = data.tz || 0;
		currentVisit = visitId();

		var ads = ( data.ads || [] ).filter( function ( ad ) {
			if ( ! inSchedule( ad ) ) {
				log( 'skip (schedule)', ad.id );
				return false;
			}
			if ( ! frequencyAllows( ad ) ) {
				log( 'skip (frequency)', ad.id );
				return false;
			}
			return true;
		} );

		if ( ! ads.length ) {
			log( 'no eligible ads' );
			return;
		}
		if ( ! globalAllows( g ) ) {
			log( 'global frequency restriction active' );
			return;
		}

		var ad = choose( ads, g.rotation );
		log( 'selected', ad.id, 'rotation', g.rotation, 'trigger', ad.trigger );

		arm( ad, g, function () {
			// Re-verify at trigger time: the schedule may have ended, or another tab may have shown it.
			if ( ! inSchedule( ad ) || ! frequencyAllows( ad ) || ! globalAllows( g ) ) {
				log( 'no longer eligible at trigger time', ad.id );
				return;
			}
			open( ad, g, {} ).then( function () {
				markShown( ad );
				if ( g.rotation === 'sequential' ) {
					write( local, 'seq', ad.id );
				}
				announce( ad, 'impression' );
				if ( g.track ) {
					track( ad, 'impression' );
				}
			} ).catch( function () {
				log( 'popup failed (image did not load)', ad.id );
			} );
		} );
	}

	function init() {
		if ( ! cfg.ajaxUrl || ! window.fetch || ! window.Promise ) {
			return;
		}
		var ctx = cfg.ctx || {};
		var params = [
			'action=local_ads_get',
			'type=' + encodeURIComponent( ctx.type || 'other' ),
			'id=' + encodeURIComponent( ctx.id || 0 ),
			'checkout=' + ( ctx.checkout ? 1 : 0 ),
			'cart=' + ( ctx.cart ? 1 : 0 ),
			'account=' + ( ctx.account ? 1 : 0 ),
			'path=' + encodeURIComponent( window.location.pathname + window.location.search ),
			'_=' + Date.now(),
		];
		window.fetch( cfg.ajaxUrl + ( cfg.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + params.join( '&' ), {
			credentials: 'same-origin',
			cache: 'no-store',
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( res ) {
			if ( res && res.success && res.data ) {
				start( res.data );
			}
		} ).catch( function ( e ) {
			log( 'could not load ads', e );
		} );
	}

	window.LocalAds = {
		version: '1.0.1',
		/**
		 * Admin preview: renders immediately, never tracks, never stores visitor state.
		 */
		preview: function ( ad, g, opts ) {
			if ( active ) {
				active.close( 'replaced' );
			}
			opts = opts || {};
			opts.preview = true;
			return open( ad, g || {}, opts );
		},
		close: function () {
			if ( active ) {
				active.close( 'api' );
			}
		},
	};

	if ( ! cfg.preview ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', init );
		} else {
			init();
		}
	}
}( window, document ) );
