<?php
namespace Drupal\shipbaord_pages\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Created by PhpStorm.
 * User: NWu
 * Date: 10/2/2018
 * Time: 11:41 AM
 */

class ShipboardPagesController extends ControllerBase

{
    /**
     * An SHIPBOARD PAGES Services - Contents HTTP Client.
     *
     * @var \Drupal\http_client_manager\HttpClientInterface
     */
    protected $httpClient;


    /**
     * ShipboardPagesController constructor.
     *
     * @param \Drupal\http_client_manager\HttpClientInterface $http_client

     *   The HTTP Client Manager Factory service.
     */
    public function __construct(HttpClientInterface $http_client) {
        $this->httpClient = $http_client;
    }


    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('http_client_manager.factory')
        );
    }
    /**
     * Posts route callback.
     *
     * @param int $limit
     *   The total number of posts we want to fetch.
     * @param string $sort
     *   The sorting order.
     *
     * @return array
     *   A render array used to show the Posts list.
     */
    public function posts($limit, $sort) {
        $posts = $this->httpClient->call('GetPosts', [
            'limit' => (int) $limit,
            'sort' => $sort,
        ]);

        // .. omitted code.
    }


}