<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Collection;

use AppendIterator;
use ArrayIterator;
use Cake\Collection\Iterator\BufferedIterator;
use Cake\Collection\Iterator\ExtractIterator;
use Cake\Collection\Iterator\FilterIterator;
use Cake\Collection\Iterator\InsertIterator;
use Cake\Collection\Iterator\MapReduce;
use Cake\Collection\Iterator\NestIterator;
use Cake\Collection\Iterator\ReplaceIterator;
use Cake\Collection\Iterator\SortIterator;
use Cake\Collection\Iterator\StoppableIterator;
use Cake\Collection\Iterator\TreeIterator;
use Cake\Collection\Iterator\UnfoldIterator;
use Cake\Collection\Iterator\ZipIterator;
use Countable;
use LimitIterator;
use LogicException;
use RecursiveIteratorIterator;
use Traversable;

/**
 * Offers a handful of method to manipulate iterators
 */
trait CollectionTrait
{

    use ExtractTrait;

    /**
     * {@inheritDoc}
     */
    public function each(callable $c)
    {
        foreach ($this->optimizeUnwrap() as $k => $v) {
            $c($v, $k);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\FilterIterator
     */
    public function filter(callable $c = null)
    {
        if ($c === null) {
            $c = function ($v) {
                return (bool)$v;
            };
        }

        return new FilterIterator($this->unwrap(), $c);
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\FilterIterator
     */
    public function reject(callable $c)
    {
        return new FilterIterator($this->unwrap(), function ($key, $value, $items) use ($c) {
            return !$c($key, $value, $items);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function every(callable $c)
    {
        foreach ($this->optimizeUnwrap() as $key => $value) {
            if (!$c($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function some(callable $c)
    {
        foreach ($this->optimizeUnwrap() as $key => $value) {
            if ($c($value, $key) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function contains($value)
    {
        foreach ($this->optimizeUnwrap() as $v) {
            if ($value === $v) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\ReplaceIterator
     */
    public function map(callable $c)
    {
        return new ReplaceIterator($this->unwrap(), $c);
    }

    /**
     * {@inheritDoc}
     */
    public function reduce(callable $c, $zero = null)
    {
        $isFirst = false;
        if (func_num_args() < 2) {
            $isFirst = true;
        }

        $result = $zero;
        foreach ($this->optimizeUnwrap() as $k => $value) {
            if ($isFirst) {
                $result = $value;
                $isFirst = false;
                continue;
            }
            $result = $c($result, $value, $k);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function extract($matcher)
    {
        $extractor = new ExtractIterator($this->unwrap(), $matcher);
        if (is_string($matcher) && strpos($matcher, '{*}') !== false) {
            $extractor = $extractor
                ->filter(function ($data) {
                    return $data !== null && ($data instanceof Traversable || is_array($data));
                })
                ->unfold();
        }

        return $extractor;
    }

    /**
     * {@inheritDoc}
     */
    public function max($callback, $type = SORT_NUMERIC)
    {
        return (new SortIterator($this->unwrap(), $callback, SORT_DESC, $type))->first();
    }

    /**
     * {@inheritDoc}
     */
    public function min($callback, $type = SORT_NUMERIC)
    {
        return (new SortIterator($this->unwrap(), $callback, SORT_ASC, $type))->first();
    }

    /**
     * {@inheritDoc}
     */
    public function avg($matcher = null)
    {
        $result = $this;
        if ($matcher != null) {
            $result = $result->extract($matcher);
        }
        $result = $result
            ->reduce(function ($acc, $current) {
                list($count, $sum) = $acc;

                return [$count + 1, $sum + $current];
            }, [0, 0]);

        if ($result[0] === 0) {
            return null;
        }

        return $result[1] / $result[0];
    }

    /**
     * {@inheritDoc}
     */
    public function median($matcher = null)
    {
        $elements = $this;
        if ($matcher != null) {
            $elements = $elements->extract($matcher);
        }
        $values = $elements->toList();
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        $middle = (int)($count / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * {@inheritDoc}
     */
    public function sortBy($callback, $dir = SORT_DESC, $type = SORT_NUMERIC)
    {
        return new SortIterator($this->unwrap(), $callback, $dir, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function groupBy($callback)
    {
        $callback = $this->_propertyExtractor($callback);
        $group = [];
        foreach ($this->optimizeUnwrap() as $value) {
            $group[$callback($value)][] = $value;
        }

        return new Collection($group);
    }

    /**
     * {@inheritDoc}
     */
    public function indexBy($callback)
    {
        $callback = $this->_propertyExtractor($callback);
        $group = [];
        foreach ($this->optimizeUnwrap() as $value) {
            $group[$callback($value)] = $value;
        }

        return new Collection($group);
    }

    /**
     * {@inheritDoc}
     */
    public function countBy($callback)
    {
        $callback = $this->_propertyExtractor($callback);

        $mapper = function ($value, $key, $mr) use ($callback) {
            $mr->emitIntermediate($value, $callback($value));
        };

        $reducer = function ($values, $key, $mr) {
            $mr->emit(count($values), $key);
        };

        return new Collection(new MapReduce($this->unwrap(), $mapper, $reducer));
    }

    /**
     * {@inheritDoc}
     */
    public function sumOf($matcher = null)
    {
        if ($matcher === null) {
            return array_sum($this->toList());
        }

        $callback = $this->_propertyExtractor($matcher);
        $sum = 0;
        foreach ($this->optimizeUnwrap() as $k => $v) {
            $sum += $callback($v, $k);
        }

        return $sum;
    }

    /**
     * {@inheritDoc}
     */
    public function shuffle()
    {
        $elements = $this->toArray();
        shuffle($elements);

        return new Collection($elements);
    }

    /**
     * {@inheritDoc}
     */
    public function sample($size = 10)
    {
        return new Collection(new LimitIterator($this->shuffle(), 0, $size));
    }

    /**
     * {@inheritDoc}
     */
    public function take($size = 1, $from = 0)
    {
        return new Collection(new LimitIterator($this, $from, $size));
    }

    /**
     * {@inheritDoc}
     */
    public function skip($howMany)
    {
        return new Collection(new LimitIterator($this, $howMany));
    }

    /**
     * {@inheritDoc}
     */
    public function match(array $conditions)
    {
        return $this->filter($this->_createMatcherFilter($conditions));
    }

    /**
     * {@inheritDoc}
     */
    public function firstMatch(array $conditions)
    {
        return $this->match($conditions)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        $iterator = new LimitIterator($this, 0, 1);
        foreach ($iterator as $result) {
            return $result;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        $iterator = $this->optimizeUnwrap();
        if (is_array($iterator)) {
            return array_pop($iterator);
        }

        if ($iterator instanceof Countable) {
            $count = count($iterator);
            if ($count === 0) {
                return null;
            }
            $iterator = new LimitIterator($iterator, $count - 1, 1);
        }

        $result = null;
        foreach ($iterator as $result) {
            // No-op
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function append($items)
    {
        $list = new AppendIterator();
        $list->append($this->unwrap());
        $list->append((new Collection($items))->unwrap());

        return new Collection($list);
    }

    /**
     * {@inheritDoc}
     */
    public function combine($keyPath, $valuePath, $groupPath = null)
    {
        $options = [
            'keyPath' => $this->_propertyExtractor($keyPath),
            'valuePath' => $this->_propertyExtractor($valuePath),
            'groupPath' => $groupPath ? $this->_propertyExtractor($groupPath) : null
        ];

        $mapper = function ($value, $key, $mapReduce) use ($options) {
            $rowKey = $options['keyPath'];
            $rowVal = $options['valuePath'];

            if (!$options['groupPath']) {
                $mapReduce->emit($rowVal($value, $key), $rowKey($value, $key));

                return null;
            }

            $key = $options['groupPath']($value, $key);
            $mapReduce->emitIntermediate(
                [$rowKey($value, $key) => $rowVal($value, $key)],
                $key
            );
        };

        $reducer = function ($values, $key, $mapReduce) {
            $result = [];
            foreach ($values as $value) {
                $result += $value;
            }
            $mapReduce->emit($result, $key);
        };

        return new Collection(new MapReduce($this->unwrap(), $mapper, $reducer));
    }

    /**
     * {@inheritDoc}
     */
    public function nest($idPath, $parentPath, $nestingKey = 'children')
    {
        $parents = [];
        $idPath = $this->_propertyExtractor($idPath);
        $parentPath = $this->_propertyExtractor($parentPath);
        $isObject = true;

        $mapper = function ($row, $key, $mapReduce) use (&$parents, $idPath, $parentPath, $nestingKey) {
            $row[$nestingKey] = [];
            $id = $idPath($row, $key);
            $parentId = $parentPath($row, $key);
            $parents[$id] =& $row;
            $mapReduce->emitIntermediate($id, $parentId);
        };

        $reducer = function ($values, $key, $mapReduce) use (&$parents, &$isObject, $nestingKey) {
            static $foundOutType = false;
            if (!$foundOutType) {
                $isObject = is_object(current($parents));
                $foundOutType = true;
            }
            if (empty($key) || !isset($parents[$key])) {
                foreach ($values as $id) {
                    $parents[$id] = $isObject ? $parents[$id] : new ArrayIterator($parents[$id], 1);
                    $mapReduce->emit($parents[$id]);
                }

                return null;
            }

            $children = [];
            foreach ($values as $id) {
                $children[] =& $parents[$id];
            }
            $parents[$key][$nestingKey] = $children;
        };

        return (new Collection(new MapReduce($this->unwrap(), $mapper, $reducer)))
            ->map(function ($value) use (&$isObject) {
                return $isObject ? $value : $value->getArrayCopy();
            });
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\InsertIterator
     */
    public function insert($path, $values)
    {
        return new InsertIterator($this->unwrap(), $path, $values);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray($preserveKeys = true)
    {
        $iterator = $this->unwrap();
        if ($iterator instanceof ArrayIterator) {
            $items = $iterator->getArrayCopy();

            return $preserveKeys ? $items : array_values($items);
        }
        // RecursiveIteratorIterator can return duplicate key values causing
        // data loss when converted into an array
        if ($preserveKeys && get_class($iterator) === 'RecursiveIteratorIterator') {
            $preserveKeys = false;
        }

        return iterator_to_array($this, $preserveKeys);
    }

    /**
     * {@inheritDoc}
     */
    public function toList()
    {
        return $this->toArray(false);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function compile($preserveKeys = true)
    {
        return new Collection($this->toArray($preserveKeys));
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\BufferedIterator
     */
    public function buffered()
    {
        return new BufferedIterator($this->unwrap());
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\TreeIterator
     */
    public function listNested($dir = 'desc', $nestingKey = 'children')
    {
        $dir = strtolower($dir);
        $modes = [
            'desc' => TreeIterator::SELF_FIRST,
            'asc' => TreeIterator::CHILD_FIRST,
            'leaves' => TreeIterator::LEAVES_ONLY
        ];

        return new TreeIterator(
            new NestIterator($this, $nestingKey),
            isset($modes[$dir]) ? $modes[$dir] : $dir
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\Iterator\StoppableIterator
     */
    public function stopWhen($condition)
    {
        if (!is_callable($condition)) {
            $condition = $this->_createMatcherFilter($condition);
        }

        return new StoppableIterator($this->unwrap(), $condition);
    }

    /**
     * {@inheritDoc}
     */
    public function unfold(callable $transformer = null)
    {
        if ($transformer === null) {
            $transformer = function ($item) {
                return $item;
            };
        }

        return new Collection(
            new RecursiveIteratorIterator(
                new UnfoldIterator($this->unwrap(), $transformer),
                RecursiveIteratorIterator::LEAVES_ONLY
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function through(callable $handler)
    {
        $result = $handler($this);

        return $result instanceof CollectionInterface ? $result : new Collection($result);
    }

    /**
     * {@inheritDoc}
     */
    public function zip($items)
    {
        return new ZipIterator(array_merge([$this->unwrap()], func_get_args()));
    }

    /**
     * {@inheritDoc}
     */
    public function zipWith($items, $callable)
    {
        if (func_num_args() > 2) {
            $items = func_get_args();
            $callable = array_pop($items);
        } else {
            $items = [$items];
        }

        return new ZipIterator(array_merge([$this->unwrap()], $items), $callable);
    }

    /**
     * {@inheritDoc}
     */
    public function chunk($chunkSize)
    {
        return $this->map(function ($v, $k, $iterator) use ($chunkSize) {
            $values = [$v];
            for ($i = 1; $i < $chunkSize; $i++) {
                $iterator->next();
                if (!$iterator->valid()) {
                    break;
                }
                $values[] = $iterator->current();
            }

            return $values;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function chunkWithKeys($chunkSize, $preserveKeys = true)
    {
        return $this->map(function ($v, $k, $iterator) use ($chunkSize, $preserveKeys) {
            $key = 0;
            if ($preserveKeys) {
                $key = $k;
            }
            $values = [$key => $v];
            for ($i = 1; $i < $chunkSize; $i++) {
                $iterator->next();
                if (!$iterator->valid()) {
                    break;
                }
                if ($preserveKeys) {
                    $values[$iterator->key()] = $iterator->current();
                } else {
                    $values[] = $iterator->current();
                }
            }

            return $values;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        foreach ($this as $el) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function unwrap()
    {
        $iterator = $this;
        while (get_class($iterator) === 'Cake\Collection\Collection') {
            $iterator = $iterator->getInnerIterator();
        }

        if ($iterator !== $this && $iterator instanceof CollectionInterface) {
            $iterator = $iterator->unwrap();
        }

        return $iterator;
    }

    /**
     * Backwards compatible wrapper for unwrap()
     *
     * @return \Traversable
     * @deprecated
     */
    // @codingStandardsIgnoreLine
    public function _unwrap()
    {
        return $this->unwrap();
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\CollectionInterface
     */
    public function cartesianProduct(callable $operation = null, callable $filter = null)
    {
        if ($this->isEmpty()) {
            return new Collection([]);
        }

        $collectionArrays = [];
        $collectionArraysKeys = [];
        $collectionArraysCounts = [];

        foreach ($this->toList() as $value) {
            $valueCount = count($value);
            if ($valueCount !== count($value, COUNT_RECURSIVE)) {
                throw new LogicException('Cannot find the cartesian product of a multidimensional array');
            }

            $collectionArraysKeys[] = array_keys($value);
            $collectionArraysCounts[] = $valueCount;
            $collectionArrays[] = $value;
        }

        $result = [];
        $lastIndex = count($collectionArrays) - 1;
        // holds the indexes of the arrays that generate the current combination
        $currentIndexes = array_fill(0, $lastIndex + 1, 0);

        $changeIndex = $lastIndex;

        while (!($changeIndex === 0 && $currentIndexes[0] === $collectionArraysCounts[0])) {
            $currentCombination = array_map(function ($value, $keys, $index) {
                return $value[$keys[$index]];
            }, $collectionArrays, $collectionArraysKeys, $currentIndexes);

            if ($filter === null || $filter($currentCombination)) {
                $result[] = ($operation === null) ? $currentCombination : $operation($currentCombination);
            }

            $currentIndexes[$lastIndex]++;

            for ($changeIndex = $lastIndex; $currentIndexes[$changeIndex] === $collectionArraysCounts[$changeIndex] && $changeIndex > 0; $changeIndex--) {
                $currentIndexes[$changeIndex] = 0;
                $currentIndexes[$changeIndex - 1]++;
            }
        }

        return new Collection($result);
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Collection\CollectionInterface
     */
    public function transpose()
    {
        $arrayValue = $this->toList();
        $length = count(current($arrayValue));
        $result = [];
        foreach ($arrayValue as $column => $row) {
            if (count($row) != $length) {
                throw new LogicException('Child arrays do not have even length');
            }
        }

        for ($column = 0; $column < $length; $column++) {
            $result[] = array_column($arrayValue, $column);
        }

        return new Collection($result);
    }

    /**
     * Unwraps this iterator and returns the simplest
     * traversable that can be used for getting the data out
     *
     * @return \Traversable|array
     */
    protected function optimizeUnwrap()
    {
        $iterator = $this->unwrap();

        if (get_class($iterator) === ArrayIterator::class) {
            $iterator = $iterator->getArrayCopy();
        }

        return $iterator;
    }
}
