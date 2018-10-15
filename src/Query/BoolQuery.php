<?php
/**
 * Created by PhpStorm.
 * User: yorkie
 * Date: 12.10.18
 * Time: 17:07
 */
namespace Networking\ElasticSearchBundle\Query;

use Elastica\Query\BoolQuery as BaseQuery;

/**
 * Class BoolQuery
 * @package Networking\ElasticSearchBundle\Elastica
 */
class BoolQuery extends BaseQuery implements \Countable {

	/**
	 * @return int
	 */
	public function count(){

		return count($this->toArray());
	}
}
