<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 11/10/17
 * Time: 16:24
 */

namespace Eukles\Entity\Middleware;

use Eukles\Container\ContainerInterface;
use Eukles\Container\ContainerTrait;
use Eukles\Service\Router\RouteInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CollectionFetch
 *
 * @todo    refactor
 * @package Eukles\Entity\Middleware
 */
class CollectionFetch /*implements RouteEntityMiddlewareInterface*/
{

    use ContainerTrait;
    /**
     * @var RouteInterface
     */
    protected $route;

    /**
     * CollectionFetch constructor.
     *
     * @param ContainerInterface $container
     * @param RouteInterface     $route
     */
    public function __construct(ContainerInterface $container, RouteInterface $route)
    {
        $this->container = $container;
        $this->route     = $route;
    }

    /**
     * @param $request
     * @param $response
     * @param $next
     *
     * @return ResponseInterface
     */
    public function __invoke($request, $response, $next): ResponseInterface
    {
        $requestClass = $this->route->getRequestClass();
        /** @var ContainerInterface $this */
        $response = $this->container->getEntityFactory()->fetchCollection(
            new $requestClass($this->container),
            $request,
            $response,
            $next,
            $this->route->getNameOfInjectedParam()
        );

        return $response;
    }
}