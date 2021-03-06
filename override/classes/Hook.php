<?php
//<version>1.6.0</version>
class Hook extends HookCore
{
    public static function getHookModuleExecList($hook_name = null)
    {
        if (substr(_PS_VERSION_, 0, 3) == '1.5') {
            $context = Context::getContext();
            $cache_id = 'hook_module_exec_list'.((isset($context->customer)) ? '_'.$context->customer->id : '');
            if (!Cache::isStored($cache_id) || $hook_name == 'displayPayment')
            {
                $frontend = true;
                $groups = array();
                if (isset($context->employee))
                {
                    $shop_list = array((int)$context->shop->id);
                    $frontend = false;
                }
                else
                {
                    // Get shops and groups list
                    $shop_list = Shop::getContextListShopID();
                    if (isset($context->customer) && $context->customer->isLogged())
                        $groups = $context->customer->getGroups();
                    elseif (isset($context->customer) && $context->customer->isLogged(true))
                        $groups = array((int)Configuration::get('PS_GUEST_GROUP'));
                    else
                        $groups = array((int)Configuration::get('PS_UNIDENTIFIED_GROUP'));
                }

                // SQL Request
                $sql = new DbQuery();
                $sql->select('h.`name` as hook, m.`id_module`, h.`id_hook`, m.`name` as module, h.`live_edit`');
                $sql->from('module', 'm');
                $sql->innerJoin('hook_module', 'hm', 'hm.`id_module` = m.`id_module`');
                $sql->innerJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
                $sql->where('(SELECT COUNT(*) FROM '._DB_PREFIX_.'module_shop ms WHERE ms.id_module = m.id_module AND ms.id_shop IN ('.implode(', ', $shop_list).')) = '.count($shop_list));
                if ($hook_name != 'displayPayment')
                    $sql->where('h.name != "displayPayment"');
                // For payment modules, we check that they are available in the contextual country
                elseif ($frontend)
                {
                    $sql->where(Module::getPaypalIgnore());
                    if (Validate::isLoadedObject($context->country))
                        $sql->where('(h.name = "displayPayment" AND (SELECT id_country FROM '._DB_PREFIX_.'module_country mc WHERE mc.id_module = m.id_module AND id_country = '.(int)$context->country->id.' AND id_shop = '.(int)$context->shop->id.' LIMIT 1) = '.(int)$context->country->id.')');
                    if (Validate::isLoadedObject($context->currency))
                        $sql->where('(h.name = "displayPayment" AND (SELECT id_currency FROM '._DB_PREFIX_.'module_currency mcr WHERE mcr.id_module = m.id_module AND id_currency IN ('.(int)$context->currency->id.', -1, -2) LIMIT 1) IN ('.(int)$context->currency->id.', -1, -2))');
                }
                if (Validate::isLoadedObject($context->shop))
                    $sql->where('hm.id_shop = '.(int)$context->shop->id);

                if ($frontend)
                {
                    $sql->leftJoin('module_group', 'mg', 'mg.`id_module` = m.`id_module`');
                    if (Validate::isLoadedObject($context->shop))
                        $sql->where('mg.id_shop = '.((int)$context->shop->id).' AND  mg.`id_group` IN ('.implode(', ', $groups).')');
                    else
                        $sql->where('mg.`id_group` IN ('.implode(', ', $groups).')');
                    $sql->groupBy('hm.id_hook, hm.id_module');
                }

                $sql->orderBy('hm.`position`');

                $list = array();
                $disabledMethods = unserialize(Configuration::get("INTRUM_DISABLED_METHODS"));
                if ($hook_name == 'displayPayment') {
                    /* @var $context Context */

                    /* Make intrum request */
                    /* Intrum status */
                    $status = 0;
                    if (!defined('_PS_MODULE_INTRUMCOM_API')) {
                        require(_PS_MODULE_DIR_.'intrumcom/api/intrum.php');
                        require(_PS_MODULE_DIR_.'intrumcom/api/library_prestashop.php');
                    }

                    $request = CreatePrestaShopRequest($context->cart, $context->customer, $context->currency);
                    $xml = $request->createRequest();
                    $intrumCommunicator = new IntrumCommunicator();
                    $intrumCommunicator->setServer(Configuration::get("INTRUM_MODE"));
                    $response = $intrumCommunicator->sendRequest($xml);

                    if ($response) {
                        $intrumResponse = new IntrumResponse();
                        $intrumResponse->setRawResponse($response);
                        $intrumResponse->processResponse();
                        $status = $intrumResponse->getCustomerRequestStatus();
                    }
                    $intrumLogger = IntrumLogger::getInstance();
                    $intrumLogger->log(Array(
                        "firstname" => $request->getFirstName(),
                        "lastname" => $request->getLastName(),
                        "town" => $request->getTown(),
                        "postcode" => $request->getPostCode(),
                        "street" => trim($request->getFirstLine().' '.$request->getHouseNumber()),
                        "country" => $request->getCountryCode(),
                        "ip" => $_SERVER["REMOTE_ADDR"],
                        "status" => $status,
                        "request_id" => $request->getRequestId(),
                        "type" => "Request status",
                        "error" => ($status == 0) ? $response : "",
                        "response" => $response,
                        "request" => $xml
                    ));
                    $minAmount = Configuration::get("INTRUM_MIN_AMOUNT");
                    $currentAmount = $context->cart->getOrderTotal(true, Cart::BOTH);
                    $checkIntrum = true;
                    if ($minAmount > $currentAmount) {
                        $checkIntrum = false;
                    }

                    $allowed = Array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,27,28,29,30,50,51,52,53,54,55,56,57);
                    if (!in_array($status, $allowed)) {
                        $status = 0;
                    }
                    if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                        foreach ($result as $row)
                        {
                            if (!empty($disabledMethods[$status]) && is_array($disabledMethods[$status]) && in_array($row['id_module'], $disabledMethods[$status]) && $checkIntrum) {
                                continue;
                            }
                            $row['hook'] = strtolower($row['hook']);
                            if (!isset($list[$row['hook']]))
                                $list[$row['hook']] = array();

                            $list[$row['hook']][] = array(
                                'id_hook' => $row['id_hook'],
                                'module' => $row['module'],
                                'id_module' => $row['id_module'],
                                'live_edit' => $row['live_edit'],
                            );
                        }
                    }
                } else {
                    if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql))
                        foreach ($result as $row)
                        {
                            $row['hook'] = strtolower($row['hook']);
                            if (!isset($list[$row['hook']]))
                                $list[$row['hook']] = array();

                            $list[$row['hook']][] = array(
                                'id_hook' => $row['id_hook'],
                                'module' => $row['module'],
                                'id_module' => $row['id_module'],
                                'live_edit' => $row['live_edit'],
                            );
                        }
                }
                if ($hook_name != 'displayPayment')
                {
                    Cache::store($cache_id, $list);
                    // @todo remove this in 1.6, we keep it in 1.5 for retrocompatibility
                    self::$_hook_modules_cache_exec = $list;
                }
            }
            else
                $list = Cache::retrieve($cache_id);

            // If hook_name is given, just get list of modules for this hook
            if ($hook_name)
            {
                $retro_hook_name = Hook::getRetroHookName($hook_name);
                $hook_name = strtolower($hook_name);

                $return = array();
                $inserted_modules = array();
                if (isset($list[$hook_name]))
                    $return = $list[$hook_name];
                foreach ($return as $module)
                    $inserted_modules[] = $module['id_module'];
                if (isset($list[$retro_hook_name]))
                    foreach ($list[$retro_hook_name] as $retro_module_call)
                        if (!in_array($retro_module_call['id_module'], $inserted_modules))
                            $return[] = $retro_module_call;

                return (count($return) > 0 ? $return : false);
            }
            else
                return $list;

        } else {
            $context = Context::getContext();
            $cache_id = 'hook_module_exec_list_'.(isset($context->shop->id) ? '_'.$context->shop->id : '' ).((isset($context->customer)) ? '_'.$context->customer->id : '');
            if (!Cache::isStored($cache_id) || $hook_name == 'displayPayment' || $hook_name == 'displayBackOfficeHeader')
            {
                $frontend = true;
                $groups = array();
                $use_groups = Group::isFeatureActive();
                if (isset($context->employee))
                    $frontend = false;
                else
                {
                    // Get groups list
                    if ($use_groups)
                    {
                        if (isset($context->customer) && $context->customer->isLogged())
                            $groups = $context->customer->getGroups();
                        elseif (isset($context->customer) && $context->customer->isLogged(true))
                            $groups = array((int)Configuration::get('PS_GUEST_GROUP'));
                        else
                            $groups = array((int)Configuration::get('PS_UNIDENTIFIED_GROUP'));
                    }
                }

                // SQL Request
                $sql = new DbQuery();
                $sql->select('h.`name` as hook, m.`id_module`, h.`id_hook`, m.`name` as module, h.`live_edit`');
                $sql->from('module', 'm');
                if ($hook_name != 'displayBackOfficeHeader')
                {
                    $sql->join(Shop::addSqlAssociation('module', 'm', true, 'module_shop.enable_device & '.(int)Context::getContext()->getDevice()));
                    $sql->innerJoin('module_shop', 'ms', 'ms.`id_module` = m.`id_module`');
                }
                $sql->innerJoin('hook_module', 'hm', 'hm.`id_module` = m.`id_module`');
                $sql->innerJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
                if ($hook_name != 'displayPayment')
                    $sql->where('h.name != "displayPayment"');
                // For payment modules, we check that they are available in the contextual country
                elseif ($frontend)
                {
                    if (Validate::isLoadedObject($context->country))
                        $sql->where('(h.name = "displayPayment" AND (SELECT id_country FROM '._DB_PREFIX_.'module_country mc WHERE mc.id_module = m.id_module AND id_country = '.(int)$context->country->id.' AND id_shop = '.(int)$context->shop->id.' LIMIT 1) = '.(int)$context->country->id.')');
                    if (Validate::isLoadedObject($context->currency))
                        $sql->where('(h.name = "displayPayment" AND (SELECT id_currency FROM '._DB_PREFIX_.'module_currency mcr WHERE mcr.id_module = m.id_module AND id_currency IN ('.(int)$context->currency->id.', -1, -2) LIMIT 1) IN ('.(int)$context->currency->id.', -1, -2))');
                }
                if (Validate::isLoadedObject($context->shop))
                    $sql->where('hm.id_shop = '.(int)$context->shop->id);

                if ($frontend)
                {
                    if ($use_groups)
                    {
                        $sql->leftJoin('module_group', 'mg', 'mg.`id_module` = m.`id_module`');
                        if (Validate::isLoadedObject($context->shop))
                            $sql->where('mg.id_shop = '.((int)$context->shop->id).' AND  mg.`id_group` IN ('.implode(', ', $groups).')');
                        else
                            $sql->where('mg.`id_group` IN ('.implode(', ', $groups).')');
                    }
                }

                $sql->groupBy('hm.id_hook, hm.id_module');
                $sql->orderBy('hm.`position`');

                $list = array();
                $disabledMethods = unserialize(Configuration::get("INTRUM_DISABLED_METHODS"));
                if ($hook_name == 'displayPayment') {
                    /* @var $context Context */
                    $status = 0;
                    if (!defined('_PS_MODULE_INTRUMCOM_API')) {
                        require(_PS_MODULE_DIR_.'intrumcom/api/intrum.php');
                        require(_PS_MODULE_DIR_.'intrumcom/api/library_prestashop.php');
                    }

                    $request = CreatePrestaShopRequest($context->cart, $context->customer, $context->currency);
                    $xml = $request->createRequest();
                    $intrumCommunicator = new IntrumCommunicator();
                    $intrumCommunicator->setServer(Configuration::get("INTRUM_MODE"));
                    $response = $intrumCommunicator->sendRequest($xml);

                    if ($response) {
                        $intrumResponse = new IntrumResponse();
                        $intrumResponse->setRawResponse($response);
                        $intrumResponse->processResponse();
                        $status = $intrumResponse->getCustomerRequestStatus();
                    }
                    $intrumLogger = IntrumLogger::getInstance();
                    $intrumLogger->log(Array(
                        "firstname" => $request->getFirstName(),
                        "lastname" => $request->getLastName(),
                        "town" => $request->getTown(),
                        "postcode" => $request->getPostCode(),
                        "street" => trim($request->getFirstLine().' '.$request->getHouseNumber()),
                        "country" => $request->getCountryCode(),
                        "ip" => $_SERVER["REMOTE_ADDR"],
                        "status" => $status,
                        "request_id" => $request->getRequestId(),
                        "type" => "Request status",
                        "error" => ($status == 0) ? $response : "",
                        "response" => $response,
                        "request" => $xml
                    ));
                    $minAmount = Configuration::get("INTRUM_MIN_AMOUNT");
                    $currentAmount = $context->cart->getOrderTotal(true, Cart::BOTH);
                    $checkIntrum = true;
                    if ($minAmount > $currentAmount) {
                        $checkIntrum = false;
                    }

                    $allowed = Array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,27,28,29,30,50,51,52,53,54,55,56,57);
                    if (!in_array($status, $allowed)) {
                        $status = 0;
                    }
                    if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                        foreach ($result as $row)
                        {

                            $row['hook'] = strtolower($row['hook']);
                            if (!isset($list[$row['hook']]))
                                $list[$row['hook']] = array();
                            if (!empty($disabledMethods[$status]) && is_array($disabledMethods[$status]) && in_array($row['id_module'], $disabledMethods[$status]) && $checkIntrum) {

                                $list[$row['hook']][] = array(
                                    'id_hook' => $row['id_hook'],
                                    'module' => $row['module'],
                                    'id_module' => $row['id_module'],
                                    'live_edit' => $row['live_edit'],
                                    'forbidden' => 1,
                                );
                            } else {
                                $list[$row['hook']][] = array(
                                    'id_hook' => $row['id_hook'],
                                    'module' => $row['module'],
                                    'id_module' => $row['id_module'],
                                    'live_edit' => $row['live_edit'],
                                    'forbidden' => 0,
                                );
                            }
                        }
                    }
                } else {
                    if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql))
                        foreach ($result as $row)
                        {
                            $row['hook'] = strtolower($row['hook']);
                            if (!isset($list[$row['hook']]))
                                $list[$row['hook']] = array();

                            $list[$row['hook']][] = array(
                                'id_hook' => $row['id_hook'],
                                'module' => $row['module'],
                                'id_module' => $row['id_module'],
                                'live_edit' => $row['live_edit'],
                            );
                        }
                }
                if ($hook_name != 'displayPayment' && $hook_name != 'displayBackOfficeHeader')
                {
                    Cache::store($cache_id, $list);
                    // @todo remove this in 1.6, we keep it in 1.5 for retrocompatibility
                    self::$_hook_modules_cache_exec = $list;
                }
            }
            else
                $list = Cache::retrieve($cache_id);

            // If hook_name is given, just get list of modules for this hook
            if ($hook_name)
            {
                $retro_hook_name = strtolower(Hook::getRetroHookName($hook_name));
                $hook_name = strtolower($hook_name);

                $return = array();
                $inserted_modules = array();
                if (isset($list[$hook_name]))
                    $return = $list[$hook_name];
                foreach ($return as $module)
                    $inserted_modules[] = $module['id_module'];
                if (isset($list[$retro_hook_name]))
                    foreach ($list[$retro_hook_name] as $retro_module_call)
                        if (!in_array($retro_module_call['id_module'], $inserted_modules))
                            $return[] = $retro_module_call;

                return (count($return) > 0 ? $return : false);
            }
            else
                return $list;
        }
    }

    public static function exec($hook_name, $hook_args = array(), $id_module = null, $array_return = false, $check_exceptions = true, $use_push = false, $id_shop = null)
    {
        if (defined('PS_INSTALLATION_IN_PROGRESS'))
            return;
        if ($hook_name == 'displayPayment' || $hook_name == 'displayBackOfficeHeader')
        {
            static $disable_non_native_modules = null;
            if ($disable_non_native_modules === null)
                $disable_non_native_modules = (bool)Configuration::get('PS_DISABLE_NON_NATIVE_MODULE');

            // Check arguments validity
            if (($id_module && !is_numeric($id_module)) || !Validate::isHookName($hook_name))
                throw new PrestaShopException('Invalid id_module or hook_name');

            // If no modules associated to hook_name or recompatible hook name, we stop the function

            if (!$module_list = Hook::getHookModuleExecList($hook_name))
                return '';

            // Check if hook exists
            if (!$id_hook = Hook::getIdByName($hook_name))
                return false;

            // Store list of executed hooks on this page
            Hook::$executed_hooks[$id_hook] = $hook_name;

            $live_edit = false;
            $context = Context::getContext();
            if (!isset($hook_args['cookie']) || !$hook_args['cookie'])
                $hook_args['cookie'] = $context->cookie;
            if (!isset($hook_args['cart']) || !$hook_args['cart'])
                $hook_args['cart'] = $context->cart;

            $retro_hook_name = Hook::getRetroHookName($hook_name);

            // Look on modules list
            $altern = 0;
            $output = '';

            if ($disable_non_native_modules && !isset(Hook::$native_module))
                Hook::$native_module = Module::getNativeModuleList();

            $different_shop = false;
            if ($id_shop !== null && Validate::isUnsignedId($id_shop) && $id_shop != $context->shop->getContextShopID()) {
                $old_context_shop_id = $context->shop->getContextShopID();
                $old_context = $context->shop->getContext();
                $old_shop = clone $context->shop;
                $shop = new Shop((int)$id_shop);
                if (Validate::isLoadedObject($shop)) {
                    $context->shop = $shop;
                    $context->shop->setContext(Shop::CONTEXT_SHOP, $shop->id);
                    $different_shop = true;
                }
            }
            usort($module_list, function ($a, $b) { return $a["forbidden"] >  $b["forbidden"]; });
            foreach ($module_list as $array) {
                // Check errors
                if ($id_module && $id_module != $array['id_module'])
                    continue;

                if ((bool)$disable_non_native_modules && Hook::$native_module && count(Hook::$native_module) && !in_array($array['module'], self::$native_module))
                    continue;

                // Check permissions
                if ($check_exceptions) {
                    $exceptions = Module::getExceptionsStatic($array['id_module'], $array['id_hook']);

                    $controller = Dispatcher::getInstance()->getController();
                    $controller_obj = Context::getContext()->controller;

                    //check if current controller is a module controller
                    if (isset($controller_obj->module) && Validate::isLoadedObject($controller_obj->module))
                        $controller = 'module-' . $controller_obj->module->name . '-' . $controller;

                    if (in_array($controller, $exceptions))
                        continue;

                    //retro compat of controller names
                    $matching_name = array(
                        'authentication' => 'auth',
                        'productscomparison' => 'compare'
                    );
                    if (isset($matching_name[$controller]) && in_array($matching_name[$controller], $exceptions))
                        continue;
                    if (Validate::isLoadedObject($context->employee) && !Module::getPermissionStatic($array['id_module'], 'view', $context->employee))
                        continue;
                }

                if (!($moduleInstance = Module::getInstanceByName($array['module'])))
                    continue;

                if ($use_push && !$moduleInstance->allow_push)
                    continue;
                // Check which / if method is callable
                $hook_callable = is_callable(array($moduleInstance, 'hook' . $hook_name));
                $hook_retro_callable = is_callable(array($moduleInstance, 'hook' . $retro_hook_name));

                if (($hook_callable || $hook_retro_callable) && Module::preCall($moduleInstance->name)) {
                    $hook_args['altern'] = ++$altern;
                    $hook_args['intrum_cdp']["forbidden"] = $array["forbidden"];

                    if ($use_push && isset($moduleInstance->push_filename) && file_exists($moduleInstance->push_filename))
                        Tools::waitUntilFileIsModified($moduleInstance->push_filename, $moduleInstance->push_time_limit);

                    // Call hook method
                    if ($hook_callable)
                        $display = $moduleInstance->{'hook' . $hook_name}($hook_args);
                    elseif ($hook_retro_callable)
                        $display = $moduleInstance->{'hook' . $retro_hook_name}($hook_args);
                    // Live edit
                    if (isset($array["forbidden"]) && $array["forbidden"] == 1) {
                        $display = preg_replace('/<a(.*)href="([^"]*)"(.*)>/','<a$1href="javascript:void(0);"$3>', $display);
                        $display = "<div style='pointer-events:none'>".$display."</div>";
                    }
                    if (!$array_return && $array['live_edit'] && Tools::isSubmit('live_edit') && Tools::getValue('ad') && Tools::getValue('liveToken') == Tools::getAdminToken('AdminModulesPositions' . (int)Tab::getIdFromClassName('AdminModulesPositions') . (int)Tools::getValue('id_employee'))) {
                        $live_edit = true;
                        $output .= self::wrapLiveEdit($display, $moduleInstance, $array['id_hook']);
                    } elseif ($array_return)
                        $output[$moduleInstance->name] = $display;
                    else
                        $output .= $display;
                }
            }

            if ($different_shop) {
                $context->shop = $old_shop;
                $context->shop->setContext($old_context, $shop->id);
            }

            if ($array_return)
                return $output;
            else
                return ($live_edit ? '<script type="text/javascript">hooks_list.push(\'' . $hook_name . '\');</script>
				<div id="' . $hook_name . '" class="dndHook" style="min-height:50px">' : '') . $output . ($live_edit ? '</div>' : '');// Return html string

        } else {

            static $disable_non_native_modules = null;
            if ($disable_non_native_modules === null)
                $disable_non_native_modules = (bool)Configuration::get('PS_DISABLE_NON_NATIVE_MODULE');

            // Check arguments validity
            if (($id_module && !is_numeric($id_module)) || !Validate::isHookName($hook_name))
                throw new PrestaShopException('Invalid id_module or hook_name');

            // If no modules associated to hook_name or recompatible hook name, we stop the function

            if (!$module_list = Hook::getHookModuleExecList($hook_name))
                return '';

            // Check if hook exists
            if (!$id_hook = Hook::getIdByName($hook_name))
                return false;

            // Store list of executed hooks on this page
            Hook::$executed_hooks[$id_hook] = $hook_name;

            $live_edit = false;
            $context = Context::getContext();
            if (!isset($hook_args['cookie']) || !$hook_args['cookie'])
                $hook_args['cookie'] = $context->cookie;
            if (!isset($hook_args['cart']) || !$hook_args['cart'])
                $hook_args['cart'] = $context->cart;

            $retro_hook_name = Hook::getRetroHookName($hook_name);

            // Look on modules list
            $altern = 0;
            $output = '';

            if ($disable_non_native_modules && !isset(Hook::$native_module))
                Hook::$native_module = Module::getNativeModuleList();

            $different_shop = false;
            if ($id_shop !== null && Validate::isUnsignedId($id_shop) && $id_shop != $context->shop->getContextShopID()) {
                $old_context_shop_id = $context->shop->getContextShopID();
                $old_context = $context->shop->getContext();
                $old_shop = clone $context->shop;
                $shop = new Shop((int)$id_shop);
                if (Validate::isLoadedObject($shop)) {
                    $context->shop = $shop;
                    $context->shop->setContext(Shop::CONTEXT_SHOP, $shop->id);
                    $different_shop = true;
                }
            }

            foreach ($module_list as $array) {
                // Check errors
                if ($id_module && $id_module != $array['id_module'])
                    continue;

                if ((bool)$disable_non_native_modules && Hook::$native_module && count(Hook::$native_module) && !in_array($array['module'], self::$native_module))
                    continue;

                // Check permissions
                if ($check_exceptions) {
                    $exceptions = Module::getExceptionsStatic($array['id_module'], $array['id_hook']);

                    $controller = Dispatcher::getInstance()->getController();
                    $controller_obj = Context::getContext()->controller;

                    //check if current controller is a module controller
                    if (isset($controller_obj->module) && Validate::isLoadedObject($controller_obj->module))
                        $controller = 'module-' . $controller_obj->module->name . '-' . $controller;

                    if (in_array($controller, $exceptions))
                        continue;

                    //retro compat of controller names
                    $matching_name = array(
                        'authentication' => 'auth',
                        'productscomparison' => 'compare'
                    );
                    if (isset($matching_name[$controller]) && in_array($matching_name[$controller], $exceptions))
                        continue;
                    if (Validate::isLoadedObject($context->employee) && !Module::getPermissionStatic($array['id_module'], 'view', $context->employee))
                        continue;
                }

                if (!($moduleInstance = Module::getInstanceByName($array['module'])))
                    continue;

                if ($use_push && !$moduleInstance->allow_push)
                    continue;
                // Check which / if method is callable
                $hook_callable = is_callable(array($moduleInstance, 'hook' . $hook_name));
                $hook_retro_callable = is_callable(array($moduleInstance, 'hook' . $retro_hook_name));

                if (($hook_callable || $hook_retro_callable) && Module::preCall($moduleInstance->name)) {
                    $hook_args['altern'] = ++$altern;

                    if ($use_push && isset($moduleInstance->push_filename) && file_exists($moduleInstance->push_filename))
                        Tools::waitUntilFileIsModified($moduleInstance->push_filename, $moduleInstance->push_time_limit);

                    // Call hook method
                    if ($hook_callable)
                        $display = $moduleInstance->{'hook' . $hook_name}($hook_args);
                    elseif ($hook_retro_callable)
                        $display = $moduleInstance->{'hook' . $retro_hook_name}($hook_args);
                    // Live edit
                    if (!$array_return && $array['live_edit'] && Tools::isSubmit('live_edit') && Tools::getValue('ad') && Tools::getValue('liveToken') == Tools::getAdminToken('AdminModulesPositions' . (int)Tab::getIdFromClassName('AdminModulesPositions') . (int)Tools::getValue('id_employee'))) {
                        $live_edit = true;
                        $output .= self::wrapLiveEdit($display, $moduleInstance, $array['id_hook']);
                    } elseif ($array_return)
                        $output[$moduleInstance->name] = $display;
                    else
                        $output .= $display;
                }
            }

            if ($different_shop) {
                $context->shop = $old_shop;
                $context->shop->setContext($old_context, $shop->id);
            }

            if ($array_return)
                return $output;
            else
                return ($live_edit ? '<script type="text/javascript">hooks_list.push(\'' . $hook_name . '\');</script>
				<div id="' . $hook_name . '" class="dndHook" style="min-height:50px">' : '') . $output . ($live_edit ? '</div>' : '');// Return html string
        }
    }
}