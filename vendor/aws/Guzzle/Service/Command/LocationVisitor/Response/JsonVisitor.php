<?php

namespace WP_Cloud_Search\Guzzle\Service\Command\LocationVisitor\Response;

use WP_Cloud_Search\Guzzle\Http\Message\Response;
use WP_Cloud_Search\Guzzle\Service\Description\Parameter;
use WP_Cloud_Search\Guzzle\Service\Command\CommandInterface;
/**
 * Location visitor used to marshal JSON response data into a formatted array.
 *
 * Allows top level JSON parameters to be inserted into the result of a command. The top level attributes are grabbed
 * from the response's JSON data using the name value by default. Filters can be applied to parameters as they are
 * traversed. This allows data to be normalized before returning it to users (for example converting timestamps to
 * DateTime objects).
 */
class JsonVisitor extends \WP_Cloud_Search\Guzzle\Service\Command\LocationVisitor\Response\AbstractResponseVisitor
{
    public function before(\WP_Cloud_Search\Guzzle\Service\Command\CommandInterface $command, array &$result)
    {
        // Ensure that the result of the command is always rooted with the parsed JSON data
        $result = $command->getResponse()->json();
    }
    public function visit(\WP_Cloud_Search\Guzzle\Service\Command\CommandInterface $command, \WP_Cloud_Search\Guzzle\Http\Message\Response $response, \WP_Cloud_Search\Guzzle\Service\Description\Parameter $param, &$value, $context = null)
    {
        $name = $param->getName();
        $key = $param->getWireName();
        if (isset($value[$key])) {
            $this->recursiveProcess($param, $value[$key]);
            if ($key != $name) {
                $value[$name] = $value[$key];
                unset($value[$key]);
            }
        }
    }
    /**
     * Recursively process a parameter while applying filters
     *
     * @param Parameter $param API parameter being validated
     * @param mixed     $value Value to validate and process. The value may change during this process.
     */
    protected function recursiveProcess(\WP_Cloud_Search\Guzzle\Service\Description\Parameter $param, &$value)
    {
        if ($value === null) {
            return;
        }
        if (\is_array($value)) {
            $type = $param->getType();
            if ($type == 'array') {
                foreach ($value as &$item) {
                    $this->recursiveProcess($param->getItems(), $item);
                }
            } elseif ($type == 'object' && !isset($value[0])) {
                // On the above line, we ensure that the array is associative and not numerically indexed
                $knownProperties = array();
                if ($properties = $param->getProperties()) {
                    foreach ($properties as $property) {
                        $name = $property->getName();
                        $key = $property->getWireName();
                        $knownProperties[$name] = 1;
                        if (isset($value[$key])) {
                            $this->recursiveProcess($property, $value[$key]);
                            if ($key != $name) {
                                $value[$name] = $value[$key];
                                unset($value[$key]);
                            }
                        }
                    }
                }
                // Remove any unknown and potentially unsafe properties
                if ($param->getAdditionalProperties() === \false) {
                    $value = \array_intersect_key($value, $knownProperties);
                } elseif (($additional = $param->getAdditionalProperties()) !== \true) {
                    // Validate and filter additional properties
                    foreach ($value as &$v) {
                        $this->recursiveProcess($additional, $v);
                    }
                }
            }
        }
        $value = $param->filter($value);
    }
}