<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Parser\Parser;

class BugzillaHooks implements BeforePageDisplayHook, ParserFirstCallInitHook {

    public function onBeforePageDisplay( $out, $skin ): void {
        global $wgScriptPath;

        // Let the user optionally override bugzilla extension styles
        if( file_exists("$wgScriptPath/extensions/Bugzilla/web/css/custom.css") ) {
            $out->addStyle("$wgScriptPath/extensions/Bugzilla/web/css/custom.css");
        }
    }

    // Hook our callback function into the parser
    public function onParserFirstCallInit( $parser ) {
        global $wgBugzillaTagName;

        // Register the desired tag
        $parser->setHook( $wgBugzillaTagName, [ self::class, 'render' ] );

        // Let the other hooks keep processing
        return true;
    }

    // Function to be called when our tag is found by the parser
    public static function render( $input, array $args, Parser $parser, $frame=null ) {
        global $wgBugzillaJqueryTable;

        // We don't want the page to be cached
        // TODO: Not sure if we need this
        # $parser->disableCache();   # Disabled since it is deprecated and no longer working
        # $parser->getOutput()->updateCacheExpiry(0); # might be an alternative

        // TODO: Figure out to have the parser not do anything to our output
        // mediawiki docs are wrong :-(
        // error_log(print_r($parser->mStripState, true));
        // $parser->mStripState->addItem( 'nowiki', 'NOWIKI', true);
        // 'noparse' => true, 'isHTML' => true, 'markerType' => 'nowiki' );

        $input = $parser->recursiveTagParse($input, $frame);

        // Only pages that actually use the tag need the styles and the
        // DataTables plugin.
        $parser->getOutput()->addModules( [ 'ext.Bugzilla' ] );

        if( $wgBugzillaJqueryTable ) {
            $parser->getOutput()->setJsConfigVar( 'wgBugzillaTable', self::_table_options() );
        }

        // Create a new bugzilla object
        $bz = Bugzilla::create($args, $input, $parser->getTitle());

        // Show the desired output (or an error if there was one)
        $bz->fetch();
        return $bz->render();
    }

    protected static function _table_options() {
        global $wgBugzillaTable;

        return [
            'pageSize'   => $wgBugzillaTable['pageSize'],
            // DataTables wants the array itself, and the setting has always
            // held it as a literal for interpolation into JavaScript.
            'lengthMenu' => json_decode( $wgBugzillaTable['lengthMenu'], true ),
        ];
    }
}
