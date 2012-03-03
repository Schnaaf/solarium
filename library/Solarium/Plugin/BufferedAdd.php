<?php
/**
 * Copyright 2011 Bas de Nooijer. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this listof conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation are
 * those of the authors and should not be interpreted as representing official
 * policies, either expressed or implied, of the copyright holder.
 *
 * @copyright Copyright 2011 Bas de Nooijer <solarium@raspberry.nl>
 * @license http://github.com/basdenooijer/solarium/raw/master/COPYING
 * @link http://www.solarium-project.org/
 *
 * @package Solarium
 * @subpackage Plugin
 */

/**
 * @namespace
 */
namespace Solarium\Plugin;
use Solarium\Client;
use Solarium\Core\Plugin;
use Solarium\Query\Update\Result as UpdateResult;
use Solarium\Query\Update\Query\Query as UpdateQuery;
use Solarium\Query\Select\Result\Document as ReadOnlyDocument;

/**
 * Buffered add plugin
 *
 * If you need to add (or update) a big number of documents to Solr it's much more efficient to do so in batches.
 * This plugin makes this as easy as possible.
 *
 * @package Solarium
 * @subpackage Plugin
 */
class BufferedAdd extends Plugin
{

    /**
     * Default options
     *
     * @var array
     */
    protected $options = array(
        'buffersize' => 100,
    );

    /**
     * Update query instance
     *
     * @var UpdateQuery
     */
    protected $updateQuery;

    /**
     * Buffered documents
     *
     * @var array
     */
    protected $buffer = array();

    /**
     * Plugin init function
     *
     * This is an extension point for plugin implementations.
     * Will be called as soon as $this->client and options have been set.
     *
     * @return void
     */
    protected function initPluginType()
    {
        $this->updateQuery = $this->client->createUpdate();
    }

    /**
     * Set buffer size option
     *
     * @param int $size
     * @return self
     */
    public function setBufferSize($size)
    {
        return $this->setOption('buffersize', $size);
    }

    /**
     * Get buffer size option value
     *
     * @return int
     */
    public function getBufferSize()
    {
        return $this->getOption('buffersize');
    }

    /**
     * Create a document object instance and add it to the buffer
     *
     * @param array $fields
     * @param array $boosts
     * @return self Provides fluent interface
     */
    public function createDocument($fields, $boosts = array())
    {
        $doc = $this->updateQuery->createDocument($fields, $boosts);
        $this->addDocument($doc);

        return $this;
    }

    /**
     * Add a document
     *
     * @param ReadOnlyDocument $document
     * @return self Provides fluent interface
     */
    public function addDocument($document)
    {
        $this->buffer[] = $document;
        if (count($this->buffer) == $this->options['buffersize']) {
            $this->flush();
        }

        return $this;
    }

    /**
     * Add multiple documents
     *
     * @param array
     * @return self Provides fluent interface
     */
    public function addDocuments($documents)
    {
        foreach ($documents as $document) {
            $this->addDocument($document);
        }

        return $this;
    }

    /**
     * Get all documents currently in the buffer
     *
     * Any previously flushed documents will not be included!
     *
     * @return array
     */
    public function getDocuments()
    {
        return $this->buffer;
    }

    /**
     * Clear any buffered documents
     *
     * @return self Provides fluent interface
     */
    public function clear()
    {
        $this->updateQuery = $this->client->createUpdate();
        $this->buffer = array();
        return $this;
    }

    /**
     * Flush any buffered documents to Solr
     *
     * @param boolean $overwrite
     * @param int $commitWithin
     * @return boolean|UpdateResult
     */
    public function flush($overwrite = null, $commitWithin = null)
    {
        if (count($this->buffer) == 0) {
            // nothing to do
            return false;
        }

        $this->client->triggerEvent('BufferedAddFlushStart', array($this->buffer));

        $this->updateQuery->addDocuments($this->buffer, $overwrite, $commitWithin);
        $result = $this->client->update($this->updateQuery);
        $this->clear();

        $this->client->triggerEvent('BufferedAddFlushEnd', array($result));

        return $result;
    }

    /**
     * Commit changes
     *
     * Any remaining documents in the buffer will also be flushed
     *
     * @param boolean $overwrite
     * @param boolean $waitFlush
     * @param boolean $waitSearcher
     * @param boolean $expungeDeletes
     * @return UpdateResult
     */
    public function commit($overwrite = null, $waitFlush = null, $waitSearcher = null, $expungeDeletes = null)
    {
        $this->client->triggerEvent('BufferedAddCommitStart', array($this->buffer));

        $this->updateQuery->addDocuments($this->buffer, $overwrite);
        $this->updateQuery->addCommit($waitFlush, $waitSearcher, $expungeDeletes);
        $result = $this->client->update($this->updateQuery);
        $this->clear();

        $this->client->triggerEvent('BufferedAddCommitEnd', array($result));

        return $result;
    }

}