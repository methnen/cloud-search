<?php

namespace WP_Cloud_Search\Aws\Common\Command;

use WP_Cloud_Search\Guzzle\Http\Message\RequestInterface;
use WP_Cloud_Search\Guzzle\Service\Description\Parameter;
use WP_Cloud_Search\Guzzle\Service\Command\CommandInterface;
use WP_Cloud_Search\Guzzle\Service\Command\LocationVisitor\Request\AbstractRequestVisitor;
/**
 * Location visitor used to serialize AWS query parameters (e.g. EC2, SES, SNS, SQS, etc) as POST fields
 */
class AwsQueryVisitor extends \WP_Cloud_Search\Guzzle\Service\Command\LocationVisitor\Request\AbstractRequestVisitor
{
    private $fqname;
    public function visit(\WP_Cloud_Search\Guzzle\Service\Command\CommandInterface $command, \WP_Cloud_Search\Guzzle\Http\Message\RequestInterface $request, \WP_Cloud_Search\Guzzle\Service\Description\Parameter $param, $value)
    {
        $this->fqname = $command->getName();
        $query = array();
        $this->customResolver($value, $param, $query, $param->getWireName());
        $request->addPostFields($query);
    }
    /**
     * Map nested parameters into the location_key based parameters
     *
     * @param array     $value  Value to map
     * @param Parameter $param  Parameter that holds information about the current key
     * @param array     $query  Built up query string values
     * @param string    $prefix String to prepend to sub query values
     */
    protected function customResolver($value, \WP_Cloud_Search\Guzzle\Service\Description\Parameter $param, array &$query, $prefix = '')
    {
        switch ($param->getType()) {
            case 'object':
                $this->resolveObject($param, $value, $prefix, $query);
                break;
            case 'array':
                $this->resolveArray($param, $value, $prefix, $query);
                break;
            default:
                $query[$prefix] = $param->filter($value);
        }
    }
    /**
     * Custom handling for objects
     *
     * @param Parameter $param  Parameter for the object
     * @param array     $value  Value that is set for this parameter
     * @param string    $prefix Prefix for the resulting key
     * @param array     $query  Query string array passed by reference
     */
    protected function resolveObject(\WP_Cloud_Search\Guzzle\Service\Description\Parameter $param, array $value, $prefix, array &$query)
    {
        // Maps are implemented using additional properties
        $hasAdditionalProperties = $param->getAdditionalProperties() instanceof \WP_Cloud_Search\Guzzle\Service\Description\Parameter;
        $additionalPropertyCount = 0;
        foreach ($value as $name => $v) {
            if ($subParam = $param->getProperty($name)) {
                // if the parameter was found by name as a regular property
                $key = $prefix . '.' . $subParam->getWireName();
                $this->customResolver($v, $subParam, $query, $key);
            } elseif ($hasAdditionalProperties) {
                // Handle map cases like &Attribute.1.Name=<name>&Attribute.1.Value=<value>
                $additionalPropertyCount++;
                $data = $param->getData();
                $keyName = isset($data['keyName']) ? $data['keyName'] : 'key';
                $valueName = isset($data['valueName']) ? $data['valueName'] : 'value';
                $query["{$prefix}.{$additionalPropertyCount}.{$keyName}"] = $name;
                $newPrefix = "{$prefix}.{$additionalPropertyCount}.{$valueName}";
                if (\is_array($v)) {
                    $this->customResolver($v, $param->getAdditionalProperties(), $query, $newPrefix);
                } else {
                    $query[$newPrefix] = $param->filter($v);
                }
            }
        }
    }
    /**
     * Custom handling for arrays
     *
     * @param Parameter $param  Parameter for the object
     * @param array     $value  Value that is set for this parameter
     * @param string    $prefix Prefix for the resulting key
     * @param array     $query  Query string array passed by reference
     */
    protected function resolveArray(\WP_Cloud_Search\Guzzle\Service\Description\Parameter $param, array $value, $prefix, array &$query)
    {
        static $serializeEmpty = array('SetLoadBalancerPoliciesForBackendServer' => 1, 'SetLoadBalancerPoliciesOfListener' => 1, 'UpdateStack' => 1);
        // For BC, serialize empty lists for specific operations
        if (!$value) {
            if (isset($serializeEmpty[$this->fqname])) {
                if (\substr($prefix, -7) === '.member') {
                    $prefix = \substr($prefix, 0, -7);
                }
                $query[$prefix] = '';
            }
            return;
        }
        $offset = $param->getData('offset') ?: 1;
        foreach ($value as $index => $v) {
            $index += $offset;
            if (\is_array($v) && ($items = $param->getItems())) {
                $this->customResolver($v, $items, $query, $prefix . '.' . $index);
            } else {
                $query[$prefix . '.' . $index] = $param->filter($v);
            }
        }
    }
}
