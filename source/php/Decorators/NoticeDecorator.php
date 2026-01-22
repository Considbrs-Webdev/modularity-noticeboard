<?php

namespace ModularityNoticeboard\Decorators;

use Municipio\PostObject\PostObjectInterface;
use Municipio\PostObject\Decorators\AbstractPostObjectDecorator;



/**
 * Class NoticeDecorator
 *
 * Decorator to add service information meta data for templates.
 *
 * @package ModularityNoticeboard\Decorators
 */
class NoticeDecorator extends AbstractPostObjectDecorator implements PostObjectInterface {
    public function __construct(PostObjectInterface $postObject) {
        parent::__construct($postObject);
    }

    public function getExcerpt(): string
    {
        $content = parent::getExcerpt();

        return $content;
    }
    
}