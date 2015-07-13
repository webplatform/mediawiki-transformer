<?php

/**
 * WebPlatform Content Converter.
 */

namespace WebPlatform\ContentConverter\Entity;

use SimpleXMLElement;
use RuntimeException;

/**
 * Models a MediaWiki WikiPage Revision.
 *
 * Represent one revision from a page node.
 *
 * Revision MUST be child of a MediaWikiDocument (representing a page node),
 * accessible through the getRevisions() method.
 *
 * Here is a sample of a MediaWiki dumpBackupXml page node.
 *
 * <page>
 *   <title>WPD:Contributor Agreement</title>
 *   <ns>3000</ns>
 *   <id>5</id>
 *   <revision>
 *     <id>39</id>
 *     <parentid>10</parentid>
 *     <timestamp>2012-06-20T04:42:18Z</timestamp>
 *     <contributor>
 *       <username>Shepazu</username>
 *       <id>2</id>
 *     </contributor>
 *     <comment>removed warning</comment>
 *     <model>wikitext</model>
 *     <format>text/x-wiki</format>
 *     <text xml:space="preserve" bytes="1">'''Wikitext''' text.</text>
 *     <sha1>l37t3nh9pz0qgiakt2o6v11ofw812jd</sha1>
 *   </revision>
 * </page>
 *
 * @author Renoir Boulanger <hello@renoirboulanger.com>
 */
class MediaWikiRevision extends AbstractRevision
{
    /* String used in MediaWiki dumpBackup format node in a revision entry  */
    const FORMAT_WIKI = 'text/x-wiki';

    /* String used in MediaWiki dumpBackup model node in a revision entry  */
    const MODEL_WIKI = 'wikitext';

    protected $validate_contributor = true;

    /** @var string MediaWiki’s Content-Type format string name, e.g. "text/x-wiki" */
    protected $format = null;

    /** @var string MediaWiki content model name, e.g. "wikitext" */
    protected $model = null;

    /** @var int id of the MediaWiki from the user table */
    protected $contributor_id = null;

    /** @var string username of the MediaWiki user table */
    protected $contributor_name = null;

    /**
     * Constructs a WikiPage Revision object.
     *
     * @param SimpleXMLElement $revisionNode
     */
    public function __construct(SimpleXMLElement $revisionNode)
    {
        if (self::isMediaWikiDumpRevisionNode($revisionNode) === true) {
            if (!empty($revisionNode->comment)) {
                $this->setComment((string) $revisionNode->comment);
            }

            if (!empty($revisionNode->text)) {
                $this->setContent((string) $revisionNode->text);
            }

            $this->format = (string) $revisionNode->format;
            $this->model = (string) $revisionNode->model;

            // Format is: 2014-09-08T19:05:22Z so we know its in the Zulu Time Zone.
            $this->setTimestamp(new \DateTime($revisionNode->timestamp, new \DateTimeZone('Z')));

            // XML uses username node
            $this->contributor_name = (string) $revisionNode->contributor[0]->username;
            $this->contributor_id = (int) $revisionNode->contributor[0]->id;

            return $this;
        }

        throw new RuntimeException('SimpleXMLElement revision element did not contain required nodes');
    }

    /**
     * Validate if SimpleXMLElement has essential nodes.
     *
     * Essential:
     *   - text
     *   - timestamp
     *   - contributor
     *
     * Optionnal:
     *   - model
     *   - format
     *   - comment
     *   - minor
     *
     * @param SimpleXMLElement $revisionNode <revision> XML node from MediaWiki dumpBackup generated file
     *
     * @return bool [description]
     */
    public static function isMediaWikiDumpRevisionNode(SimpleXMLElement $revisionNode)
    {
        $checks[] = $revisionNode->getName() === 'revision';
        $checks[] = count($revisionNode->timestamp) == 1;
        $checks[] = count($revisionNode->contributor) >= 1;
        $checks[] = count($revisionNode->contributor->username) >= 1;
        $checks[] = count($revisionNode->text) == 1;
        if (in_array(false, $checks) === false) {
            // We have no failed tests, therefore we have all we need
            return true;
        }

        return false;
    }

    /**
     * set Author data object.
     *
     * @param Author $author               The Author of the change
     * @param bool   $validate_contributor Whether or not we should make sure the MediaWiki contributor node data match our JSON user cache
     */
    public function setContributor(MediaWikiContributor $author, $validate_contributor = true)
    {
        $this->validate_contributor = $validate_contributor;
        $u1 = $this->contributor_name;
        $u2 = $author->getName();

        if ($u2 !== $u1 && $this->validate_contributor === true) {
            throw new RuntimeException(sprintf('Contributor %s is not the same as %s', $u1, $u2));
        }

        $this->setAuthor($author);

        return $this;
    }

    public function getContributor()
    {
        if ($this->author instanceof Author) {
            return $this->author;
        }

        throw new RuntimeException('Author not linked to Revision, please make sure you explicitly call setContributor()');
    }

    public function getContributorName()
    {
        if ($this->author instanceof Author) {
            return $this->author->getName();
        }

        return $this->contributor_name;
    }

    public function getContributorId()
    {
        if ($this->author instanceof Author) {
            return $this->author->getId();
        }

        return $this->contributor_id;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setComment($comment)
    {
        $this->comment = preg_replace("/\n/imu", ' ', $comment);

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }
}
