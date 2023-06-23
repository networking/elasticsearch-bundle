<?php

declare(strict_types=1);

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
    public function count(): int
    {
        return sizeof($this->_results);
    }

    /**
     * Returns the current object of the set.
     *
     * @return \Elastica\Result|bool Set object or false if not valid (no more entries)
     */
    public function current(): mixed
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
    public function next(): void
    {
        ++$this->_position;
    }

    /**
     * Returns the position of the current entry.
     *
     * @return int Current position
     */
    public function key(): mixed
    {
        return $this->_position;
    }

    /**
     * Check if an object exists at the current position.
     *
     * @return bool True if object exists
     */
    public function valid(): bool
    {
        return isset($this->_results[$this->key()]);
    }

    /**
     * Resets position to 0, restarts iterator.
     */
    public function rewind(): void
    {
        $this->_position = 0;
    }

    public function getType($name): string
    {
        if(!$this->index instanceof MultiIndex){
            return $name;
        }
        foreach ($this->index->getIndices() as $index){
            if(str_starts_with((string) $name, (string) $index)){
                return str_replace($this->index->getName(), '', (string) $index);
            }
        }

        return $name;
    }
}
