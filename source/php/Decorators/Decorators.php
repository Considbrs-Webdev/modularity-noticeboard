<?php

namespace ModularityNoticeboard\Decorators;

use ModularityNoticeboard\Data\Posttype;
use Municipio\PostObject\PostObjectInterface;

class Decorators {
    /**
     * Initialize decorators and setup
     */
    public function __construct() {
        add_filter('Municipio/DecoratePostObject', function (PostObjectInterface $postObject) {
            if ($postObject->getPostType() !== Posttype::NOTICE_POST_TYPE) {
                return $postObject;
            }

            $postObject = new NoticeDecorator($postObject);
            
            return $postObject;
        }, 20);
    }
}
