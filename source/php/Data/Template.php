<?php
namespace ModularityNoticeboard\Data;

use ModularityNoticeboard\ViewCallableProviders\GetExcerpt;
use ModularityNoticeboard\ViewCallableProviders\GetTextSearchFieldArguments;

/**
 * Class Template
 *
 * Adds view data for noticeboard templates.
 *
 * @package ModularityNoticeboard\Data
 */
class Template {
    public function __construct()
    {
        add_filter('Municipio/Template/'. Posttype::NOTICE_POST_TYPE .'/archive/viewData', function($data) {
            $wpService = \Modularity\Helper\WpService::get();

            $data['getExcerpt'] = (new GetExcerpt($wpService))->getCallable();
        
            return $data;
        }, 10, 1);
    }
}