<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * @covers \BugzillaQuery
 * @covers \BugzillaBaseQuery
 * @covers \BugzillaRESTQuery
 */
class BugzillaQueryTest extends MediaWikiIntegrationTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->setMwGlobals([
            'wgBugzillaMethod' => 'REST',
            'wgBugzillaRESTURL' => 'https://bugzilla.example.org/rest',
            'wgBugzillaURL' => 'https://bugzilla.example.org',
            'wgBugzillaDefaultFields' => ['id', 'summary', 'priority', 'status'],
        ]);
    }

    /**
     * @dataProvider prepareOptionsProvider
     *
     * @param string $json     JSON options structure defined in <bugzilla /> element.
     * @param array  $default  default fields to include in query
     * @param array  $expected resulting options array
     */
    public function testPrepareOptions($json, $default, $expected)
    {
        $q = BugzillaQuery::create('bug', $json, 'title');
        $this->assertEquals($expected, $q->prepare_options($json, $default));
    }

    public static function prepareOptionsProvider()
    {
        $default_includes = ['incA', 'incB'];
        return [
            'no query at all falls back to the default fields' => [
                '',
                [],
                ['include_fields' => []]
            ],
            'an empty JSON object falls back to the default fields' => [
                " { \n } ",
                ['default'],
                ['include_fields' => ['default']]
            ],
            'other options are kept alongside the default fields' => [
                '{"other":"options"}',
                $default_includes,
                [
                    'include_fields' => $default_includes,
                    'other' => 'options'
                ]
            ],
            'a single requested field replaces the defaults' => [
                '{"include_fields": ["C"]}',
                $default_includes,
                [
                    'include_fields' => ['C']
                ]
            ],
            'a JSON array of fields is used as given' => [
                '{"include_fields": ["json", "array"]}',
                $default_includes,
                [
                    'include_fields' => ['json', 'array']
                ]
            ],
            'a comma separated string of fields is split' => [
                '{"include_fields": "json,string"}',
                $default_includes,
                [
                    'include_fields' => ['json', 'string']
                ]
            ],
            'invalid JSON produces no options' => [
                'invalid JSON',
                $default_includes,
                null
            ],
        ];
    }

    public function testInvalidJsonOptionsAreReportedAsAnError()
    {
        $q = BugzillaQuery::create('bug', 'invalid JSON', 'title');

        $this->assertSame('Query options must be valid JSON.', $q->error);
    }

    /**
     * Valid JSON, but not the object of query options the tag body is meant to
     * hold. Reported rather than fataling the page.
     *
     * @dataProvider nonObjectBodyProvider
     */
    public function testATagBodyThatIsNotAJsonObjectIsReportedAsAnError($body)
    {
        $q = BugzillaQuery::create('bug', $body, 'title');

        $this->assertSame('Query options must be valid JSON.', $q->error);
    }

    public static function nonObjectBodyProvider()
    {
        return [
            'a number' => ['123'],
            'a quoted string' => ['"product"'],
            'true' => ['true'],
            'false' => ['false'],
        ];
    }

    /**
     * @dataProvider rebaseFieldsProvider
     */
    public function testRebaseFields($request, $synthetic, $expected)
    {
        $q = BugzillaQuery::create('bug', '{}', 'title');

        $this->assertEquals($expected, $q->rebase_fields($request, $synthetic));
    }

    public static function rebaseFieldsProvider()
    {
        return [
            'already a superset of the synthetic fields' => [ ['A', 'B', 'C'], ['A', 'B'], ['A', 'B', 'C'] ],
            'missing synthetic fields are added'         => [ ['A'],           ['A', 'B'], ['A', 'B']      ],
            'the result is sorted'                       => [ ['C'],           ['B', 'A'], ['A', 'B', 'C'] ],
        ];
    }

    public function testFieldsNeededOnlyForRenderingAreFetchedButNotDisplayed()
    {
        $q = BugzillaQuery::create('bug', '{"include_fields": ["summary"]}', 'title');

        $this->assertSame(
            ['summary'],
            $q->options['include_fields'],
            'the columns are the ones the wiki page asked for'
        );
        $this->assertSame(
            ['id', 'priority', 'status', 'summary'],
            $q->rebased_options()['include_fields'],
            'the request also carries the fields the templates need'
        );
    }

    public function testTheFullQueryUrlRepeatsEachValueOfAnArrayOption()
    {
        $q = BugzillaQuery::create(
            'bug',
            '{"product": "Bugzilla", "include_fields": ["id", "summary"]}',
            'title'
        );

        $this->assertSame(
            'https://bugzilla.example.org/buglist.cgi'
                . '?product=Bugzilla&include_fields=id&include_fields=summary',
            $q->full_query_url()
        );
    }

    public function testTheFullQueryUrlEscapesValues()
    {
        $q = BugzillaQuery::create('bug', '{"whiteboard": "[needs triage]"}', 'title');

        $this->assertStringContainsString(
            'whiteboard=%5Bneeds+triage%5D',
            $q->full_query_url()
        );
    }
}
