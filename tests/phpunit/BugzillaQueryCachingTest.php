<?php
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * The cache sits between a failed request and the reader, and its effects
 * outlive the request that caused them, so they are invisible from the
 * rendered page until the timeout expires.
 *
 * @covers \BugzillaBaseQuery
 */
class BugzillaQueryCachingTest extends MediaWikiIntegrationTestCase
{

    private const OPTIONS = '{"product": "Bugzilla"}';

    private const BUGS = ['bugs' => [['id' => 4001, 'summary' => 'A bug', 'status' => 'NEW']]];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setMwGlobals([
            'wgMainCacheType' => CACHE_HASH,
            'wgBugzillaCacheTimeOut' => 5,
            'wgBugzillaDefaultFields' => ['id', 'summary', 'priority', 'status'],
        ]);
    }

    public function testARepeatedQueryIsAnsweredFromTheCache()
    {
        $this->newQueryReturning(self::BUGS)->fetch();

        $second = $this->newQueryFailingWith('Bugzilla is unreachable');
        $second->fetch();

        $this->assertSame(self::BUGS, $second->data);
        $this->assertFalse($second->error);
    }

    public function testAFailedQueryDoesNotLeaveAnEmptyResultBehindForTheNextReader()
    {
        $this->newQueryFailingWith('Bugzilla is unreachable')->fetch();

        $second = $this->newQueryReturning(self::BUGS);
        $second->fetch();

        $this->assertSame(self::BUGS, $second->data);
    }

    public function testAFailedQueryReportsItsError()
    {
        $query = $this->newQueryFailingWith('Bugzilla is unreachable');

        $query->fetch();

        $this->assertSame('Bugzilla is unreachable', $query->error);
        $this->assertSame([], $query->data);
    }

    private function newQueryReturning(array $data): BugzillaFakeQuery
    {
        return new BugzillaFakeQuery('bug', self::OPTIONS, 'title', $data, false);
    }

    private function newQueryFailingWith(string $error): BugzillaFakeQuery
    {
        return new BugzillaFakeQuery('bug', self::OPTIONS, 'title', [], $error);
    }
}

/**
 * Stands in for the HTTP round trip so the caching around it can be observed.
 */
class BugzillaFakeQuery extends BugzillaBaseQuery
{

    private $response;

    private $failure;

    public function __construct($type, $options, $title, array $response, $failure)
    {
        parent::__construct($type, $options, $title);

        $this->response = $response;
        $this->failure = $failure;
    }

    public function _fetch_by_options()
    {
        if ($this->failure) {
            $this->error = $this->failure;
            return;
        }

        $this->data = $this->response;
    }
}
