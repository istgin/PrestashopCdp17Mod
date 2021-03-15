<?php
//<version>1.0.0</version>
class Hook extends HookCore
{
    private static $lastStatus = -1;
    public static function getAllHookRegistrations(Context $context, ?string $hookName): array
    {
        $shop = $context->shop;
        $customer = $context->customer;

        $cache_id = self::MODULE_LIST_BY_HOOK_KEY
            . (isset($shop->id) ? '_' . $shop->id : '')
            . (isset($customer->id) ? '_' . $customer->id : '');

        $useCache = (
        !in_array(
            $hookName,
            [
                'displayPayment',
                'displayPaymentEU',
                'paymentOptions',
                'displayBackOfficeHeader',
                'displayAdminLogin',
            ]
        )
        );

        if ($useCache && Cache::isStored($cache_id)) {
            return Cache::retrieve($cache_id);
        }

        $groups = [];
        $use_groups = Group::isFeatureActive();
        $frontend = !$context->employee instanceof Employee;
        if ($frontend) {
            // Get groups list
            if ($use_groups) {
                if ($customer instanceof Customer && $customer->isLogged()) {
                    $groups = $customer->getGroups();
                } elseif ($customer instanceof Customer && $customer->isLogged(true)) {
                    $groups = [(int) Configuration::get('PS_GUEST_GROUP')];
                } else {
                    $groups = [(int) Configuration::get('PS_UNIDENTIFIED_GROUP')];
                }
            }
        }

        // SQL Request
        $sql = new DbQuery();
        $sql->select('h.`name` as hook, m.`id_module`, h.`id_hook`, m.`name` as module');
        $sql->from('module', 'm');
        if (!in_array($hookName, ['displayBackOfficeHeader', 'displayAdminLogin'])) {
            $sql->join(
                Shop::addSqlAssociation(
                    'module',
                    'm',
                    true,
                    'module_shop.enable_device & ' . (int) Context::getContext()->getDevice()
                )
            );
            $sql->innerJoin('module_shop', 'ms', 'ms.`id_module` = m.`id_module`');
        }
        $sql->innerJoin('hook_module', 'hm', 'hm.`id_module` = m.`id_module`');
        $sql->innerJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
        if ($hookName !== 'paymentOptions') {
            $sql->where('h.`name` != "paymentOptions"');
        } elseif ($frontend) {
            // For payment modules, we check that they are available in the contextual country
            if (Validate::isLoadedObject($context->country)) {
                $sql->where(
                    '(
                        h.`name` IN ("displayPayment", "displayPaymentEU", "paymentOptions")
                        AND (
                            SELECT `id_country`
                            FROM `' . _DB_PREFIX_ . 'module_country` mc
                            WHERE mc.`id_module` = m.`id_module`
                            AND `id_country` = ' . (int) $context->country->id . '
                            AND `id_shop` = ' . (int) $shop->id . '
                            LIMIT 1
                        ) = ' . (int) $context->country->id . ')'
                );
            }
            if (Validate::isLoadedObject($context->currency)) {
                $sql->where(
                    '(
                        h.`name` IN ("displayPayment", "displayPaymentEU", "paymentOptions")
                        AND (
                            SELECT `id_currency`
                            FROM `' . _DB_PREFIX_ . 'module_currency` mcr
                            WHERE mcr.`id_module` = m.`id_module`
                            AND `id_currency` IN (' . (int) $context->currency->id . ', -1, -2)
                            LIMIT 1
                        ) IN (' . (int) $context->currency->id . ', -1, -2))'
                );
            }
            if (Validate::isLoadedObject($context->cart)) {
                $carrier = new Carrier($context->cart->id_carrier);
                if (Validate::isLoadedObject($carrier)) {
                    $sql->where(
                        '(
                            h.`name` IN ("displayPayment", "displayPaymentEU", "paymentOptions")
                            AND (
                                SELECT `id_reference`
                                FROM `' . _DB_PREFIX_ . 'module_carrier` mcar
                                WHERE mcar.`id_module` = m.`id_module`
                                AND `id_reference` = ' . (int) $carrier->id_reference . '
                                AND `id_shop` = ' . (int) $shop->id . '
                                LIMIT 1
                            ) = ' . (int) $carrier->id_reference . ')'
                    );
                }
            }
        }
        if (Validate::isLoadedObject($shop) && $hookName !== 'displayAdminLogin') {
            $sql->where('hm.`id_shop` = ' . (int) $shop->id);
        }

        if ($frontend) {
            if ($use_groups) {
                $sql->leftJoin('module_group', 'mg', 'mg.`id_module` = m.`id_module`');
                if (Validate::isLoadedObject($shop)) {
                    $sql->where(
                        'mg.id_shop = ' . ((int) $shop->id)
                        . (count($groups) ? ' AND  mg.`id_group` IN (' . implode(', ', $groups) . ')' : '')
                    );
                } elseif (count($groups)) {
                    $sql->where('mg.`id_group` IN (' . implode(', ', $groups) . ')');
                }
            }
        }

        $sql->groupBy('hm.id_hook, hm.id_module');
        $sql->orderBy('hm.`position`');

        $allHookRegistrations = [];


        $isCheckout = false;
        /* @var $context Context */
        $controller = $context->controller;
        if ($controller != null && $controller instanceof  OrderController) {
            /* @var $controller OrderControllerCore */
            $checkoutProcess = $controller->getCheckoutProcess();
            if ($checkoutProcess != null && $checkoutProcess instanceof  CheckoutProcess) {
                /* @var $checkoutProcess CheckoutProcessCore */
                $currentStep = $checkoutProcess->getCurrentStep();
                if ($currentStep != null && $currentStep instanceof CheckoutPaymentStep) {
                    /* @var $currentStep CheckoutPaymentStep */
                    $identifier = $currentStep->getIdentifier();
                    if ($identifier == "checkout-payment-step") {
                        $isCheckout = true;
                    }
                }
            }
        }
        if ($isCheckout && in_array(
                $hookName,
                [
                    'displayPayment',
                    'displayPaymentEU',
                    'paymentOptions',
                ]
            )
        ) {
            $disabledMethods = unserialize(Configuration::get("INTRUM_DISABLED_METHODS"));
            $status = 0;
            if (self::$lastStatus == -1) {
                if (!defined('_PS_MODULE_INTRUMCOM_API')) {
                    require(_PS_MODULE_DIR_ . 'intrumcom/api/intrum.php');
                    require(_PS_MODULE_DIR_ . 'intrumcom/api/library_prestashop.php');
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
                    "street" => trim($request->getFirstLine() . ' ' . $request->getHouseNumber()),
                    "country" => $request->getCountryCode(),
                    "ip" => $_SERVER["REMOTE_ADDR"],
                    "status" => $status,
                    "request_id" => $request->getRequestId(),
                    "type" => "Request status",
                    "error" => ($status == 0) ? $response : "",
                    "response" => $response,
                    "request" => $xml
                ));
                self::$lastStatus = $status;
            } else {
                $status = self::$lastStatus;
            }
            $minAmount = Configuration::get("INTRUM_MIN_AMOUNT");
            $currentAmount = $context->cart->getOrderTotal(true, Cart::BOTH);
            $checkIntrum = true;
            if ($minAmount > $currentAmount) {
                $checkIntrum = false;
            }
            $allowed = Array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27, 28, 29, 30, 50, 51, 52, 53, 54, 55, 56, 57);
            if (!in_array($status, $allowed)) {
                $status = 0;
            }
            if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                foreach ($result as $row)
                {
                    $row['hook'] = strtolower($row['hook']);
                    if (!isset($allHookRegistrations[$row['hook']])) {
                        $allHookRegistrations[$row['hook']] = [];
                    }
                    if (!empty($disabledMethods[$status]) && is_array($disabledMethods[$status]) && in_array($row['id_module'], $disabledMethods[$status]) && $checkIntrum) {
                        continue;
                    } else {
                        $allHookRegistrations[$row['hook']][] = [
                            'id_hook' => $row['id_hook'],
                            'module' => $row['module'],
                            'id_module' => $row['id_module']
                        ];
                    }
                }
            }
        } else {
            if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                foreach ($result as $row) {
                    $row['hook'] = strtolower($row['hook']);
                    if (!isset($allHookRegistrations[$row['hook']])) {
                        $allHookRegistrations[$row['hook']] = [];
                    }

                    $allHookRegistrations[$row['hook']][] = [
                        'id_hook' => $row['id_hook'],
                        'module' => $row['module'],
                        'id_module' => $row['id_module'],
                    ];
                }
            }
        }

        if ($useCache) {
            Cache::store($cache_id, $allHookRegistrations);
            // @todo remove this in 1.6, we keep it in 1.5 for backward compatibility
            static::$_hook_modules_cache_exec = $allHookRegistrations;
        }

        return $allHookRegistrations;
    }
}
