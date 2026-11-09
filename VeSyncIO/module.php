<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class VeSyncIO extends IPSModule
{
    use VeSync\StubsCommonLib;
    use VeSyncLocalLib;

    private static $supportedCountries = ['DE', 'AT', 'CH'];

    private static $baseUrl_map = [
        'US' => 'https://smartapi.vesync.com',
        'EU' => 'https://smartapi.vesync.eu',
    ];

    private static $appID = 'eldodkfj';
    private static $appVersion = '5.6.60';

    private static $phoneBrand = 'pyvesync';
    private static $phoneOS = 'Android';

    private static $clientType = 'vesyncApp';

    private static $userAgent = 'okhttp/3.12.1';   // BYPASS_HEADER_UA aus const.py

    /*
    DE	Germany			Europe/Berlin		de
    CH	Switzerland		Europe/Zurich
    AT	Austria			Europe/Vienna
    NL	Netherlands		Europe/Amsterdam	nl
    BE	Belgium			Europe/Brussels
    LU	Luxembourg		Europe/Luxembourg
    FR	France			Europe/Paris		fr
    UK	United Kingdom	Europe/London		en
     */

    private static $timeZone = 'Europe/Berlin';
    private static $acceptLanguage = 'de';
    private static $deviceRegion = 'EU';

    private static $login_interval = 60 * 60;

    private static $semaphoreTM = 5 * 1000;

    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('region', 'EU');
        $this->RegisterPropertyString('country', 'DE');

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterAttributeString('AccessData', '');
        $this->RegisterAttributeInteger('TraceIdCounter', 0);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $username = $this->ReadPropertyString('username');
        if ($username == '') {
            $this->SendDebug(__FUNCTION__, '"username" is needed', 0);
            $r[] = $this->Translate('Username must be specified');
        }

        $password = $this->ReadPropertyString('password');
        if ($password == '') {
            $this->SendDebug(__FUNCTION__, '"password" is needed', 0);
            $r[] = $this->Translate('Password must be specified');
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1000;
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        $this->MaintainMedia('ApiCallStats', $this->Translate('API call statistics'), MEDIATYPE_DOCUMENT, '.txt', false, $vpos++, $collectApiCallStats);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('VeSync I/O');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Access data',
            'items'   => [
                [
                    'name'    => 'username',
                    'type'    => 'ValidationTextBox',
                    'caption' => 'User-ID (email)'
                ],
                [
                    'name'    => 'password',
                    'type'    => 'PasswordTextBox',
                    'caption' => 'Password'
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccount", "");',
        ];

        $items = [
            $this->GetInstallVarProfilesFormItem(),
            [
                'type'    => 'Button',
                'caption' => 'Clear token',
                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
            ],
        ];
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $items[] = $this->GetApiCallStatsFormItem();
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => $items,
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'ClearToken':
                $this->ClearToken();
                break;
            case 'TestAccount':
                $this->TestAccount();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function ClearToken()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $jaccess_data = @json_decode($this->ReadAttributeString('AccessData'), true);
        $token = $jaccess_data['token'] ?? '';

        $this->SendDebug(__FUNCTION__, 'clear token=' . $token, 0);
        $this->WriteAttributeString('AccessData', '');

        IPS_SemaphoreLeave($this->SemaphoreID);
    }

    protected function SendData($data)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode(['DataID' => '{36130503-6A92-BA52-AB31-6D24AFBAA9ED}', 'Buffer' => $data]));
    }

    public function ForwardData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $callerID = $jdata['CallerID'];
        $this->SendDebug(__FUNCTION__, 'caller=' . $callerID . '(' . IPS_GetName($callerID) . ')', 0);
        $_IPS['CallerID'] = $callerID;

        $ret = '';
        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetDevices':
                    $ret = $this->GetDevices();
                    break;
                case 'GetDeviceDetails':
                    $ret = $this->GetDeviceDetails($jdata['cid']);
                    break;
                case 'CallBypassV2':
                    $ret = $this->CallBypassV2($jdata['cid'], $jdata['configModule'], $jdata['payload']);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function build_url($url, $params)
    {
        $n = 0;
        if (is_array($params)) {
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
            }
        }
        return $url;
    }

    private function build_header($headerfields)
    {
        $header = [];
        foreach ($headerfields as $key => $value) {
            $header[] = $key . ': ' . $value;
        }
        return $header;
    }

    private function get_next_counter()
    {
        $counter = $this->ReadAttributeInteger('TraceIdCounter');
        $counter++;
        $this->WriteAttributeInteger('TraceIdCounter', $counter);
        return $counter;
    }

    private function base_postfields(string $method): array
    {
        $counter = $this->get_next_counter();
        $terminalId = '2' . md5((string) php_uname('n'));
        $traceId = 'APP' . substr($terminalId, -5, 4) . time() . '-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
        $clientVersion = 'VeSync ' . self::$appVersion;

        return [
            'method'         => $method,
            'acceptLanguage' => self::$acceptLanguage,
            'clientInfo'     => self::$phoneBrand,
            'clientType'     => self::$clientType,
            'clientVersion'  => $clientVersion,
            'debugMode'      => false,
            'osInfo'         => self::$phoneOS,
            'terminalId'     => $terminalId,
            'timeZone'       => self::$timeZone,
            'traceId'        => $traceId,
        ];
    }

    private function get_error_info(int|string|null $code, ?string $msg = null): array
    {
        static $error_map = [
            '-11260022'  => ['CROSS_REGION_ERROR', 'CROSS_REGION', 'Cross region error'],
            '11'         => ['DEVICE_OFFLINE', 'DEVICE_OFFLINE', 'Device offline'],
            '4041004'    => ['DEVICE_OFFLINE', 'DEVICE_OFFLINE', 'Device offline'],
            '-11203000'  => ['ACCOUNT_EXIST', 'AUTHENTICATION', 'Account already exists'],
            '-11200000'  => ['ACCOUNT_FORMAT_ERROR', 'CONFIG_ERROR', 'Account format error'],
            '-11202000'  => ['ACCOUNT_NOT_EXIST', 'AUTHENTICATION', 'Account does not exist'],
            '-11300027'  => ['AIRPURGE_OFFLINE', 'DEVICE_OFFLINE', 'Device offline'],
            '-16906000'  => ['REQUEST_TOO_FREQUENT', 'RATE_LIMIT', 'Request too frequent'],
            '-11902000'  => ['AUTHKEY_NOT_EXIST', 'CONFIG_ERROR', 'Authkey does not exist'],
            '-11900000'  => ['AUTHKEY_PID_NOT_MATCH', 'DEVICE_ERROR', 'Authkey PID mismatch'],
            '-11504000'  => ['AWAY_MAX', 'CONFIG_ERROR', 'Away maximum reached'],
            '11014000'   => ['BYPASS_AIRPURIFIER_E2', 'DEVICE_ERROR', 'Air Purifier E2 error'],
            '11802000'   => ['BYPASS_AIRPURIFIER_MOTOR_ABNORMAL', 'DEVICE_ERROR', 'Air Purifier motor error'],
            '11504000'   => ['BYPASS_AWAY_MAX', 'CONFIG_ERROR', 'Away maximum reached'],
            '11509000'   => ['BYPASS_AWAY_NOT_EXIST', 'CONFIG_ERROR', 'Away does not exist'],
            '11908000'   => ['BYPASS_COOK_TIMEOUT', 'DEVICE_ERROR', 'Cook timeout error'],
            '11909000'   => ['BYPASS_SMART_STOP', 'DEVICE_ERROR', 'Smart stop error'],
            '11910000'   => ['BYPASS_LEFT_ZONE_COOKING', 'DEVICE_ERROR', 'Left zone cooking error'],
            '11911000'   => ['BYPASS_RIGHT_ZONE_COOKING', 'DEVICE_ERROR', 'Right zone cooking error'],
            '11912000'   => ['BYPASS_ALL_ZONE_COOKING', 'DEVICE_ERROR', 'All zone cooking error'],
            '11916000'   => ['BYPASS_NTC_RIGHT_TOP_SHORT', 'DEVICE_ERROR', 'Right top short error'],
            '11917000'   => ['BYPASS_NTC_RIGHT_TOP_OPEN', 'DEVICE_ERROR', 'Right top open error'],
            '11918000'   => ['BYPASS_NTC_BOTTOM_SHORT', 'DEVICE_ERROR', 'Bottom short error'],
            '11919000'   => ['BYPASS_NTC_BOTTOM_OPEN', 'DEVICE_ERROR', 'Bottom open error'],
            '11924000'   => ['BYPASS_RIGHT_TEMP_FAULT', 'DEVICE_ERROR', 'Right temperature fault'],
            '11925000'   => ['BYPASS_ZONE_2_MOTOR_ABNORMAL', 'DEVICE_ERROR', 'Zone 2 motor error'],
            '11021000'   => ['BYPASS_DEVICE_END', 'DEVICE_ERROR', 'Device end error'],
            '11012000'   => ['BYPASS_DEVICE_RUNNING', 'DEVICE_ERROR', 'Device running error'],
            '11020000'   => ['BYPASS_DEVICE_STOP', 'DEVICE_ERROR', 'Device stop error'],
            '11901000'   => ['BYPASS_DOOR_OPEN', 'DEVICE_ERROR', 'Door open error'],
            '11006000'   => ['BYPASS_E1_OPEN', 'DEVICE_ERROR', 'Open circuit error'],
            '11007000'   => ['BYPASS_E2_SHORT', 'DEVICE_ERROR', 'Short circuit error'],
            '11015000'   => ['BYPASS_E3_WARM', 'DEVICE_ERROR', 'Warm error'],
            '11018000'   => ['BYPASS_SET_MIST_LEVEL', 'DEVICE_ERROR', 'Cannot set mist level error'],
            '11019000'   => ['BYPASS_E6_VOLTAGE_LOW', 'DEVICE_ERROR', 'Low voltage error'],
            '11013000'   => ['BYPASS_E7_VOLTAGE', 'DEVICE_ERROR', 'Voltage error'],
            '11607000'   => ['BYPASS_HUMIDIFIER_ERROR_CONNECT_MSG', 'DEVICE_ERROR', 'Humidifier connect message error'],
            '11317000'   => ['BYPASS_DIMMER_NCT', 'DEVICE_ERROR', 'Dimmer NCT error'],
            '11608000'   => ['BYPASS_HUMIDIFIER_ERROR_WATER_PUMP', 'DEVICE_ERROR', 'Humidifier water pump error'],
            '11609000'   => ['BYPASS_HUMIDIFIER_ERROR_FAN_MOTOR', 'DEVICE_ERROR', 'Humidifier fan motor error'],
            '11601000'   => ['BYPASS_HUMIDIFIER_ERROR_DRY_BURNING', 'DEVICE_ERROR', 'Dry burning error'],
            '11602000'   => ['BYPASS_HUMIDIFIER_ERROR_PTC', 'DEVICE_ERROR', 'Humidifier PTC error'],
            '11603000'   => ['BYPASS_HUMIDIFIER_ERROR_WARM_HIGH', 'DEVICE_ERROR', 'Humidifier warm high error'],
            '11604000'   => ['BYPASS_HUMIDIFIER_ERROR_WATER', 'DEVICE_ERROR', 'Humidifier water error'],
            '11907000'   => ['BYPASS_LOW_WATER', 'DEVICE_ERROR', 'Low water error'],
            '11028000'   => ['BYPASS_MOTOR_OPEN', 'DEVICE_ERROR', 'Motor open error'],
            '11017000'   => ['BYPASS_NOT_SUPPORTED', 'REQUEST_ERROR', 'Not supported error'],
            '11905000'   => ['BYPASS_NO_POT', 'DEVICE_ERROR', 'No pot error'],
            '12001000'   => ['BYPASS_LACK_FOOD', 'DEVICE_ERROR', 'Lack of food error'],
            '12002000'   => ['BYPASS_JAM_FOOD', 'DEVICE_ERROR', 'Jam food error'],
            '12003000'   => ['BYPASS_BLOCK_FOOD', 'DEVICE_ERROR', 'Block food error'],
            '12004000'   => ['BYPASS_PUMP_FAIL', 'DEVICE_ERROR', 'Pump failure error'],
            '12005000'   => ['BYPASS_CALI_FAIL', 'DEVICE_ERROR', 'Calibration failure error'],
            '11611000'   => ['BYPASS_FILTER_TRAY_ERROR', 'DEVICE_ERROR', 'Filter tray error'],
            '11610000'   => ['BYPASS_VALUE_ERROR', 'DEVICE_ERROR', 'Value error'],
            '11022000'   => ['BYPASS_CANNOT_SET_LEVEL', 'DEVICE_ERROR', 'Cannot set level error'],
            '11023000'   => ['BYPASS_NTC_BOTTOM_OPEN', 'DEVICE_ERROR', 'NTC bottom open error'],
            '11024000'   => ['BYPASS_NTC_BOTTOM_SHORT', 'DEVICE_ERROR', 'NTC bottom short error'],
            '11026000'   => ['BYPASS_NTC_TOP_OPEN', 'DEVICE_ERROR', 'NTC top open error'],
            '11025000'   => ['BYPASS_NTC_TOP_SHORT', 'DEVICE_ERROR', 'NTC top short error'],
            '11027000'   => ['BYPASS_OPEN_HEAT_PIPE_OR_OPEN_FUSE', 'DEVICE_ERROR', 'Open heat pipe or fuse error'],
            '11906000'   => ['BYPASS_OVER_HEATED', 'DEVICE_ERROR', 'Overheated error'],
            '11000000'   => ['BYPASS_PARAMETER_INVALID', 'REQUEST_ERROR', 'Invalid bypass parameter'],
            '11510000'   => ['BYPASS_SCHEDULE_CONFLICT', 'CONFIG_ERROR', 'Schedule conflict'],
            '11502000'   => ['BYPASS_SCHEDULE_MAX', 'CONFIG_ERROR', 'Maximum number of schedules reached'],
            '11507000'   => ['BYPASS_SCHEDULE_NOT_EXIST', 'CONFIG_ERROR', 'Schedule does not exist'],
            '11503000'   => ['TIMER_MAX', 'CONFIG_ERROR', 'Maximum number of timers reached'],
            '11508000'   => ['TIMER_NOT_EXIST', 'CONFIG_ERROR', 'Timer does not exist'],
            '11605000'   => ['BYPASS_WATER_LOCK', 'DEVICE_ERROR', 'Water lock error'],
            '11029000'   => ['BYPASS_WIFI_ERROR', 'DEVICE_ERROR', 'WiFi error'],
            '11902000'   => ['BY_PASS_ERROR_COOKING_158', 'DEVICE_ERROR', 'Error setting cook mode, air fryer is already cooking'],
            '11035000'   => ['BYPASS_MOTOR_ABNORMAL_ERROR', 'DEVICE_ERROR', 'Motor abnormal error'],
            '11903000'   => ['BY_PASS_ERROR_NOT_COOK_158', 'DEVICE_ERROR', 'Error pausing, air fryer is not cooking'],
            '-12001000'  => ['CONFIGKEY_EXPIRED', 'CONFIG_ERROR', 'Configkey expired'],
            '-12000000'  => ['CONFIGKEY_NOT_EXIST', 'CONFIG_ERROR', 'Configkey does not exist'],
            '-11305000'  => ['CONFIG_MODULE_NOT_EXIST', 'REQUEST_ERROR', 'Config module does not exist'],
            '-11100000'  => ['DATABASE_FAILED', 'SERVER_ERROR', 'Database error'],
            '-11101000'  => ['DATABASE_FAILED_ERROR', 'SERVER_ERROR', 'Database error'],
            '-11306000'  => ['DEVICE_BOUND', 'CONFIG_ERROR', 'Device already associated with another account'],
            '-11301000'  => ['DEVICE_NOT_EXIST', 'CONFIG_ERROR', 'Device does not exist'],
            '-11300000'  => ['DEVICE_OFFLINE', 'DEVICE_OFFLINE', 'Device offline'],
            '-11302000'  => ['DEVICE_TIMEOUT', 'DEVICE_ERROR', 'Device timeout'],
            '-11304000'  => ['DEVICE_TIMEZONE_DIFF', 'CONFIG_ERROR', 'Device timezone difference'],
            '-11303000'  => ['FIRMWARE_LATEST', 'CONFIG_ERROR', 'No firmware update available'],
            '-11102000'  => ['INTERNAL_ERROR', 'SERVER_ERROR', 'Internal server error'],
            '-11004000'  => ['METHOD_NOT_FOUND', 'REQUEST_ERROR', 'Method not found'],
            '-11107000'  => ['MONGODB_ERROR', 'SERVER_ERROR', 'MongoDB error'],
            '-11105000'  => ['MYSQL_ERROR', 'SERVER_ERROR', 'MySQL error'],
            '88888888'   => ['NETWORK_DISABLE', 'SERVER_ERROR', 'Network disabled'],
            '77777777'   => ['NETWORK_TIMEOUT', 'SERVER_ERROR', 'Network timeout'],
            '4031005'    => ['NO_PERMISSION_7A', 'DEVICE_ERROR', 'No 7A Permissions'],
            '-11201000'  => ['PASSWORD_ERROR', 'AUTHENTICATION', 'Invalid password'],
            '-11901000'  => ['PID_NOT_EXIST', 'DEVICE_ERROR', 'PID does not exist'],
            '-11106000'  => ['REDIS_ERROR', 'SERVER_ERROR', 'Redis error'],
            '-11003000'  => ['REQUEST_HIGH', 'RATE_LIMIT', 'Rate limiting error'],
            '-11005000'  => ['RESOURCE_NOT_EXIST', 'REQUEST_ERROR', 'No device with ID found'],
            '-11108000'  => ['S3_ERROR', 'SERVER_ERROR', 'S3 error'],
            '-11502000'  => ['SCHEDULE_MAX', 'CONFIG_ERROR', 'Maximum number of schedules reached'],
            '-11103000'  => ['SERVER_BUSY', 'SERVER_ERROR', 'Server busy'],
            '-11104000'  => ['SERVER_TIMEOUT', 'SERVER_ERROR', 'Server timeout'],
            '-11501000'  => ['TIMER_CONFLICT', 'DEVICE_ERROR', 'Timer conflict'],
            '-11503000'  => ['TIMER_MAX', 'DEVICE_ERROR', 'Maximum number of timers reached'],
            '-11500000'  => ['TIMER_NOT_EXIST', 'DEVICE_ERROR', 'Timer does not exist'],
            '-11001000'  => ['TOKEN_EXPIRED', 'TOKEN_ERROR', 'Invalid token'],
            '-999999999' => ['UNKNOWN', 'UNKNOWN_ERROR', 'Unknown error'],
            '-11307000'  => ['UUID_NOT_EXIST', 'DEVICE_ERROR', 'Device UUID not found'],
            '12102000'   => ['TEM_SENOR_ERROR', 'DEVICE_ERROR', 'Temperature sensor error'],
            '12103000'   => ['HUM_SENOR_ERROR', 'DEVICE_ERROR', 'Humidity sensor error'],
            '12101000'   => ['SENSOR_ERROR', 'DEVICE_ERROR', 'Sensor error'],
            '11005000'   => ['BYPASS_DEVICE_IS_OFF', 'DEVICE_ERROR', 'Device is offDevice is off'],
        ];
        if ($code === null || !is_numeric($code)) {
            $code = -999999999;
        }
        $int = (int) $code;
        if ($int === 0) {
            $info = ['SUCCESS', 'SUCCESS', 'Success'];
        } elseif (isset($error_map[(string) $int])) {
            $info = $error_map[(string) $int];
        } else {
            // Fallback wie im Original: auf Tausender abrunden (Richtung 0)
            $info = $error_map[(string) (intdiv($int, 1000) * 1000)] ?? ['UNKNOWN', 'UNKNOWN_ERROR', 'Unknown error'];
        }
        return [
            'code'    => $int,
            'name'    => $info[0],
            'type'    => $info[1],
            'message' => $info[2] . ($msg !== null ? " - $msg" : ''),
        ];
    }

    private function parse_error(array $response): array
    {
        if (array_key_exists('code', $response) && (string) $response['code'] !== '0') {
            return $this->get_error_info($response['code'], $response['msg'] ?? null);
        }
        $found = [];
        $walk = function ($node) use (&$walk, &$found): void
        {
            if (!is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (in_array($k, ['error', 'code', 'error_code', 'errorCode'], true) && is_int($v) && $v !== 0) {
                    $found[] = [$v, $node['msg'] ?? ($node['result']['msg'] ?? null)];
                }
                if (is_array($v)) {
                    $walk($v);
                }
            }
        };
        $walk($response);
        if ($found === []) {
            return $this->get_error_info(0);
        }
        [$c, $m] = end($found);
        return $this->get_error_info($c, $m);
    }

    private function do_HttpRequest(string $region, string $endpoint, array $postfields): array|false
    {
        $url = ($region === 'EU' ? self::$baseUrl_map['EU'] : self::$baseUrl_map['US']) . $endpoint;

        $headerfiels = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'User-Agent'   => self::$userAgent,
        ];
        $header = $this->build_header($headerfiels);

        $time_start = microtime(true);

        $mode = $postfields != [] ? 'post' : 'get';
        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ', url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);

        $postdata = json_encode($postfields, JSON_UNESCAPED_UNICODE);
        if ($postfields != []) {
            $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);
            $this->SendDebug(__FUNCTION__, '... postdata=' . $postdata, 0);
        }

        $curl_opts = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postdata,
            CURLOPT_HTTPHEADER     => $header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADER         => true,
            CURLINFO_HEADER_OUT    => true,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $curl_opts);
        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_info = curl_getinfo($ch);
        if (IPS_GetKernelVersion() < 8.5) {
            curl_close($ch);
        }

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => response=' . $response, 0);

        $statuscode = 0;
        $err = '';

        $jbody = false;

        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } else {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);

            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                $this->WriteAttributeString('AccessData', '');
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                $this->WriteAttributeString('AccessData', '');
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . ' (' . $this->HttpCode2Text($httpcode) . ')';
            }
        }

        if ($statuscode == 0) {
            $jbody = @json_decode($body, true);
            if ($jbody === false) {
                $this->SendDebug(__FUNCTION__, 'json_last_error_msg=' . json_last_error_msg(), 0);
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'invalid/malformed data';
            }
        }

        $info = $this->get_error_info($resp['code'] ?? null);
        if ($info['type'] === 'TOKEN_ERROR') {
            $message = $info['message'] . (isset($resp['msg']) ? ' (' . $resp['msg'] . ')' : '');
            $this->SendDebug(__FUNCTION__, 'token error - ' . $message, 0);
            // $statuscode = self::$IS_NOLOGIN;
            // $err = 'token error';
            $this->WriteAttributeString('AccessData', '');
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        return $jbody;
    }

    private function perform_login(): array|false
    {
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');
        $country = $this->ReadPropertyString('country');
        $region = $this->ReadPropertyString('region');

        // ---- Schritt 1: authorizeCode holen
        $postdata = $this->base_postfields('authByPWDOrOTM') + [
            'email'            => $username,
            'password'         => md5($password),
            'authProtocolType' => 'generic',
            'appID'            => self::$appID,
            'sourceAppID'      => self::$appID,
            'userCountryCode'  => 'US',   // Modell-Default im Original, nicht der Kontoländercode
            'accountID'        => '',
            'token'            => '',
        ];
        $resp = $this->do_HttpRequest($region, '/globalPlatform/api/accountAuth/v1/authByPWDOrOTM', $postdata);
        if ($resp === false) {
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            return false;
        }

        if (($resp['code'] ?? null) !== 0) {
            $info = $this->get_error_info($resp['code'] ?? null);
            $message = $info['message'] . (isset($resp['msg']) ? ' (' . $resp['msg'] . ')' : '');
            $this->SendDebug(__FUNCTION__, 'Authentication failed - ' . $message, 0);
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            return false;
        }
        $authCode = $resp['result']['authorizeCode'] ?? null;
        if (!is_string($authCode) || !isset($resp['result']['accountID'])) {
            $this->SendDebug(__FUNCTION__, 'Invalid authentication response format', 0);
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            return false;
        }

        // ---- Schritt 2: authorizeCode gegen Token tauschen (max. 2 Region-Wechsel)
        $bizToken = null;
        for ($attempt = 0; $attempt <= 2; $attempt++) {
            $postdata = $this->base_postfields('loginByAuthorizeCode4Vesync') + [
                'authorizeCode'      => $authCode,
                'emailSubscriptions' => false,
                'userCountryCode'    => $country,
                'accountID'          => '',
                'token'              => '',
            ];
            if ($bizToken !== null) {
                $postdata['bizToken'] = $bizToken;
                $postdata['regionChange'] = 'lastRegion';
            }
            $resp = $this->do_HttpRequest($region, '/user/api/accountManage/v1/loginByAuthorizeCode4Vesync', $postdata);
            if ($resp === false) {
                $this->MaintainStatus(self::$IS_UNAUTHORIZED);
                return false;
            }

            $result = $resp['result'] ?? null;
            if (!is_array($result)) {
                $this->SendDebug(__FUNCTION__, 'Invalid login response format', 0);
                $this->MaintainStatus(self::$IS_UNAUTHORIZED);
                return false;
            }

            if (($resp['code'] ?? null) === 0) {
                if (!isset($result['token'], $result['accountID'], $result['countryCode'])) {
                    $this->SendDebug(__FUNCTION__, 'Error parsing login response', 0);
                    $this->MaintainStatus(self::$IS_UNAUTHORIZED);
                    return false;
                }
                return [
                    'token'          => (string) $result['token'],
                    'account_id'     => (string) $result['accountID'],
                    'country_code'   => strtoupper((string) $result['countryCode']),
                    'current_region' => $region,
                ];
            }

            $info = $this->get_error_info($resp['code'] ?? null);
            if ($info['type'] === 'CROSS_REGION') {
                if (!isset($result['countryCode'], $result['currentRegion'], $result['bizToken'])) {
                    $this->SendDebug(__FUNCTION__, 'Error parsing login response', 0);
                    $this->MaintainStatus(self::$IS_UNAUTHORIZED);
                    return false;
                }
                $country = strtoupper((string) $result['countryCode']);
                $region = (string) $result['currentRegion'];
                $bizToken = (string) $result['bizToken'];
                continue;
            }

            $message = $info['message'] . (isset($resp['msg']) ? ' (' . $resp['msg'] . ')' : '');
            $this->SendDebug(__FUNCTION__, 'Login failed - ' . $message, 0);
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'Login failed - too many region changes', 0);
        $this->MaintainStatus(self::$IS_UNAUTHORIZED);
        return false;
    }

    private function fetch_token(): array|false
    {
        $jdata = $this->perform_login();
        if ($jdata == false) {
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        $token = $jdata['token'] ?? '';
        $account_id = $jdata['account_id'] ?? '';
        $country_code = $jdata['country_code'] ?? '';
        $current_region = $jdata['current_region'] ?? '';
        $expireѕ = time() + self::$login_interval;
        $this->SendDebug(__FUNCTION__, 'new token=' . $token . ', valid until ' . date('d.m.y H:i:s', $expireѕ), 0);
        $jaccess_data = [
            'token'          => $token,
            'account_id'     => $account_id,
            'country_code'   => $country_code,
            'current_region' => $current_region,
            'expireѕ'        => $expireѕ,
        ];
        $this->WriteAttributeString('AccessData', json_encode($jaccess_data));
        return $jaccess_data;
    }

    private function GetAccessData()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $access_data = $this->ReadAttributeString('AccessData');
        if ($access_data != '') {
            $jaccess_data = @json_decode($access_data, true);
            $token = $jaccess_data['token'] ?? '';
            $expireѕ = $jaccess_data['expireѕ'] ?? 0;
            if ($expireѕ < time()) {
                $this->SendDebug(__FUNCTION__, 'token expired', 0);
                $token = '';
            }
            if ($token != '') {
                $this->SendDebug(__FUNCTION__, 'token=' . $token . ', valid until ' . date('d.m.y H:i:s', $expireѕ), 0);
                IPS_SemaphoreLeave($this->SemaphoreID);
                return $jaccess_data;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved token', 0);
        }

        $jaccess_data = $this->fetch_token();

        IPS_SemaphoreLeave($this->SemaphoreID);
        return $jaccess_data;
    }

    private function TestAccount()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText() . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $access_data = $this->GetAccessData();
        if ($access_data == false) {
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            $msg = $this->Translate('Invalid login-data at VeSync Cloud') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $r = $this->GetDevices();
        if ($r == false) {
            $msg = $this->Translate('Unable to get device list') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $msg = $this->Translate('valid account-data') . PHP_EOL;
        $msg .= PHP_EOL;

        $devices = json_decode($r, true);
        if (is_array($devices)) {
            foreach ($devices as $device) {
                $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                $deviceName = $this->GetArrayElem($device, 'deviceName', '');
                $deviceType = $this->GetArrayElem($device, 'deviceType', 0);
                $msg .= ' - ' . $deviceName . ' (' . $deviceType . ')' . PHP_EOL;
            }
        }
        $this->PopupMessage($msg);
    }

    private function GetDevices()
    {
        $access_data = $this->GetAccessData();
        if ($access_data == false) {
            return false;
        }

        $pageSize = 100;
        $devices = [];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        for ($pageNo = 1; $pageNo <= 20; $pageNo++) {   // Obergrenze als Schutz vor Endlosschleife
            $postdata = [
                'acceptLanguage' => self::$acceptLanguage,
                'timeZone'       => self::$timeZone,
                'traceId'        => (string) time(),
                'appVersion'     => self::$appVersion,
                'phoneBrand'     => self::$phoneBrand,
                'phoneOS'        => self::$phoneOS,
                'token'          => $access_data['token'],
                'accountID'      => $access_data['account_id'],
                'method'         => 'devices',
                'pageNo'         => $pageNo,
                'pageSize'       => $pageSize,
            ];

            $resp = $this->do_HttpRequest($access_data['current_region'], '/cloud/v1/deviceManaged/devices', $postdata);
            if ($resp === false) {
                return false;
            }

            if (($resp['code'] ?? null) !== 0) {
                $info = $this->get_error_info($resp['code'] ?? null, $resp['msg'] ?? null);
                $message = $info['message'] . (isset($resp['msg']) ? ' (' . $resp['msg'] . ')' : '');
                if ($info['type'] === 'TOKEN_ERROR') {
                    $access_data = $this->fetch_token();
                    if ($access_data == false) {
                        if ($this->GetStatus() == IS_ACTIVE) {
                            $this->MaintainStatus(self::$IS_NOLOGIN);
                        }
                        return false;
                    }
                    $pageNo--;
                    continue;
                }
                $this->SendDebug(__FUNCTION__, 'Error receiving response to device list request - ' . $message, 0);
                $this->MaintainStatus(self::$IS_INVALIDDATA);
                return false;
            }

            $result = $resp['result'] ?? null;
            if (!is_array($result) || !isset($result['list']) || !is_array($result['list'])) {
                $this->SendDebug(__FUNCTION__, 'Invalid device list response format', 0);
                $this->MaintainStatus(self::$IS_INVALIDDATA);
            }

            foreach ($result['list'] as $dev) {
                if (is_array($dev)) {
                    $dev['deviceStatus'] = $dev['deviceStatus'] ?? 'off';
                    $dev['connectionStatus'] = $dev['connectionStatus'] ?? 'offline';
                    $dev['macID'] = $dev['macID'] ?? '';

                    if (isset($dev['deviceProp']) && is_array($dev['deviceProp'])) {
                        $prop = $dev['deviceProp'];
                        if (isset($prop['powerSwitch'])) {
                            $dev['deviceStatus'] = ((int) $prop['powerSwitch'] === 1) ? 'on' : 'off';
                        }
                        if (isset($prop['connectionStatus'])) {
                            $dev['connectionStatus'] = $prop['connectionStatus'];
                        }
                        if (isset($prop['wifiMac'])) {
                            $dev['macID'] = $prop['wifiMac'];
                        }
                    }
                    if (!isset($dev['cid']) || $dev['cid'] === '') {
                        $dev['cid'] = $dev['uuid'] ?? $dev['macID'];
                    }
                    $devices[] = $dev;
                }
            }
            $total = (int) ($result['total'] ?? count($devices));
            if (count($result['list']) < $pageSize || count($devices) >= $total) {
                break;
            }
        }
        IPS_SemaphoreLeave($this->SemaphoreID);

        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);
        return json_encode($devices);
    }

    private function GetDeviceDetails($cid)
    {
        $this->SendDebug(__FUNCTION__, 'cid=' . $cid, 0);
        $devices = $this->GetDevices();
        if ($devices == false) {
            return false;
        }
        $devices = json_decode($devices, true);
        foreach ($devices as $device) {
            if ($device['cid'] == $cid) {
                $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                return json_encode($device);
            }
        }
        return false;
    }

    private function CallBypassV2($cid, $configModule, $payload)
    {
        $this->SendDebug(__FUNCTION__, 'cid=' . $cid . ', configModule=' . $configModule . ', payload=' . print_r($payload, true), 0);
        $access_data = $this->GetAccessData();
        if ($access_data == false) {
            return false;
        }

        $jpayload = json_decode($payload, true);
        $postfields = [
            'acceptLanguage'  => self::$acceptLanguage,
            'timeZone'        => self::$timeZone,
            'traceId'         => (string) time(),
            'method'          => 'bypassV2',
            'appVersion'      => self::$appVersion,
            'phoneBrand'      => self::$phoneBrand,
            'phoneOS'         => self::$phoneOS,
            'debugMode'       => false,
            'token'           => $access_data['token'],
            'accountID'       => $access_data['account_id'],
            'userCountryCode' => $access_data['country_code'],
            'method'          => 'bypassV2',
            'cid'             => $cid,
            'deviceId'        => $cid,
            'configModule'    => $configModule,
            'configModel'     => $configModule,
            'payload'         => [
                'method' => $jpayload['method'],
                'data'   => $jpayload['data'] === [] ? new stdClass() : $jpayload['data'],
                'source' => $jpayload['source'],
            ],
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($access_data['current_region'], '/cloud/v2/deviceManaged/bypassV2', $postfields);
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $result = (array) $this->GetArrayElem($jdata, 'result.result', []);
        $this->SendDebug(__FUNCTION__, 'result=' . print_r($result, true), 0);
        return json_encode($result);
    }
}
