<?php

namespace Opencart\Admin\Controller\Extension\Oblio\Module;

interface OblioApiAccessTokenHandlerInterface {
    /**
     *  @return stdClass $accessToken
     */
    public function get();
    
    /**
     *  @param stdClass $accessToken
     */
    public function set($accessToken);
}
