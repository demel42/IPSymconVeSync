<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class VeSyncIO extends IPSModule
{
    use VeSync\StubsCommonLib;
    use VeSyncLocalLib;

    private static $base_url = 'https://smartapi.vesync.com';

    private static $login_endpoint = '/cloud/v1/user/login';

    private static $deviceList_endpoint = '/cloud/v2/deviceManaged/devices';

    private static $bypassV2_endpoint = '/cloud/v2/deviceManaged/bypassV2';

    private static $appVersion = '2.8.6';

    private static $phoneBrand = 'SM N9005';
    private static $phoneOS = 'Android';

    private static $userType = '1';

    private static $user_agent = 'symcon-vesync';

    private static $timeZone = 'Europe/Berlin';
    private static $acceptLanguage = 'de';
    private static $deviceRegion = 'EU';

    private static $login_interval = 60 * 60;

    private static $semaphoreTM = 5 * 1000;

    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
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

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ApiCallStats', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->RegisterAttributeString('AccessData', '');

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

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
                [
                    'type'    => 'Button',
                    'caption' => 'Clear token',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                ],
                $this->GetApiCallStatsFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    $this->GetApiCallStatsFormItem(),
                ],
            ]
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

        $jaccess_data = json_decode($this->ReadAttributeString('AccessData'), true);
        $token = isset($jaccess_data['token']) ? $jaccess_data['token'] : '';
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

    private function do_HttpRequest($endpoint, $postfields, $params, $header_add)
    {
        $url = self::$base_url . $endpoint;

        if ($params != '') {
            $this->SendDebug(__FUNCTION__, 'params=' . print_r($params, true), 0);
            $n = 0;
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
            }
        }

        $header_base = [
            'accept'          => 'application/json; charset=utf-8',
            'accept-language' => self::$acceptLanguage,
            'content-type'    => 'application/json; charset=utf-8',
            'user-agent'      => self::$user_agent,
            'connection'      => 'keep-alive',
        ];
        if ($header_add != '') {
            foreach ($header_add as $key => $val) {
                $header_base[$key] = $val;
            }
        }
        $header = [];
        foreach ($header_base as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $mode = $postfields != '' ? 'post' : 'get';
        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ', url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        if ($postfields != '') {
            $this->SendDebug(__FUNCTION__, '... postfields=' . print_r($postfields, true), 0);
            $this->SendDebug(__FUNCTION__, '... postdata=' . json_encode($postfields, JSON_FORCE_OBJECT), 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if ($postfields != '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields, JSON_FORCE_OBJECT));
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                $this->WriteAttributeString('AccessData', '');
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
                $this->WriteAttributeString('AccessData', '');
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            if ($cdata == '' || ctype_print($cdata)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $cdata, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($cdata), 0);
            }
        }
        if ($statuscode == 0) {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }
        if ($statuscode == 0) {
            $code = $this->GetArrayElem($jdata, 'code', 0);
            if ($code != 0) {
                $msg = $this->GetArrayElem($jdata, 'msg', '');
                $this->SendDebug(__FUNCTION__, 'code=' . $code . ', msg=' . $msg, 0);
                if ($msg == 'the token has expired') {
                    $statuscode = self::$IS_UNAUTHORIZED;
                } else {
                    $statuscode = self::$IS_INVALIDDATA;
                }
                $this->WriteAttributeString('AccessData', '');
            }
        }

        $this->ApiCallsCollect($url, $err, $statuscode);

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        return $jdata;
    }

    private function GetAccessData()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $access_data = $this->ReadAttributeString('AccessData');
        if ($access_data != '') {
            $jaccess_data = json_decode($access_data, true);
            $token = isset($jaccess_data['token']) ? $jaccess_data['token'] : '';
            $expireѕ = isset($jaccess_data['expireѕ']) ? $jaccess_data['expireѕ'] : 0;
            if ($expireѕ < time()) {
                $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
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

        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $postfields = [
            'appVersion'     => self::$appVersion,
            'phoneBrand'     => self::$phoneBrand,
            'phoneOS'        => self::$phoneOS,
            'userType'       => self::$userType,
            'timeZone'       => self::$timeZone,
            'acceptLanguage' => self::$acceptLanguage,
            'devToken'       => '',
            'token'          => '',
            'traceId'        => time(),
            'method'         => 'login',
            'email'          => $username,
            'password'       => md5($password),
        ];

        $jdata = $this->do_HttpRequest(self::$login_endpoint, $postfields, '', '');
        if ($jdata == false) {
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        $token = $this->GetArrayElem($jdata, 'result.token', '');
        $accountID = $this->GetArrayElem($jdata, 'result.accountID', '');
        $expireѕ = time() + self::$login_interval;
        $this->SendDebug(__FUNCTION__, 'new token=' . $token . ', valid until ' . date('d.m.y H:i:s', $expireѕ), 0);
        $jaccess_data = [
            'token'     => $token,
            'accountID' => $accountID,
            'expireѕ'   => $expireѕ,
        ];
        $this->WriteAttributeString('AccessData', json_encode($jaccess_data));
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

        $url = self::$deviceList_endpoint;

        $header_add = [
            'tz'        => self::$timeZone,
            'tk'        => $access_data['token'],
            'accountId' => $access_data['accountID'],
        ];

        $postfields = [
            'appVersion'     => self::$appVersion,
            'phoneBrand'     => self::$phoneBrand,
            'phoneOS'        => self::$phoneOS,
            'timeZone'       => self::$timeZone,
            'acceptLanguage' => self::$acceptLanguage,
            'traceId'        => time(),
            'token'          => $access_data['token'],
            'accountID'      => $access_data['accountID'],
            'method'         => 'devices',
            'pageNo'         => 1,
            'pageSize'       => 100,
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($url, $postfields, '', $header_add);
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $devices = $this->GetArrayElem($jdata, 'result.list', '');
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
        $access_data = $this->GetAccessData();
        if ($access_data == false) {
            return false;
        }

        $url = self::$bypassV2_endpoint;

        $postfields = [
            'appVersion'     => self::$appVersion,
            'phoneBrand'     => self::$phoneBrand,
            'phoneOS'        => self::$phoneOS,
            'timeZone'       => self::$timeZone,
            'acceptLanguage' => self::$acceptLanguage,
            'traceId'        => time(),
            'token'          => $access_data['token'],
            'accountID'      => $access_data['accountID'],
            'cid'            => $cid,
            'configModule'   => $configModule,
            'deviceRegion'   => self::$deviceRegion,
            'method'         => 'bypassV2',
            'payload'        => json_decode($payload),
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($url, $postfields, '', '');
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $result = (array) $this->GetArrayElem($jdata, 'result.result', []);
        $this->SendDebug(__FUNCTION__, 'result=' . print_r($result, true), 0);
        return json_encode($result);
    }
}
