/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

( function () {
	var options = mw.config.get( 'wgBugzillaTable' );

	// Absent unless $wgBugzillaJqueryTable is on for this page.
	if ( !options ) {
		return;
	}

	// The hook, rather than document ready: the module can execute before the
	// content is in the DOM, and the content can be replaced afterwards by
	// live preview. DataTables marks what it has taken over with .dataTable,
	// which keeps a second firing from re-initialising the same table.
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( 'table.bugzilla' ).not( '.dataTable' ).dataTable( {
			bJQueryUI: true,
			aLengthMenu: options.lengthMenu,
			iDisplayLength: options.pageSize,
			// Disable initial sort
			aaSorting: []
		} );
	} );
}() );
