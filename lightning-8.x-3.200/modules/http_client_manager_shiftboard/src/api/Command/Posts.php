<?php
/**
 * Created by PhpStorm.
 * User: NWu
 * Date: 10/10/2018
 * Time: 10:39 AM
 */

namespace Drupal\http_client_manager_shiftboard\api\Command;


/**
 * Class Posts.
 *
 * Contains all the Guzzle Commands defined inside the "posts" Guzzle Service
 * Description.
 *
 * @package Drupal\http_client_manager_shiftboard\api\Commands
 */
final class Posts {

    const CREATE_POST = 'CreatePost';

    const FIND_POSTS = 'FindPosts';

    const FIND_POST = 'FindPost';

    const FIND_COMMENTS = 'FindComments';

}