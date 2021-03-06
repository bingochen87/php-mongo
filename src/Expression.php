<?php

/**
 * This file is part of the PHPMongo package.
 *
 * (c) Dmytro Sokil <dmytro.sokil@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sokil\Mongo;

use Sokil\Mongo\Structure\Arrayable;
use GeoJson\Geometry\Geometry;
use GeoJson\Geometry\Point;

/**
 * This class represents all expressions used to query document from collection
 *
 * @link http://docs.mongodb.org/manual/reference/operator/query/
 * @author Dmytro Sokil <dmytro.sokil@gmail.com>
 */
class Expression implements Arrayable
{
    protected $_expression = array();

    /**
     * Create new instance of expression
     * @return \Sokil\Mongo\Expression
     */
    public function expression()
    {
        return new self;
    }

    public function where($field, $value)
    {
        if(!isset($this->_expression[$field]) || !is_array($value) || !is_array($this->_expression[$field])) {
            $this->_expression[$field] = $value;
        }
        else {
            $this->_expression[$field] = array_merge_recursive($this->_expression[$field], $value);
        }

        return $this;
    }

    public function whereEmpty($field)
    {
        return $this->where('$or', array(
            array($field => null),
            array($field => ''),
            array($field => array()),
            array($field => array('$exists' => false))
        ));
    }

    public function whereNotEmpty($field)
    {
        return $this->where('$nor', array(
            array($field => null),
            array($field => ''),
            array($field => array()),
            array($field => array('$exists' => false))
        ));
    }

    public function whereGreater($field, $value)
    {
        return $this->where($field, array('$gt' => $value));
    }

    public function whereGreaterOrEqual($field, $value)
    {
        return $this->where($field, array('$gte' => $value));
    }

    public function whereLess($field, $value)
    {
        return $this->where($field, array('$lt' => $value));
    }

    public function whereLessOrEqual($field, $value)
    {
        return $this->where($field, array('$lte' => $value));
    }

    public function whereNotEqual($field, $value)
    {
        return $this->where($field, array('$ne' => $value));
    }

    /**
     * Selects the documents where the value of a
     * field equals any value in the specified array.
     *
     * @param string $field
     * @param array $values
     * @return \Sokil\Mongo\Expression
     */
    public function whereIn($field, array $values)
    {
        return $this->where($field, array('$in' => $values));
    }

    public function whereNotIn($field, array $values)
    {
        return $this->where($field, array('$nin' => $values));
    }

    public function whereExists($field)
    {
        return $this->where($field, array('$exists' => true));
    }

    public function whereNotExists($field)
    {
        return $this->where($field, array('$exists' => false));
    }

    public function whereHasType($field, $type)
    {
        return $this->where($field, array('$type' => (int) $type));
    }

    public function whereDouble($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_DOUBLE);
    }

    public function whereString($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_STRING);
    }

    public function whereObject($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_OBJECT);
    }

    public function whereBoolean($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_BOOLEAN);
    }

    public function whereArray($field)
    {
        return $this->whereJsCondition('Array.isArray(this.' . $field . ')');
    }

    public function whereArrayOfArrays($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_ARRAY);
    }

    public function whereObjectId($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_OBJECT_ID);
    }

    public function whereDate($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_DATE);
    }

    public function whereNull($field)
    {
        return $this->whereHasType($field, Document::FIELD_TYPE_NULL);
    }

    public function whereJsCondition($condition)
    {
        return $this->where('$where', $condition);
    }

    public function whereLike($field, $regex, $caseInsensitive = true)
    {
        // regex
        $expression = array(
            '$regex'    => $regex,
        );

        // options
        $options = '';

        if($caseInsensitive) {
            $options .= 'i';
        }

        $expression['$options'] = $options;

        // query
        return $this->where($field, $expression);
    }

    /**
     * Find documents where the value of a field is an array
     * that contains all the specified elements.
     * This is equivalent of logical AND.
     *
     * @link http://docs.mongodb.org/manual/reference/operator/query/all/
     *
     * @param string $field point-delimited field name
     * @param array $values
     * @return \Sokil\Mongo\Expression
     */
    public function whereAll($field, array $values)
    {
        return $this->where($field, array('$all' => $values));
    }

    /**
     * Find documents where the value of a field is an array
     * that contains none of the specified elements.
     * This is equivalent of logical AND.
     *
     * @link http://docs.mongodb.org/manual/reference/operator/query/all/
     *
     * @param string $field point-delimited field name
     * @param array $values
     * @return \Sokil\Mongo\Expression
     */
    public function whereNoneOf($field, array $values)
    {
        return $this->where($field, array(
            '$not' => array(
                '$all' => $values
            ),
        ));
    }

    /**
     * Find documents where the value of a field is an array
     * that contains any of the specified elements.
     * This is equivalent of logical AND.
     *
     * @param string $field point-delimited field name
     * @param array $values
     * @return \Sokil\Mongo\Expression
     */
    public function whereAny($field, array $values)
    {
        return $this->whereIn($field, $values);
    }

    /**
     * Matches documents in a collection that contain an array field with at
     * least one element that matches all the specified query criteria.
     *
     * @param string $field point-delimited field name
     * @param \Sokil\Mongo\Expression|callable|array $expression
     * @return \Sokil\Mongo\Expression
     */
    public function whereElemMatch($field, $expression)
    {
        if(is_callable($expression)) {
            $expression = call_user_func($expression, $this->expression());
        }

        if($expression instanceof Expression) {
            $expression = $expression->toArray();
        } elseif(!is_array($expression)) {
            throw new Exception('Wrong expression passed');
        }

        return $this->where($field, array('$elemMatch' => $expression));
    }

    /**
     * Matches documents in a collection that contain an array field with elements
     * that do not matches all the specified query criteria.
     *
     * @param type $field
     * @param \Sokil\Mongo\Expression|callable|array $expression
     * @return \Sokil\Mongo\Expression
     */
    public function whereElemNotMatch($field, $expression)
    {
        return $this->whereNot($this->expression()->whereElemMatch($field, $expression));
    }

    /**
     * Selects documents if the array field is a specified size.
     *
     * @param string $field
     * @param integer $length
     * @return \Sokil\Mongo\Expression
     */
    public function whereArraySize($field, $length)
    {
        return $this->where($field, array('$size' => (int) $length));
    }

    /**
     * Selects the documents that satisfy at least one of the expressions
     *
     * @param array|\Sokil\Mongo\Expression $expressions Array of Expression instances or comma delimited expression list
     * @return \Sokil\Mongo\Expression
     */
    public function whereOr($expressions = null /**, ...**/)
    {
        if($expressions instanceof Expression) {
            $expressions = func_get_args();
        }

        return $this->where('$or', array_map(function(Expression $expression) {
            return $expression->toArray();
        }, $expressions));
    }

    /**
     * Select the documents that satisfy all the expressions in the array
     *
     * @param array|\Sokil\Mongo\Expression $expressions Array of Expression instances or comma delimited expression list
     * @return \Sokil\Mongo\Expression
     */
    public function whereAnd($expressions = null /**, ...**/)
    {
        if($expressions instanceof Expression) {
            $expressions = func_get_args();
        }

        return $this->where('$and', array_map(function(Expression $expression) {
            return $expression->toArray();
        }, $expressions));
    }

    /**
     * Selects the documents that fail all the query expressions in the array
     *
     * @param array|\Sokil\Mongo\Expression $expressions Array of Expression instances or comma delimited expression list
     * @return \Sokil\Mongo\Expression
     */
    public function whereNor($expressions = null /**, ...**/)
    {
        if($expressions instanceof Expression) {
            $expressions = func_get_args();
        }

        return $this->where('$nor', array_map(function(Expression $expression) {
            return $expression->toArray();
        }, $expressions));
    }

    public function whereNot(Expression $expression)
    {
        foreach($expression->toArray() as $field => $value) {
            // $not acceptable only for operators-expressions
            if(is_array($value) && is_string(key($value))) {
                $this->where($field, array('$not' => $value));
            }
            // for single values use $ne
            else {
                $this->whereNotEqual($field, $value);
            }
        }

        return $this;
    }

    /**
     * Select documents where the value of a field divided by a divisor has the specified remainder (i.e. perform a modulo operation to select documents)
     *
     * @param string $field
     * @param int $divisor
     * @param int $remainder
     */
    public function whereMod($field, $divisor, $remainder)
    {
        $this->where($field, array(
            '$mod' => array((int) $divisor, (int) $remainder),
        ));

        return $this;
    }

    /**
     * Find document near points in flat surface
     *
     * @param string $field
     * @param float $longitude
     * @param float $latitude
     * @param int|array $distance distance from point in meters. Array distance
     *  allowed only in MongoDB 2.6
     * @return \Sokil\Mongo\Expression
     */
    public function nearPoint($field, $longitude, $latitude, $distance)
    {
        $point = new \GeoJson\Geometry\Point(array(
            (float) $longitude,
            (float) $latitude
        ));

        $near = array(
            '$geometry' => $point->jsonSerialize(),
        );

        if(is_array($distance)) {
            if(!empty($distance[0])) {
                $near['$minDistance'] = (int) $distance[0];
            }
            if(!empty($distance[1])) {
                $near['$maxDistance'] = (int) $distance[1];
            }
        } else {
            $near['$maxDistance'] = (int) $distance;
        }

        $this->where($field, array('$near' => $near));

        return $this;
    }

    /**
     * Find document near points in spherical surface
     *
     * @param string $field
     * @param float $longitude
     * @param float $latitude
     * @param int|array $distance distance from point in meters. Array distance
     *  allowed only in MongoDB 2.6
     * @return \Sokil\Mongo\Expression
     */
    public function nearPointSpherical($field, $longitude, $latitude, $distance)
    {
        $point = new Point(array(
            (float) $longitude,
            (float) $latitude
        ));

        $near = array(
            '$geometry' => $point->jsonSerialize(),
        );

        if(is_array($distance)) {
            if(!empty($distance[0])) {
                $near['$minDistance'] = (int) $distance[0];
            }
            if(!empty($distance[1])) {
                $near['$maxDistance'] = (int) $distance[1];
            }
        } else {
            $near['$maxDistance'] = (int) $distance;
        }

        $this->where($field, array('$nearSphere' => $near));

        return $this;
    }

    /**
     * Selects documents whose geospatial data intersects with a specified
     * GeoJSON object; i.e. where the intersection of the data and the
     * specified object is non-empty. This includes cases where the data
     * and the specified object share an edge. Uses spherical geometry.
     *
     * @link http://docs.mongodb.org/manual/reference/operator/query/geoIntersects/
     *
     * @param string $field
     * @param Geometry $geometry
     * @return \Sokil\Mongo\Expression
     */
    public function intersects($field, Geometry $geometry)
    {
        $this->where($field, array(
            '$geoIntersects' => array(
                '$geometry' => $geometry->jsonSerialize(),
            ),
        ));

        return $this;
    }

    /**
     * Selects documents with geospatial data that exists entirely within a specified shape.
     *
     * @link http://docs.mongodb.org/manual/reference/operator/query/geoWithin/
     * @param string $field
     * @param Geometry $geometry
     * @return \Sokil\Mongo\Expression
     */
    public function within($field, Geometry $geometry)
    {
        $this->where($field, array(
            '$geoWithin' => array(
                '$geometry' => $geometry->jsonSerialize(),
            ),
        ));

        return $this;
    }

    /**
     * Select documents with geospatial data within circle defined
     * by center point and radius in flat surface
     *
     * @param string $field
     * @param float $longitude
     * @param float $latitude
     * @param float $radius
     * @return \Sokil\Mongo\Expression
     */
    public function withinCircle($field, $longitude, $latitude, $radius)
    {
        $this->where($field, array(
            '$geoWithin' => array(
                '$center' => array(
                    array($longitude, $latitude),
                    $radius,
                ),
            ),
        ));

        return $this;
    }

    /**
     * Select documents with geospatial data within circle defined
     * by center point and radius in spherical surface
     *
     * To calculate distance in radians
     * @see http://docs.mongodb.org/manual/tutorial/calculate-distances-using-spherical-geometry-with-2d-geospatial-indexes/
     *
     * @param string $field
     * @param float $longitude
     * @param float $latitude
     * @param float $radiusInRadians in radians.
     * @return \Sokil\Mongo\Expression
     */
    public function withinCircleSpherical($field, $longitude, $latitude, $radiusInRadians)
    {
        $this->where($field, array(
            '$geoWithin' => array(
                '$centerSphere' => array(
                    array($longitude, $latitude),
                    $radiusInRadians,
                ),
            ),
        ));

        return $this;
    }

    /**
     * Return documents that are within the bounds of the rectangle, according
     * to their point-based location data.
     *
     * Based on grid coordinates and does not query for GeoJSON shapes.
     *
     * Use planar geometry, so 2d index may be used but not required
     *
     * @param string $field
     * @param array $bottomLeftCoordinate Bottom left coordinate of box
     * @param array $upperRightCoordinate Upper right coordinate of box
     * @return \Sokil\Mongo\Expression
     */
    public function withinBox($field, array $bottomLeftCoordinate, array $upperRightCoordinate)
    {
        $this->where($field, array(
            '$geoWithin' => array(
                '$box' => array(
                    $bottomLeftCoordinate,
                    $upperRightCoordinate,
                ),
            ),
        ));

        return $this;
    }

    /**
     * Return documents that are within the polygon, according
     * to their point-based location data.
     *
     * Based on grid coordinates and does not query for GeoJSON shapes.
     *
     * Use planar geometry, so 2d index may be used but not required
     *
     * @param string $field
     * @param array $points array of coordinates
     * @return \Sokil\Mongo\Expression
     */
    public function withinPolygon($field, array $points)
    {
        $this->where($field, array(
            '$geoWithin' => array(
                '$polygon' => $points,
            ),
        ));

        return $this;
    }

    public function toArray()
    {
        return $this->_expression;
    }

    public function merge(Expression $expression)
    {
        $this->_expression = array_merge_recursive($this->_expression, $expression->toArray());
        return $this;
    }
}
