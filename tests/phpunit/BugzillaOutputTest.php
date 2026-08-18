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

    /**
     * Field names come from the tag's JSON, so any page editor picks them. Tag output
     * reaches the reader through the strip state, which MediaWiki does not sanitize.
     *
     * @dataProvider displayProvider
     */
    public function testAFieldNameCannotBreakOutOfTheAttributeItIsRenderedIn($display)
    {
        $rendered = $this->renderWithField($display, "id' onmouseover='alert(1)");

        $this->assertStringNotContainsString("id' onmouseover=", $rendered);
    }

    /**
     * @dataProvider displayProvider
     */
    public function testAFieldNameWithNoValueOnTheBugIsEscaped($display)
    {
        $rendered = $this->renderWithField($display, '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public static function displayProvider()
    {
        return [
            'table' => ['table'],
            'list' => ['list'],
        ];
    }

    private function renderWithField(string $display, string $field): string
    {
        $output = Bugzilla::create([ 'display' => $display ], '{"product": "Bugzilla"}', 'title');
        $output->query->options['include_fields'] = ['status', $field];
        $output->query->data = ['bugs' => [['status' => 'NEW']]];

        return $output->render();
    }
}
