<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Paginator;

use Networking\ElasticSearchBundle\Elastica\MultiIndex;
use Elastica\Result;
use Elastica\ResultSet;
use Elastica\Index;
use FOS\ElasticaBundle\Paginator\RawPartialResults;

class RawResults extends RawPartialResults implements \Countable, \Iterator
{
    /**
     * Current position.
     *
     * @var int Current position
     */
    protected $_position = 0;


    /**
     * @var array
     */
    protected $_results = [];

    /**
     * @var Index
     */
    protected $index;


    /**
     * @param ResultSet $resultSet
     */
    public function __construct(ResultSet $resultSet, Index $index)
    {
        $this->index = $index;
        parent::__construct($resultSet);


        $this->_results = array_map(function (Result $result) {

            $source = $result->getSource();
            $source['id'] = $result->getId();
            $source['type'] = $this->getType($result->getIndex());
            $source['highlights'] = $result->getHighlights();
            $source['score'] = $result->getScore();
            return $source;

        }, $this->resultSet->getResults());
    }

    public function toArray(): array
    {
        return $this->_results;
    }


    /**
     * Returns size of current set.
     *
     * @return int Size of set
     */
    public function count()
    {
        return sizeof($this->_results);
    }

    /**
     * Returns the current object of the set.
     *
     * @return \Elastica\Result|bool Set object or false if not valid (no more entries)
     */
    public function current()
    {
        if ($this->valid()) {
            return $this->_results[$this->key()];
        } else {
            return false;
        }
    }

    /**
     * Sets pointer (current) to the next item of the set.
     */
    public function next()
    {
        ++$this->_position;

        return $this->current();
    }

    /**
     * Returns the position of the current entry.
     *
     * @return int Current position
     */
    public function key()
    {
        return $this->_position;
    }

    /**
     * Check if an object exists at the current position.
     *
     * @return bool True if object exists
     */
    public function valid()
    {
        return isset($this->_results[$this->key()]);
    }

    /**
     * Resets position to 0, restarts iterator.
     */
    public function rewind()
    {
        $this->_position = 0;
    }

    public function getType($name)
    {
        if(!$this->index instanceof MultiIndex){
            return $name;
        }
        foreach ($this->index->getIndices() as $index){
            if(strpos($name, $index) === 0){
                return str_replace($this->index->getName(), '', $index);
            }
        }

        return $name;
    }
}