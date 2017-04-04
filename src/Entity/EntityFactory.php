<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 17/10/16
 * Time: 16:45
 */

namespace Eukles\Entity;

use Eukles\Action\ActionInterface;
use Propel\Runtime\Exception\EntityNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EntityFactory implements EntityFactoryInterface
{

    /**
     * Create a new instance of activeRecord and add it to Request attributes
     *
     * @param EntityRequestInterface       $entityRequest
     * @param ServerRequestInterface       $request
     * @param ResponseInterface            $response
     * @param callable                     $next
     * @param                              $nameOfParameterToAdd
     *
     * @return ResponseInterface
     */
    public function create(
        EntityRequestInterface $entityRequest,
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next,
        $nameOfParameterToAdd = null
    ) {
        # make a new empty record
        $obj = $entityRequest->instantiateActiveRecord();

        # Execute beforeCreate hook, which can alter record
        $entityRequest->beforeCreate($obj);

        # Then, alter object with allowed properties
        /** @noinspection PhpUndefinedMethodInspection */
        $obj->fromArray(
            $entityRequest->getAllowedDataFromRequest(
                $request->getParams(),
                $request->getMethod()
            ));

        # Execute afterCreate hook, which can alter record
        $entityRequest->afterCreate($obj);

        # Finally, build name of parameter to inject in action method, will be used later
        if ($nameOfParameterToAdd === null) {
            $nameOfParameterToAdd = $entityRequest->buildNameOfParameterToAdd();
        }
        /** @var $request ServerRequestInterface */
        $newRequest = $request->withAttribute($nameOfParameterToAdd, $obj);
        $response   = $next($newRequest, $response);

        return $response;
    }

    /**
     * Fetch an existing instance of activeRecord and add it to Request attributes
     *
     * @param EntityRequestInterface       $entityRequest
     * @param ServerRequestInterface       $request
     * @param ResponseInterface            $response
     * @param callable                     $next
     * @param                              $nameOfParameterToAdd
     *
     * @return ResponseInterface
     * @throws EntityNotFoundException
     */
    public function fetch(
        EntityRequestInterface $entityRequest,
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next,
        $nameOfParameterToAdd = null
    ) {

        # First, we try to determine PK in  request path (most common case)
        if (isset($request->getAttribute('routeInfo')[2]['id'])) {
            $entityRequest->setPrimaryKey($request->getAttribute('routeInfo')[2]['id']);
        }

        # Next, we create the query (ModelCriteria), based on Action class (which can alter the query)
        $query = $this->getQueryFromActiveRecordRequest($entityRequest);

        # Execute beforeFetch hook, which can enforce primary key
        $query = $entityRequest->beforeFetch($query);

        # Now get the primary key in its final form
        $pk = $entityRequest->getPrimaryKey();
        if (empty($pk)) {
            throw new \InvalidArgumentException('Primary key not set in beforeFetch() and not found in URI path');
        }
    
        # Then, fetch object
        $obj = $query->findPk($pk);
    
        if ($obj === null) {
            throw new EntityNotFoundException("Cannot fetch model from DB");
        }
    
        # Get request params
        $params     = $request->getQueryParams();
        $postParams = $request->getParsedBody();
        if ($postParams) {
            $params = array_merge($params, (array)$postParams);
        }
    
        # Then, alter object with allowed properties
        $obj->fromArray($entityRequest->getAllowedDataFromRequest($params, $request->getMethod()));

        # Then, execute afterFetch hook, which can alter the object
        $entityRequest->afterFetch($obj);

        # Finally, build name of parameter to inject in action method, will be used later
        if ($nameOfParameterToAdd === null) {
            $nameOfParameterToAdd = $entityRequest->buildNameOfParameterToAdd();
        }
        $newRequest = $request->withAttribute($nameOfParameterToAdd, $obj);
        $response   = $next($newRequest, $response);
    
        return $response;
    }
    
    /**
     * Create the query (ModelCriteria), based on Action class (which can alter the query)
     *
     * @param EntityRequestInterface $activeRecordRequest
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    private function getQueryFromActiveRecordRequest(EntityRequestInterface $activeRecordRequest)
    {
        $actionClassName = $activeRecordRequest->getActionClassName();
        /** @var ActionInterface $action */
        $action = $actionClassName::create($activeRecordRequest->getContainer());
    
        return $action->createQuery();
    }
}