<?php

namespace Opencart\Catalog\Controller\Extension\Oblio\Module;

use Exception;

/**
 * Class Oblio
 *
 * @package Opencart\Catalog\Controller\Extension\Oblio\Module
 */
class Oblio extends \Opencart\System\Engine\Controller {
    public function cron() {
        $this->registry->get('autoloader')
            ->register('Opencart\Admin\Controller\Extension\Oblio', DIR_EXTENSION . 'oblio/admin/controller/');

        $adminController = new \Opencart\Admin\Controller\Extension\Oblio\Module\Oblio($this->registry);

        if (is_callable([$adminController, 'syncStock'])) {
            $total = $adminController->syncStock($error);

            return [$total, $error];
        }
        return [0, ''];
    }
}