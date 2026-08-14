<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * The HTTP round trip the extension exists to make. Every other suite replaces
 * or bypasses it, so without these the request itself is never executed.
 *
 * @covers \BugzillaRESTQuery
 */
class BugzillaRESTQueryTest extends MediaWikiIntegrationTestCase
{

    use MockHttpTrait;

    private const OPTIONS = '{"product": "Bugzilla"}';

    private const BUGS = ['bugs' => [['id' => 4001, 'summary' => 'A bug', 'status' => 'NEW']]];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setMwGlobals([
            'wgBugzillaMethod' => 'REST',
            'wgBugzillaRESTURL' => 'https://bugzilla.example.org/rest',
            'wgBugzillaURL' => 'https://bugzilla.example.org',
            'wgMainCacheType' => CACHE_NONE,
            'wgBugzillaCacheTimeOut' => 5,
            'wgBugzillaDefaultFields' => ['id', 'summary', 'priority', 'status'],
        ]);
    }

    public function testASuccessfulQueryReturnsTheBugsBugzillaSent()
    {
        $query = $this->queryAnswering(json_encode(self::BUGS));

        $query->fetch();

        $this->assertSame(self::BUGS, $query->data);
        $this->assertFalse($query->error);
    }

    /**
     * What a proxy or captive portal in front of Bugzilla answers. Decoding to
     * null and carrying on reads downstream as a successful query for no bugs.
     */
    public function testA200ThatIsNotJsonIsReportedRatherThanReadAsZeroBugs()
    {
        $query = $this->queryAnswering('<html>Proxy error</html>');

        $query->fetch();

        $this->assertSame('Bugzilla returned a response that is not valid JSON.', $query->error);
        $this->assertSame([], $query->data);
    }

    /**
     * The error box is one line, so a status carrying several messages must not
     * arrive as the wikitext bullet list Status::getMessage() would build.
     */
    public function testAnHttpErrorIsReportedAsASingleLine()
    {
        $query = $this->queryAnswering('Not Found', 404);

        $query->fetch();

        $this->assertSame(
            'There was a problem during the HTTP request: 404 Not Found',
            $query->error
        );
    }

    public function testTheRequestAsksBugzillaForTheFieldsThePageAskedFor()
    {
        $requestedUrl = null;
        $this->installMockHttp(function ($url) use (&$requestedUrl) {
            $requestedUrl = $url;
            return $this->makeFakeHttpRequest(json_encode(self::BUGS));
        });

        BugzillaQuery::create('bug', '{"include_fields": ["summary"]}', 'title')->fetch();

        $this->assertSame(
            'https://bugzilla.example.org/rest/bug?include_fields=summary',
            $requestedUrl
        );
    }

    private function queryAnswering(string $body, int $status = 200): BugzillaBaseQuery
    {
        $this->installMockHttp($this->makeFakeHttpRequest($body, $status));

        return BugzillaQuery::create('bug', self::OPTIONS, 'title');
    }
}
