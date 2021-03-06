<?php
if (!defined('_PS_VERSION_'))
    exit;

if (!defined('_PS_MODULE_INTRUMCOM_API')) {
    require(_PS_MODULE_DIR_.'intrumcom/api/intrum.php');
    require(_PS_MODULE_DIR_.'intrumcom/api/library_prestashop.php');
}

class Intrumcom extends Module
{
    public function __construct()
    {
        $this->name = 'intrumcom';
        $this->tab = 'payments';
        $this->version = '1.0.0';
        $this->author = 'Intrum (http://www.intrum.com/ch/de/)';
        $this->need_instance = 0;
        parent::__construct();
        $this->displayName = $this->l('Intrum CDP');
        $this->description = $this->l('Credit design platform Intrum module');
		$this->l('Bestellung auf Rechnung nicht verfügbar');
    }

    public static function abc()
    {

    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('orderConfirmation') || !$this->registerHook('displayBeforeShoppingCartBlock') ){
            return false;
        }
        if ($this->moveOverriteFiles()) {
            Configuration::updateValue('INTRUM_SUBMIT_MAIN', '');
            Configuration::updateValue('INTRUM_SUBMIT_PAYMENTS', '');

            Configuration::updateValue('INTRUM_MODE', 'test');
            Configuration::updateValue('INTRUM_CLIENT_ID', '');
            Configuration::updateValue('INTRUM_USER_ID', '');
            Configuration::updateValue('INTRUM_PASSWORD','');
            Configuration::updateValue('INTRUM_TECH_EMAIL','');
            Configuration::updateValue('INTRUM_MIN_AMOUNT', '10');
            Configuration::updateValue('INTRUM_ENABLETMX', 'false');
            Configuration::updateValue('INTRUM_TMXORGID', '');

            Configuration::updateValue('INTRUM_DISABLED_METHODS', serialize(array()));

            Db::getInstance()->Execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'intrum_logs` (
                  `intrum_id` int(10) unsigned NOT NULL auto_increment,
                  `firstname` varchar(250) default NULL,
                  `lastname` varchar(250) default NULL,
                  `town` varchar(250) default NULL,
                  `postcode` varchar(250) default NULL,
                  `street` varchar(250) default NULL,
                  `country` varchar(250) default NULL,
                  `ip` varchar(250) default NULL,
                  `status` varchar(250) default NULL,
                  `request_id` varchar(250) default NULL,
                  `type` varchar(250) default NULL,
                  `error` text default NULL,
                  `response` text default NULL,
                  `request` text default NULL,
                  `creation_date` TIMESTAMP NULL DEFAULT now() ,
                  PRIMARY KEY  (`intrum_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');

            $allowedMethods = Array();
            $payment_methods = $this->getPayment();

			$allowed = Array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,27,28,29,30,50,51,52,53,54,55,56,57);
			foreach($allowed as $status_val) {
                foreach($payment_methods as $payment) {
                    $allowedMethods[$status_val][] = $payment['id_module'];
                }
            }
            return true;
        }
        return false;
    }

    private function moveOverriteFiles()
    {
        if(!copy(dirname(__FILE__).'/override/classes/Hook.php',_PS_ROOT_DIR_ .'/override/classes/Hook.php'))
            return false;
        return true;
    }
	
	private function moveOverriteFilesWithReplace()
    {
		unlink(_PS_ROOT_DIR_ .'/override/classes/Hook.php');
        if(!copy(dirname(__FILE__).'/override/classes/Hook.php',_PS_ROOT_DIR_ .'/override/classes/Hook.php'))
            return false;
        return true;
    }

    public function hookDisplayBeforeShoppingCartBlock($params) {
        if (Configuration::get("INTRUM_ENABLETMX") == 'true' && Configuration::get("INTRUM_TMXORGID") != '') {
			global $cookie;
			$cookie->intrumId = Tools::getToken(false);
            echo '
                <script type="text/javascript" src="https://h.online-metrix.net/fp/tags.js?org_id='.Configuration::get("INTRUM_TMXORGID").'&session_id='.$cookie->intrumId.'&pageid=checkout"></script>
            <noscript>
            <iframe style="width: 100px; height: 100px; border: 0; position: absolute; top: -5000px;" src="https://h.online-metrix.net/tags?org_id='.Configuration::get("INTRUM_TMXORGID").'&session_id='.$cookie->intrumId.'&pageid=checkout"></iframe>
            </noscript>
                ';
        }
    }
	
	private function getHookVersion()
    {	
		$version = '';
        if (file_exists(_PS_ROOT_DIR_ .'/override/classes/Hook.php')) {
			$fileCode = file_get_contents(_PS_ROOT_DIR_ .'/override/classes/Hook.php');
			$pattern = "/<version>(.*?)<\/version>/si";
			if (preg_match($pattern, $fileCode, $content)) {
				$content[1] = preg_replace('/\s+|\r|\n/', ' ', $content[1]);
				$content[1] = preg_replace('/&nbsp;/', ' ', $content[1]);
				$content[1] = preg_replace('/\s+/', ' ', $content[1]);
				$version = trim(strip_tags($content[1]));
			}
        }
        return $version;
    }

    public function uninstall()
    {
        // Uninstall module
        if (!parent::uninstall()) {
            return false;
        }		
        if (file_exists(_PS_ROOT_DIR_ .'/override/classes/Hook.php')) {
            unlink(_PS_ROOT_DIR_ .'/override/classes/Hook.php');
        }
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'intrum_logs` ');
        return true;
    }

    private function _postProcess()
    {
		if (Tools::isSubmit('upgradehook'))
        {
			$this->moveOverriteFilesWithReplace();
		}
        if (Tools::isSubmit('submitIntrumMethods'))
        {
            $data = Tools::getValue('data');
            $disabledMethods = Array();
            if (!empty($data) && is_array($data)) {
                foreach($data as $status => $val) {
                    if (is_array($data[$status])) {
                        if (isset($val[0]) && is_array($val[0])) {
                            foreach($val[0] as $methodId => $val2) {
                                $disabledMethods[$status][] = $methodId;
                            }
                        }
                    }
                }
                Configuration::updateValue('INTRUM_DISABLED_METHODS', serialize($disabledMethods));

            }
            Configuration::updateValue('INTRUM_SUBMIT_PAYMENTS', 'OK');
        }
        if (Tools::isSubmit('submitIntrumMain'))
        {
            Configuration::updateValue('INTRUM_SUBMIT_MAIN', 'OK');
            Configuration::updateValue('INTRUM_MODE', trim(Tools::getValue('intrum_mode')));
            Configuration::updateValue('INTRUM_CLIENT_ID', trim(Tools::getValue('intrum_client_id')));
            Configuration::updateValue('INTRUM_USER_ID', trim(Tools::getValue('intrum_user_id')));
            Configuration::updateValue('INTRUM_PASSWORD', trim(Tools::getValue('intrum_password')));
            Configuration::updateValue('INTRUM_TECH_EMAIL', trim(Tools::getValue('intrum_tech_email')));
            Configuration::updateValue('INTRUM_MIN_AMOUNT', trim(Tools::getValue('intrum_min_amount')));
            Configuration::updateValue('INTRUM_ENABLETMX', trim(Tools::getValue('intrum_enabletmx')));
            Configuration::updateValue('INTRUM_TMXORGID', trim(Tools::getValue('intrum_tmxorgid')));
        }
        if (Tools::isSubmit('submitLogSearch'))
        {
            Configuration::updateValue('INTRUM_SHOW_LOG', 'true');
        }
    }

    function getContent()
    {
        Configuration::updateValue('INTRUM_SHOW_LOG', 'false');
        $this->_postProcess();
        $methods = Array();
        $payment_methods = $this->getPayment();
        $disabledMethods = unserialize(Configuration::get("INTRUM_DISABLED_METHODS"));
		$allowed = Array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,27,28,29,30,50,51,52,53,54,55,56,57);
        foreach($allowed as $status_val) {
            $output = '';
            foreach ($payment_methods as $payment) {
                if (file_exists('../modules/' . $payment['name'] . '/logo.png')) {
                    $output .= '<img src="' . __PS_BASE_URI__ . 'modules/' . $payment['name'] . '/logo.png" width="16" title="' . $payment['name'] . '" alt="' . $payment['name'] . '" style="vertical-align:middle" />';
                } else if (file_exists('../modules/' . $payment['name'] . '/logo.gif')) {
                    $output .= '<img src="' . __PS_BASE_URI__ . 'modules/' . $payment['name'] . '/logo.gif" width="16" title="' . $payment['name'] . '" alt="' . $payment['name'] . '" style="vertical-align:middle" />';
                } else {
                    $output .= '' . $payment['name'] . '';
                }
                $checked = false;
                if (!empty($disabledMethods[$status_val]) && is_array($disabledMethods[$status_val]) && in_array($payment['id_module'], $disabledMethods[$status_val])) {
                    $checked = true;
                }
                $output = $output . ' <input type="checkbox" name="data[' . $status_val . '][0][' . $payment['id_module'] . ']" value="1" ' . ($checked ? 'checked="checked"' : '') . ' /> (' . $payment['displayName'] . ')<br />';
            }
            $methods[$status_val]["false"] = $output;
        }
		$version = $this->getHookVersion();
        $values = array(
            'this_path' => $this->_path,
            'intrum_submit_main' => Configuration::get("INTRUM_SUBMIT_MAIN"),
            'intrum_submit_payments' => Configuration::get("INTRUM_SUBMIT_PAYMENTS"),
            'intrum_mode' => Configuration::get("INTRUM_MODE"),
            'intrum_client_id' => Configuration::get("INTRUM_CLIENT_ID"),
            'intrum_user_id' => Configuration::get("INTRUM_USER_ID"),
            'intrum_password' => Configuration::get("INTRUM_PASSWORD"),
            'intrum_tech_email' => Configuration::get("INTRUM_TECH_EMAIL"),
            'intrum_min_amount' => Configuration::get("INTRUM_MIN_AMOUNT"),
            'intrum_show_log' => Configuration::get("INTRUM_SHOW_LOG"),
            'intrum_enabletmx' => Configuration::get("INTRUM_ENABLETMX"),
            'intrum_tmxorgid' => Configuration::get("INTRUM_TMXORGID"),
            'payment_methods' => $methods,
            'intrum_logs' => $this->getLogs(),
            'search_in_log' => Tools::getValue('searchInLog'),
            'upgrade_require' => ($version != $this->version) ? 1 : 0
        );
        $this->context->smarty->assign($values);

        Configuration::updateValue('INTRUM_SUBMIT_MAIN', '');
        Configuration::updateValue('INTRUM_SUBMIT_PAYMENTS', '');
        $output = $this->fetchTemplate('/views/templates/admin/back_office.tpl');

        return $output;
    }

    public function fetchTemplate($name)
    {
        if (version_compare(_PS_VERSION_, '1.4', '<'))
            $this->context->smarty->currentTemplate = $name;
        elseif (version_compare(_PS_VERSION_, '1.5', '<'))
        {
            $views = 'views/templates/';
            if (@filemtime(dirname(__FILE__).'/'.$name))
                return $this->display(__FILE__, $name);
            elseif (@filemtime(dirname(__FILE__).'/'.$views.'hook/'.$name))
                return $this->display(__FILE__, $views.'hook/'.$name);
            elseif (@filemtime(dirname(__FILE__).'/'.$views.'front/'.$name))
                return $this->display(__FILE__, $views.'front/'.$name);
            elseif (@filemtime(dirname(__FILE__).'/'.$views.'admin/'.$name))
                return $this->display(__FILE__, $views.'admin/'.$name);
        }

        return $this->display(__FILE__, $name);
    }

    public static function getLogs() {

        if (Tools::isSubmit('submitLogSearch') && Tools::getValue('searchInLog') != '')
        {
            $sql = '
                SELECT *
                FROM `'._DB_PREFIX_.'intrum_logs` as I
                WHERE I.firstname like \'%'.pSQL(Tools::getValue('searchInLog')).'%\'
                   OR I.lastname like \'%'.pSQL(Tools::getValue('searchInLog')).'%\'
                   OR I.request_id like \'%'.pSQL(Tools::getValue('searchInLog')).'%\'
                ORDER BY intrum_id DESC
                ';
            return Db::getInstance()->ExecuteS($sql);

        } else {
            return Db::getInstance()->ExecuteS('
                SELECT *
                FROM `'._DB_PREFIX_.'intrum_logs` as I
                ORDER BY intrum_id DESC
                LIMIT 20 ');
        }

    }

    public function hookOrderConfirmation($params)
    {
        $currency = Currency::getCurrencyInstance($params["order"]->id_currency);
        $request = CreatePrestaShopRequestAfterPaid($params["cart"], $params["order"], $currency);
        $xml = $request->createRequest();

        $intrumCommunicator = new IntrumCommunicator();
        $intrumCommunicator->setServer(Configuration::get("INTRUM_MODE"));
        $response = $intrumCommunicator->sendRequest($xml);
        libxml_use_internal_errors(true);
        $xmlResponse = simplexml_load_string($response);
        $intrumLogger = IntrumLogger::getInstance();
        if ($xmlResponse) {
            $intrumLogger->log(Array(
                "firstname" => $request->getFirstName(),
                "lastname" => $request->getLastName(),
                "town" => $request->getTown(),
                "postcode" => $request->getPostCode(),
                "street" => trim($request->getFirstLine().' '.$request->getHouseNumber()),
                "country" => $request->getCountryCode(),
                "ip" => $_SERVER["REMOTE_ADDR"],
                "status" => (isset($xmlResponse->Customer->RequestStatus)) ? 'OK' : '0',
                "request_id" => $request->getRequestId(),
                "type" => "Order confirmation message",
                "error" => (!(isset($xmlResponse->Customer->RequestStatus))) ? $response : "",
                "response" => $response,
                "request" => $xml
            ));
        } else {
            $intrumLogger->log(Array(
                "firstname" => $request->getFirstName(),
                "lastname" => $request->getLastName(),
                "town" => $request->getTown(),
                "postcode" => $request->getPostCode(),
                "street" => trim($request->getFirstLine().' '.$request->getHouseNumber()),
                "country" => $request->getCountryCode(),
                "ip" => $_SERVER["REMOTE_ADDR"],
                "status" => '0',
                "request_id" => $request->getRequestId(),
                "type" => "Order confirmation message",
                "error" => "",
                "response" => $response,
                "request" => $xml
            ));
        }
        return;
    }

    public static function searchLogs() {
    }

    public static function getPayment() {

        $modules_list = Module::getPaymentModules();
        foreach ($modules_list as $k => $paymod) {
            if (file_exists(_PS_MODULE_DIR_ . '/' . $paymod['name'] . '/' . $paymod['name'] . '.php')) {
                require_once(_PS_MODULE_DIR_ . '/' . $paymod['name'] . '/' . $paymod['name'] . '.php');
                $module = get_object_vars(Module::getInstanceByName($paymod['name']));
                $modules_list[$k]['displayName'] = $module['displayName'];
            }
        }
        return $modules_list;
    }

}