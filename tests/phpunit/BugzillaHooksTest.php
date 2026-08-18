<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;

/**
 * @group Database
 *
 * @covers \BugzillaHooks
 */
class BugzillaHooksTest extends MediaWikiIntegrationTestCase
{

    use MockHttpTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setMwGlobals([
            'wgBugzillaMethod' => 'REST',
            'wgBugzillaRESTURL' => 'https://bugzilla.example.org/rest',
            'wgBugzillaURL' => 'https://bugzilla.example.org',
            'wgBugzillaTagName' => 'bugzilla',
            'wgBugzillaJqueryTable' => true,
            'wgBugzillaDefaultFields' => ['id', 'summary', 'priority', 'status'],
        ]);

        $this->installMockHttp($this->makeFakeHttpRequest('{"bugs": []}'));
    }

    /**
     * lengthMenu ships as a JSON string, but an array is what the setting holds
     * conceptually and what DataTables is handed, so administrators write it
     * both ways.
     *
     * @dataProvider lengthMenuProvider
     */
    public function testTheConfiguredLengthMenuReachesTheClientAsAnArray($configured)
    {
        $this->setMwGlobals('wgBugzillaTable', [
            'pageSize' => 25,
            'lengthMenu' => $configured,
        ]);

        $this->assertSame(
            [[10, 25], [10, 25]],
            $this->parseTag()->getJsConfigVars()['wgBugzillaTable']['lengthMenu']
        );
    }

    public static function lengthMenuProvider()
    {
        return [
            'a JSON string, as extension.json ships it' => ['[[10, 25], [10, 25]]'],
            'a PHP array, as the setting is shaped' => [[[10, 25], [10, 25]]],
        ];
    }

    private function parseTag(): ParserOutput
    {
        return MediaWikiServices::getInstance()->getParserFactory()->create()->parse(
            '<bugzilla>{"product": "Bugzilla"}</bugzilla>',
            Title::newFromText('Bugzilla test'),
            ParserOptions::newFromAnon()
        );
    }
}
