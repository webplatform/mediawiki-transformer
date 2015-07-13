<?php

/**
 * WebPlatform Content Converter.
 */

namespace WebPlatform\ContentConverter\Tests\Entity;

use WebPlatform\ContentConverter\Entity\MediaWikiRevision;
use WebPlatform\ContentConverter\Entity\MediaWikiContributor;
use SimpleXMLElement;

/**
 * MediaWikiRevision test suite.
 *
 * @coversDefaultClass \WebPlatform\ContentConverter\Entity\MediaWikiRevision
 *
 * @author Renoir Boulanger <hello@renoirboulanger.com>
 */
class MediaWikiRevisionTest extends \PHPUnit_Framework_TestCase
{
    protected $instance;

    /** @var string An hardcoded revision XML */
    protected $revisionXml = "
        <revision>
            <id>69319</id>
            <parentid>69053</parentid>
            <timestamp>2014-09-08T19:05:23Z</timestamp>
            <contributor>
                <username>Jdoe</username>
                <id>42</id>
            </contributor>
            <comment>そ\nれぞれの値には、配列内で付与されたインデックス値である、</comment>
            <model>wikitext</model>
            <format>text/x-wiki</format>
            <text xml:space=\"preserve\" bytes=\"2\">Foo</text>
        </revision>";

    protected $cached_user_json = '{
            "user_email": "jdoe@example.org"
            ,"user_id": "42"
            ,"user_name": "Jdoe"
            ,"user_real_name": "John Doe"
            ,"user_email_authenticated": true
        }';

    public function setUp()
    {
        $this->instance = new MediaWikiRevision(new SimpleXMLElement($this->revisionXml), 1);
    }

    public function testIsMediaWikiDumpRevisionNode()
    {
        $xmlElement = new SimpleXMLElement($this->revisionXml);

        $this->assertTrue(MediaWikiRevision::isMediaWikiDumpRevisionNode($xmlElement));

        // Lets remove data we know require, make sure the Revision class tests it too
        unset($xmlElement->timestamp, $xmlElement->contributor);
        $this->assertFalse(MediaWikiRevision::isMediaWikiDumpRevisionNode($xmlElement));
    }

    public function testTimestampIdempotencyFormat()
    {
        $expected = '2014-09-08T19:05:23Z';
        $objTimestamp = $this->instance->getTimestamp()->format('Y-m-d\TH:i:sT');
        $this->assertSame($expected, $objTimestamp);

        $expectRfc2822Format = 'Mon, 08 Sep 2014 19:05:23 +0000';
        $this->assertSame($expectRfc2822Format, $this->instance->getTimestamp()->format(\DateTime::RFC2822));
    }

    /* This will be moved into Persistency soon
    public function testCommitArgsWithoutContributor()
    {
        $expectedValues = array(
                             'message' => "そ\nれぞれの値には、配列内で付与されたインデックス値である、"
                            ,'date' => 'Mon, 08 Sep 2014 19:05:23 +0000',
                        );
        $obj = $this->instance->commitArgs();
        $this->assertSame(asort($expectedValues), asort($obj));
    }

    public function testCommitArgsContributor()
    {
        $expectedValues = array(
                             'message' => "そ\nれぞれの値には、配列内で付与されたインデックス値である、"
                            ,'date' => 'Mon, 08 Sep 2014 19:05:23 +0000'
                            ,'author' => 'John Doe <jdoe@example.org>',
                        );
        $obj = $this->instance->commitArgs();
        $this->instance->setContributor(new MediaWikiContributor($this->cached_user_json));
        $this->assertSame(asort($expectedValues), asort($obj));
    }
    */

    /**
     * @expectedException \RuntimeException
     */
    public function testSetContributorExpectException()
    {
        $forged_user = '{
            "user_email": "mcfly@outatime.org"
            ,"user_name": "Mcfly"
            ,"user_id": "11"
            ,"user_real_name": "Marty McFly"
            ,"user_email_authenticated": true
        }';

        $this->instance->setContributor(new MediaWikiContributor($forged_user)/*, false <- to force NO validation*/);
    }

    public function testSetContributorEnforceNoValidation()
    {
        $forged_user = '{
            "user_email": "mcfly@outatime.org"
            ,"user_name": "Mcfly"
            ,"user_id": "11"
            ,"user_real_name": "Marty McFly"
            ,"user_email_authenticated": true
        }';

        $this->instance->setContributor(new MediaWikiContributor($forged_user), false);

        $this->assertInstanceOf('\WebPlatform\ContentConverter\Entity\MediaWikiContributor', $this->instance->getContributor());

        // Ensure that getContributorName returns the same value as if we did $revision->getContributor()->getName()
        // ... because we asked to NOT validate
        $this->assertSame($this->instance->getContributorName(), 'Mcfly');
        $this->assertSame($this->instance->getContributorId(), 11); // We want int!
    }

    public function testRevisionContributorProperty()
    {
        // Let’s say we dont want to use cached version of users, but we still
        // want to import a wiki, will the behavior be consistent too?
        $this->assertSame($this->instance->getContributorId(), 42); // We want int!
        $this->assertSame($this->instance->getContributorName(), 'Jdoe');
    }

    public function testToString()
    {
        // In the test fixture, we said that "Foo" was the content of the
        // contribution, let’s test that. Also, we may change the way the
        // content is generated, see MarkdownRevision for instance.
        $this->assertSame('Foo', (string) $this->instance);
    }

    public function testCommentRemovesNewlines()
    {
        // We added a \n char in the middle of this string (author don’t know what that says, BTW)
        // Making sure behavior is the same.
        $this->assertSame('そ れぞれの値には、配列内で付与されたインデックス値である、', $this->instance->getComment());
    }

    public function testSetComment()
    {
        $someComment = "Roads? Where we're going we don't need... roads!";
        $this->instance->setComment($someComment);

        $this->assertSame($someComment, $this->instance->getComment(), 'We should be able to override comment');
    }

    public function testGetFormat()
    {
        $this->assertSame('text/x-wiki', $this->instance->getFormat());
    }

    public function testGetModel()
    {
        $this->assertSame('wikitext', $this->instance->getModel());
    }
}
