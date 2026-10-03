/**
 * Local Ads admin scripts. Vanilla JS, no dependencies.
 */
( function ( window, document ) {
	'use strict';

	var A = window.LocalAdsAdmin || {};
	var t = A.i18n || {};

	function $( sel, ctx ) {
		return ( ctx || document ).querySelector( sel );
	}
	function $$( sel, ctx ) {
		return [].slice.call( ( ctx || document ).querySelectorAll( sel ) );
	}
	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text !== undefined ) {
			n.textContent = text;
		}
		return n;
	}

	function post( action, data ) {
		var body = data instanceof window.FormData ? data : new window.FormData();
		if ( ! ( data instanceof window.FormData ) && data ) {
			Object.keys( data ).forEach( function ( k ) {
				body.append( k, data[ k ] );
			} );
		}
		body.set( 'action', action );
		body.set( 'nonce', A.nonce );
		return window.fetch( A.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) {
			return r.json();
		} );
	}

	/* ---------- Conditional fields: data-la-when="field name" data-la-is="a|b" ---------- */

	function fieldValue( form, name ) {
		var inputs = $$( '[name="' + name.replace( /"/g, '\\"' ) + '"]', form );
		if ( ! inputs.length ) {
			return '';
		}
		var box = inputs.filter( function ( i ) {
			return i.type === 'checkbox';
		} )[ 0 ];
		if ( box ) {
			return box.checked ? box.value : '0';
		}
		var radios = inputs.filter( function ( i ) {
			return i.type === 'radio';
		} );
		if ( radios.length ) {
			var on = radios.filter( function ( r ) {
				return r.checked;
			} )[ 0 ];
			return on ? on.value : '';
		}
		return inputs[ inputs.length - 1 ].value;
	}

	function applyConditions() {
		$$( '[data-la-when]' ).forEach( function ( node ) {
			var form = node.closest( 'form' ) || document;
			var allowed = ( node.getAttribute( 'data-la-is' ) || '' ).split( '|' );
			var show = allowed.indexOf( fieldValue( form, node.getAttribute( 'data-la-when' ) ) ) !== -1;
			node.hidden = ! show;
		} );
	}
	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.name ) {
			applyConditions();
		}
		// Mirror controls (same setting shown on two tabs).
		var mirror = e.target && e.target.getAttribute && e.target.getAttribute( 'data-la-mirror' );
		if ( mirror ) {
			var target = $( '[name="' + mirror + '"]' );
			if ( target ) {
				target.value = e.target.value;
				target.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
			}
		} else if ( e.target && e.target.name ) {
			$$( '[data-la-mirror="' + e.target.name + '"]' ).forEach( function ( m ) {
				m.value = e.target.value;
			} );
		}
	} );

	/* ---------- Settings tabs ---------- */

	function initTabs() {
		var tabs = $$( '.la-tabs [data-la-tab]' );
		if ( ! tabs.length ) {
			return;
		}
		var input = $( '[data-la-tab-input]' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var key = tab.getAttribute( 'data-la-tab' );
				tabs.forEach( function ( x ) {
					var on = x === tab;
					x.classList.toggle( 'nav-tab-active', on );
					if ( on ) {
						x.setAttribute( 'aria-current', 'page' );
					} else {
						x.removeAttribute( 'aria-current' );
					}
				} );
				$$( '[data-la-panel]' ).forEach( function ( p ) {
					p.hidden = p.getAttribute( 'data-la-panel' ) !== key;
				} );
				if ( input ) {
					input.value = key;
				}
				try {
					window.history.replaceState( null, '', tab.href );
				} catch ( err ) {}
			} );
		} );
	}

	/* ---------- Misc: confirm, list filter, replace upload ---------- */

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( '.la-confirm' );
		if ( link && ! window.confirm( link.getAttribute( 'data-confirm' ) || t.confirm ) ) {
			e.preventDefault();
		}
	} );

	document.addEventListener( 'input', function ( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'la-filter' ) ) {
			return;
		}
		var list = document.getElementById( e.target.getAttribute( 'data-target' ) );
		var q = e.target.value.toLowerCase();
		$$( 'label', list ).forEach( function ( l ) {
			l.hidden = q !== '' && l.textContent.toLowerCase().indexOf( q ) === -1;
		} );
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( e.target.classList && e.target.classList.contains( 'la-replace-input' ) && e.target.files.length ) {
			e.target.closest( 'form' ).submit();
		}
	} );

	/* ---------- Dialog helper (native <dialog>) ---------- */

	function dialog( title, cls ) {
		var d = el( 'dialog', 'la-dialog ' + ( cls || '' ) );
		d.setAttribute( 'aria-label', title );
		var head = el( 'div', 'la-dialog__head' );
		head.appendChild( el( 'h2', 'la-dialog__title', title ) );
		var x = el( 'button', 'la-dialog__close' );
		x.type = 'button';
		x.setAttribute( 'aria-label', t.close || 'Close' );
		x.innerHTML = '<span aria-hidden="true">&times;</span>';
		x.addEventListener( 'click', function () {
			d.close();
		} );
		head.appendChild( x );
		var body = el( 'div', 'la-dialog__body' );
		d.appendChild( head );
		d.appendChild( body );
		d.addEventListener( 'close', function () {
			if ( d.parentNode ) {
				d.parentNode.removeChild( d );
			}
		} );
		d.addEventListener( 'click', function ( e ) {
			if ( e.target === d ) {
				d.close();
			}
		} );
		document.body.appendChild( d );
		return { node: d, head: head, body: body };
	}

	/* ---------- Image field and media picker ---------- */

	function validFile( file ) {
		var ok = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
		if ( ok.indexOf( file.type ) === -1 ) {
			return t.badType;
		}
		if ( A.maxUpload && file.size > A.maxUpload ) {
			return t.tooLarge;
		}
		return '';
	}

	function setImage( field, item ) {
		$( '[data-la-image-id]', field ).value = item ? item.id : 0;
		var prev = $( '[data-la-image-preview]', field );
		prev.innerHTML = '';
		if ( item ) {
			var img = el( 'img' );
			img.src = item.url;
			img.alt = '';
			prev.appendChild( img );
		} else {
			prev.appendChild( el( 'span', 'la-muted', '—' ) );
		}
		$( '[data-la-image-meta]', field ).textContent = item ? item.filename + ' · ' + item.width + '×' + item.height + ' · ' + item.size : '';
		$( '[data-la-image-remove]', field ).hidden = ! item;
		field.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
	}

	function openPicker( field ) {
		var dlg = dialog( t.select, 'la-dialog--picker' );
		var bar = el( 'div', 'la-picker__bar' );
		var upLabel = el( 'label', 'button button-secondary' );
		upLabel.textContent = t.upload;
		var upInput = el( 'input', 'screen-reader-text' );
		upInput.type = 'file';
		upInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
		upLabel.appendChild( upInput );
		var status = el( 'span', 'la-picker__status' );
		status.setAttribute( 'role', 'status' );
		bar.appendChild( upLabel );
		bar.appendChild( status );
		var grid = el( 'div', 'la-picker__grid' );
		grid.setAttribute( 'role', 'listbox' );
		grid.setAttribute( 'aria-label', t.select );
		var foot = el( 'div', 'la-dialog__foot' );
		var use = el( 'button', 'button button-primary', t.use );
		use.type = 'button';
		use.disabled = true;
		foot.appendChild( use );
		dlg.body.appendChild( bar );
		dlg.body.appendChild( grid );
		dlg.node.appendChild( foot );

		var items = [];
		var chosen = null;
		var currentId = parseInt( $( '[data-la-image-id]', field ).value, 10 ) || 0;

		function choose( item, btn ) {
			chosen = item;
			$$( '.la-picker__item', grid ).forEach( function ( b ) {
				b.setAttribute( 'aria-selected', b === btn ? 'true' : 'false' );
			} );
			use.disabled = ! item;
		}

		function draw() {
			grid.innerHTML = '';
			if ( ! items.length ) {
				grid.appendChild( el( 'p', 'la-muted', t.noImages ) );
				return;
			}
			items.forEach( function ( item ) {
				var b = el( 'button', 'la-picker__item' );
				b.type = 'button';
				b.setAttribute( 'role', 'option' );
				b.setAttribute( 'aria-selected', 'false' );
				b.setAttribute( 'aria-label', item.filename );
				var img = el( 'img' );
				img.src = item.url;
				img.alt = '';
				img.loading = 'lazy';
				b.appendChild( img );
				b.appendChild( el( 'span', 'la-picker__name', item.filename ) );
				b.addEventListener( 'click', function () {
					choose( item, b );
				} );
				b.addEventListener( 'dblclick', function () {
					choose( item, b );
					use.click();
				} );
				grid.appendChild( b );
				if ( item.id === currentId ) {
					choose( item, b );
				}
			} );
		}

		upInput.addEventListener( 'change', function () {
			var file = upInput.files[ 0 ];
			if ( ! file ) {
				return;
			}
			var problem = validFile( file );
			if ( problem ) {
				status.textContent = problem;
				status.className = 'la-picker__status is-error';
				upInput.value = '';
				return;
			}
			status.textContent = t.uploading;
			status.className = 'la-picker__status';
			var fd = new window.FormData();
			fd.append( 'file', file );
			post( 'local_ads_media_upload', fd ).then( function ( res ) {
				upInput.value = '';
				if ( ! res || ! res.success ) {
					status.textContent = ( res && res.data && res.data.message ) || t.failed;
					status.className = 'la-picker__status is-error';
					return;
				}
				status.textContent = '';
				items.unshift( res.data );
				currentId = res.data.id;
				draw();
			} ).catch( function () {
				status.textContent = t.failed;
				status.className = 'la-picker__status is-error';
			} );
		} );

		use.addEventListener( 'click', function () {
			if ( chosen ) {
				setImage( field, chosen );
				dlg.node.close();
			}
		} );

		dlg.node.showModal();
		post( 'local_ads_media_list', {} ).then( function ( res ) {
			items = res && res.success ? res.data : [];
			draw();
		} );
	}

	function initImageFields() {
		$$( '[data-la-image]' ).forEach( function ( field ) {
			$( '[data-la-image-select]', field ).addEventListener( 'click', function () {
				openPicker( field );
			} );
			$( '[data-la-image-remove]', field ).addEventListener( 'click', function () {
				setImage( field, null );
			} );
		} );
	}

	/* ---------- Schedule conflict check ---------- */

	function initConflicts() {
		var box = $( '[data-la-conflicts]' );
		var form = $( '#la-ad-form' );
		if ( ! box || ! form ) {
			return;
		}
		var timer = null;
		function check() {
			var get = function ( n ) {
				return form.elements[ n ] ? form.elements[ n ].value : '';
			};
			post( 'local_ads_check_schedule', {
				id: get( 'id' ),
				start_date: get( 'start_date' ),
				start_time: get( 'start_time' ),
				end_date: get( 'end_date' ),
				end_time: get( 'end_time' ),
			} ).then( function ( res ) {
				box.innerHTML = '';
				if ( ! res || ! res.success ) {
					return;
				}
				var level = res.data.level;
				var cls = level === 'error' ? 'notice-error' : level === 'warning' ? 'notice-warning' : 'notice-success';
				var n = el( 'div', 'notice inline ' + cls );
				n.appendChild( el( 'p', '', res.data.message ) );
				if ( res.data.items && res.data.items.length ) {
					var ul = el( 'ul', 'la-conflict-list' );
					res.data.items.forEach( function ( i ) {
						ul.appendChild( el( 'li', '', i ) );
					} );
					n.appendChild( ul );
				}
				box.appendChild( n );
			} );
		}
		$$( '[data-la-schedule]', form ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( check, 250 );
			} );
		} );
		if ( form.elements.start_date.value || form.elements.end_date.value ) {
			check();
		}
	}

	/* ---------- Preview ---------- */

	function openPreview( adId, form ) {
		var dlg = dialog( t.preview, 'la-dialog--preview' );
		var bar = el( 'div', 'la-preview__bar' );
		var desk = el( 'button', 'button', t.desktop );
		var mob = el( 'button', 'button', t.mobile );
		var replay = el( 'button', 'button', t.replay );
		[ desk, mob, replay ].forEach( function ( b ) {
			b.type = 'button';
			bar.appendChild( b );
		} );
		bar.appendChild( el( 'span', 'la-muted', t.previewNote ) );
		var stage = el( 'div', 'la-preview__stage is-desktop' );
		var frame = el( 'iframe', 'la-preview__frame' );
		frame.title = t.preview;
		frame.src = A.previewUrl + '&ad_id=' + ( adId || 0 );
		stage.appendChild( frame );
		dlg.body.appendChild( bar );
		dlg.body.appendChild( stage );

		var payload = null;
		function send() {
			if ( frame.contentWindow ) {
				frame.contentWindow.postMessage( payload || { type: 'local-ads-preview' }, window.location.origin );
			}
		}
		function device( mobile ) {
			stage.classList.toggle( 'is-mobile', mobile );
			stage.classList.toggle( 'is-desktop', ! mobile );
			desk.setAttribute( 'aria-pressed', mobile ? 'false' : 'true' );
			mob.setAttribute( 'aria-pressed', mobile ? 'true' : 'false' );
			window.setTimeout( send, 50 );
		}
		desk.addEventListener( 'click', function () {
			device( false );
		} );
		mob.addEventListener( 'click', function () {
			device( true );
		} );
		replay.addEventListener( 'click', send );
		device( false );

		function onMessage( e ) {
			if ( e.origin !== window.location.origin || e.source !== frame.contentWindow || ! e.data ) {
				return;
			}
			if ( e.data.type === 'local-ads-preview-ready' && form ) {
				var fd = new window.FormData( form );
				post( 'local_ads_preview_data', fd ).then( function ( res ) {
					if ( res && res.success ) {
						payload = { type: 'local-ads-preview', ad: res.data.ad, global: res.data.global };
					} else {
						payload = { type: 'local-ads-preview', ad: null };
					}
					send();
				} );
			}
		}
		window.addEventListener( 'message', onMessage );
		dlg.node.addEventListener( 'close', function () {
			window.removeEventListener( 'message', onMessage );
		} );
		dlg.node.showModal();
	}

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( '.la-preview-link' );
		if ( link ) {
			e.preventDefault();
			openPreview( link.getAttribute( 'data-ad-id' ), null );
		}
		var btn = e.target.closest && e.target.closest( '[data-la-preview-form]' );
		if ( btn ) {
			e.preventDefault();
			openPreview( 0, btn.closest( 'form' ) );
		}
		var lb = e.target.closest && e.target.closest( '[data-la-lightbox]' );
		if ( lb ) {
			e.preventDefault();
			var d = dialog( t.preview, 'la-dialog--lightbox' );
			var img = el( 'img', 'la-lightbox__img' );
			img.src = lb.getAttribute( 'data-la-lightbox' );
			img.alt = '';
			d.body.appendChild( img );
			d.node.showModal();
		}
	} );

	/* ---------- Charts: small multiples sharing one date axis (no dual axes) ---------- */

	var NS = 'http://www.w3.org/2000/svg';
	function svg( tag, attrs ) {
		var n = document.createElementNS( NS, tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			n.setAttribute( k, attrs[ k ] );
		} );
		return n;
	}
	function niceMax( v ) {
		if ( v <= 0 ) {
			return 1;
		}
		var p = Math.pow( 10, Math.floor( Math.log( v ) / Math.LN10 ) );
		var n = v / p;
		var nice = n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10;
		return nice * p;
	}
	function fmtInt( v ) {
		return Math.round( v ).toLocaleString();
	}
	function fmtPct( v ) {
		return ( Math.round( v * 100 ) / 100 ).toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + '%';
	}
	function fmtAxis( v, pct ) {
		if ( pct ) {
			return ( Math.round( v * 10 ) / 10 ) + '%';
		}
		return v >= 1000 ? ( Math.round( v / 100 ) / 10 ) + 'k' : String( Math.round( v * 10 ) / 10 );
	}

	function drawChart( wrap, data, m, width ) {
		var H = 120;
		var padL = 40;
		var padR = 8;
		var padT = 8;
		var padB = 22;
		var W = Math.max( 280, width );
		var innerW = W - padL - padR;
		var innerH = H - padT - padB;
		var n = Math.max( 1, data.length );
		var band = innerW / n;
		var values = data.map( function ( d ) {
			return d[ m.k ];
		} );
		var max = niceMax( Math.max.apply( null, values.concat( [ 0 ] ) ) );
		var y = function ( v ) {
			return padT + innerH - ( v / max ) * innerH;
		};

		var box = el( 'div', 'la-chart' );
		var total = m.k === 'r' ? null : values.reduce( function ( a, b ) {
			return a + b;
		}, 0 );
		var head = el( 'div', 'la-chart__head' );
		var sw = el( 'span', 'la-chart__swatch' );
		sw.style.background = m.color;
		head.appendChild( sw );
		head.appendChild( el( 'span', 'la-chart__title', m.label ) );
		if ( total !== null ) {
			head.appendChild( el( 'span', 'la-chart__total', fmtInt( total ) ) );
		}
		box.appendChild( head );

		var s = svg( 'svg', { viewBox: '0 0 ' + W + ' ' + H, width: '100%', height: H, role: 'img', 'aria-label': m.label } );
		[ 0, 0.5, 1 ].forEach( function ( f ) {
			var gy = y( max * f );
			s.appendChild( svg( 'line', { x1: padL, x2: W - padR, y1: gy, y2: gy, class: 'la-chart__grid' } ) );
			var lab = svg( 'text', { x: padL - 6, y: gy + 4, class: 'la-chart__axis', 'text-anchor': 'end' } );
			lab.textContent = fmtAxis( max * f, m.k === 'r' );
			s.appendChild( lab );
		} );

		var step = Math.max( 1, Math.ceil( n / 7 ) );
		data.forEach( function ( d, i ) {
			// Regular ticks, plus the last day; skip a tick that would collide with the last label.
			if ( i === n - 1 || ( i % step === 0 && n - 1 - i >= Math.ceil( step / 2 ) + ( step === 1 ? 0 : 1 ) ) ) {
				var tx = svg( 'text', { x: padL + band * i + band / 2, y: H - 6, class: 'la-chart__axis', 'text-anchor': 'middle' } );
				tx.textContent = d.l;
				s.appendChild( tx );
			}
		} );

		if ( m.type === 'bar' ) {
			var bw = Math.max( 2, Math.min( 28, band - 2 ) );
			data.forEach( function ( d, i ) {
				var v = d[ m.k ];
				if ( v <= 0 ) {
					return;
				}
				var x = padL + band * i + ( band - bw ) / 2;
				var top = y( v );
				var h = padT + innerH - top;
				var r = Math.min( 4, bw / 2, h );
				var path = 'M' + x + ',' + ( padT + innerH ) + 'V' + ( top + r ) + 'Q' + x + ',' + top + ' ' + ( x + r ) + ',' + top + 'H' + ( x + bw - r ) + 'Q' + ( x + bw ) + ',' + top + ' ' + ( x + bw ) + ',' + ( top + r ) + 'V' + ( padT + innerH ) + 'Z';
				s.appendChild( svg( 'path', { d: path, fill: m.color, class: 'la-chart__bar', 'data-i': i } ) );
			} );
		} else {
			var pts = data.map( function ( d, i ) {
				return ( padL + band * i + band / 2 ) + ',' + y( d[ m.k ] );
			} ).join( ' ' );
			s.appendChild( svg( 'polyline', { points: pts, fill: 'none', stroke: m.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' } ) );
		}
		s.appendChild( svg( 'line', { x1: padL, x2: W - padR, y1: padT + innerH, y2: padT + innerH, class: 'la-chart__base' } ) );

		var cross = svg( 'line', { y1: padT, y2: padT + innerH, class: 'la-chart__cross', visibility: 'hidden' } );
		var dot = svg( 'circle', { r: 4, fill: m.color, stroke: '#fff', 'stroke-width': 2, visibility: 'hidden' } );
		s.appendChild( cross );
		s.appendChild( dot );

		var tip = el( 'div', 'la-chart__tip' );
		tip.hidden = true;
		box.appendChild( s );
		box.appendChild( tip );

		data.forEach( function ( d, i ) {
			var hit = svg( 'rect', { x: padL + band * i, y: padT, width: band, height: innerH, fill: 'transparent', tabindex: '-1' } );
			hit.addEventListener( 'mouseenter', function () {
				var cx = padL + band * i + band / 2;
				cross.setAttribute( 'x1', cx );
				cross.setAttribute( 'x2', cx );
				cross.setAttribute( 'visibility', 'visible' );
				dot.setAttribute( 'cx', cx );
				dot.setAttribute( 'cy', y( d[ m.k ] ) );
				dot.setAttribute( 'visibility', m.type === 'line' ? 'visible' : 'hidden' );
				$$( '.la-chart__bar', s ).forEach( function ( b ) {
					b.style.opacity = b.getAttribute( 'data-i' ) === String( i ) ? '1' : '0.45';
				} );
				tip.innerHTML = '';
				tip.appendChild( el( 'strong', '', d.l ) );
				[ [ t.impressions, fmtInt( d.i ) ], [ t.clicks, fmtInt( d.c ) ], [ t.ctr, fmtPct( d.r ) ] ].forEach( function ( row ) {
					var line = el( 'div', 'la-chart__tiprow' );
					line.appendChild( el( 'span', '', row[ 0 ] ) );
					line.appendChild( el( 'span', 'la-chart__tipval', row[ 1 ] ) );
					tip.appendChild( line );
				} );
				tip.hidden = false;
				var px = ( cx / W ) * box.clientWidth;
				tip.style.left = Math.min( Math.max( 0, px - 70 ), box.clientWidth - 150 ) + 'px';
			} );
			s.appendChild( hit );
		} );
		s.addEventListener( 'mouseleave', function () {
			tip.hidden = true;
			cross.setAttribute( 'visibility', 'hidden' );
			dot.setAttribute( 'visibility', 'hidden' );
			$$( '.la-chart__bar', s ).forEach( function ( b ) {
				b.style.opacity = '1';
			} );
		} );
		wrap.appendChild( box );
	}

	function renderCharts() {
		$$( '[data-la-charts]' ).forEach( function ( wrap ) {
			var data;
			try {
				data = JSON.parse( wrap.getAttribute( 'data-la-charts' ) || '[]' );
			} catch ( e ) {
				data = [];
			}
			wrap.innerHTML = '';
			var width = wrap.clientWidth;
			var cols = width >= 900 ? 3 : 1;
			var each = cols === 3 ? ( width - 32 ) / 3 : width;
			[
				{ k: 'i', label: t.impressions, color: '#2a78d6', type: 'bar' },
				{ k: 'c', label: t.clicks, color: '#eb6834', type: 'bar' },
				{ k: 'r', label: t.ctr, color: '#1baf7a', type: 'line' },
			].forEach( function ( m ) {
				drawChart( wrap, data, m, each );
			} );
		} );
	}

	var resizeTimer = null;
	window.addEventListener( 'resize', function () {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( renderCharts, 200 );
	} );

	/* ---------- Boot ---------- */

	function boot() {
		applyConditions();
		initTabs();
		initImageFields();
		initConflicts();
		renderCharts();
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}( window, document ) );
