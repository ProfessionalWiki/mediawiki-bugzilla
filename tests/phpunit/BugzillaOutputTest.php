<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * @covers \BugzillaNumber
 * @covers \BugzillaOutput
 */
class BugzillaOutputTest extends MediaWikiIntegrationTestCase
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

    public function testNumberDisplayReportsAnErrorRatherThanCountingAnAbsentResult()
    {
        $output = Bugzilla::create(['display' => 'number'], '{"product": "Bugzilla"}', 'title');
        $output->query->error = 'Bugzilla is unreachable';

        $this->assertStringContainsString('Bugzilla is unreachable', $output->render());
    }

    public function testNumberDisplayCountsTheBugsReturned()
    {
        $output = Bugzilla::create(['display' => 'number'], '{"product": "Bugzilla"}', 'title');
        $output->query->data = ['bugs' => [['id' => 1], ['id' => 2], ['id' => 3]]];

        $this->assertSame('<span>3</span>', $output->render());
    }

    public function testAnErrorIsEscapedRatherThanRenderedAsMarkup()
    {
        $output = Bugzilla::create(['display' => 'table'], '{"product": "Bugzilla"}', 'title');
        $output->query->error = '<script>alert(1)</script>';

        $rendered = $output->render();

        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
    }
}
