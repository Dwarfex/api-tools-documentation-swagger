<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-documentation-swagger for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-documentation-swagger/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-documentation-swagger/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Documentation\Swagger;

use Laminas\ApiTools\Documentation\Service as BaseService;

class Service extends BaseService
{
    /**
     * @var BaseService
     */
    protected $service;

    /**
     * @param BaseService $service
     * @param string $baseUrl
     */
    public function __construct(BaseService $service, $baseUrl)
    {
        $this->service = $service;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        // localize service object for brevity
        $service = $this->service;

        // routes and parameter mangling ([:foo] will become {foo}
        $routeBasePath = substr($service->route, 0, strpos($service->route, '['));
        $routeWithReplacements = str_replace(array('[', ']', '{/', '{:'), array('{', '}', '/{', '{'), $service->route);

        // find all parameters in Swagger naming format
        preg_match_all('#{([\w\d_-]+)}#', $routeWithReplacements, $parameterMatches);

        // parameters
        $templateParameters = array();
        foreach ($parameterMatches[1] as $paramSegmentName) {
            $templateParameters[$paramSegmentName] = array(
                'paramType' => 'path',
                'name' => $paramSegmentName,
                'description' => 'URL parameter ' . $paramSegmentName,
                'dataType' => 'string',
                'required' => false,
                'minimum' => 0,
                'maximum' => 1
            );
        }

        $postPatchPutBodyParameter = array(
            'name' => 'body',
            'paramType' => 'body',
            'required' => true,
            'type' => $service->api->getName()
        );

        $operationGroups = array();

        // if there is a routeIdentifierName, this is REST service, need to enumerate
        if ($service->routeIdentifierName) {
            $entityOperations = array();
            $collectionOperations = array();

            // find all COLLECTION operations
            foreach ($service->operations as $collectionOperation) {
                $method               = $collectionOperation->getHttpMethod();

                // collection parameters
                $collectionParameters = $templateParameters;
                unset($collectionParameters[$service->routeIdentifierName]);
                $collectionParameters = array_values($collectionParameters);

                if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
                    $collectionParameters[] = $postPatchPutBodyParameter;
                }

                $collectionOperations[] = array(
                    'method'           => $method,
                    'summary'          => $collectionOperation->getDescription(),
                    'notes'            => $collectionOperation->getDescription(),
                    'nickname'         => $method . ' for ' . $service->api->getName(),
                    'type'             => $service->api->getName(),
                    'parameters'       => $collectionParameters,
                    'responseMessages' => $collectionOperation->getResponseStatusCodes(),
                );
            }

            // find all ENTITY operations
            foreach ($service->entityOperations as $entityOperation) {
                $method           = $entityOperation->getHttpMethod();
                $entityParameters = array_values($templateParameters);

                if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
                    $entityParameters[] = $postPatchPutBodyParameter;
                }

                $entityOperations[] = array(
                    'method'           => $method,
                    'summary'          => $entityOperation->getDescription(),
                    'notes'            => $entityOperation->getDescription(),
                    'nickname'         => $method . ' for ' . $service->api->getName(),
                    'type'             => $service->api->getName(),
                    'parameters'       => $entityParameters,
                    'responseMessages' => $entityOperation->getResponseStatusCodes(),
                );
            }

            $operationGroups[] = array(
                'operations' => $collectionOperations,
                'path'       => str_replace('/{' . $service->routeIdentifierName . '}', '', $routeWithReplacements)
            );

            $operationGroups[] = array(
                'operations' => $entityOperations,
                'path' => $routeWithReplacements
            );
        } else {

            // find all other operations
            $operations = array();
            foreach ($service->operations as $operation) {
                $method           = $operation->getHttpMethod();
                $parameters       = array_values($templateParameters);

                if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
                    $parameters[] = $postPatchPutBodyParameter;
                }

                $operations[] = array(
                    'method'           => $method,
                    'summary'          => $operation->getDescription(),
                    'notes'            => $operation->getDescription(),
                    'nickname'         => $method . ' for ' . $service->api->getName(),
                    'type'             => $service->api->getName(),
                    'parameters'       => $parameters,
                    'responseMessages' => $operation->getResponseStatusCodes(),
                );
            }
            $operationGroups[] = array(
                'operations' => $operations,
                'path'       => $routeWithReplacements
            );
        }

        $requiredProperties = $properties = array();
        foreach ($service->fields as $field) {
            $properties[$field->getName()] = array(
                'type' => 'string',
                'description' => $field->getDescription()
            );
            if ($field->isRequired()) {
                $requiredProperties[] = $field->getName();
            }
        }

        return array(
            'apiVersion'     => $service->api->getVersion(),
            'swaggerVersion' => '1.2',
            'basePath'       => $this->baseUrl,
            'resourcePath'   => $routeBasePath,
            'apis'           => $operationGroups,
            'produces'       => $service->requestAcceptTypes,
            'models'         => array(
                $service->api->getName() => array(
                    'id'         => $service->api->getName(),
                    'required'   => $requiredProperties,
                    'properties' => $properties,
                ),
            ),
        );
    }
}
