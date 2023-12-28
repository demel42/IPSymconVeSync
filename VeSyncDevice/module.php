<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class VeSyncDevice extends IPSModule
{
    use VeSync\StubsCommonLib;
    use VeSyncLocalLib;

    private function model2options($model)
    {
        $options['power'] = false;
        $options['work_mode'] = false;
        $options['work_mode01234'] = false;
        $options['work_mode0123'] = false;
        $options['speed_level'] = false;
        $options['speed_level123'] = false;
        $options['speed_level1234'] = false;
        $options['display_mode'] = false;
        $options['light_detection'] = false;
        $options['filter_lifetime'] = false;
        $options['air_quality'] = false;
        $options['air_quality_value'] = false;
        $options['pm25'] = false;
        $options['rssi'] = false;

        switch ($model) {
            case 'Vital100S':
                $options['power'] = true;
                $options['work_mode01234'] = true;
                $options['speed_level1234'] = true;
                $options['display_mode'] = true;
                $options['light_detection'] = true;
                $options['filter_lifetime'] = true;
                $options['air_quality'] = true;
                $options['pm25'] = true;
                $options['rssi'] = true;
                break;
            case 'Core300S':
                $options['power'] = true;
                $options['work_mode0123'] = true;
                $options['speed_level1234'] = true;
                $options['display_mode'] = true;
                $options['filter_lifetime'] = true;
                $options['air_quality'] = true;
                $options['air_quality_value'] = true;
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }
        if ($options['work_mode01234'] || $options['work_mode0123']) {
            $options['work_mode'] = true;
        }
        if ($options['speed_level1234'] || $options['speed_level123']) {
            $options['speed_level'] = true;
        }
        return $options;
    }

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyBoolean('log_no_parent', true);

        $this->RegisterPropertyString('cid', '');
        $this->RegisterPropertyString('deviceType', '');
        $this->RegisterPropertyString('configModule', '');

        $this->RegisterPropertyString('model', '');

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{0E57FA4B-B212-E8E7-715D-4F5BA30C7817}');

        $this->RegisterTimer('UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        parent::MessageSink($timeStamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('1.3')) {
            $r[] = $this->Translate('Adjusting variableprofile \'VeSync.AQLevel\'');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('1.3')) {
            if (IPS_VariableProfileExists('VeSync.AQLevel')) {
                IPS_DeleteVariableProfile('VeSync.AQLevel');
            }
            $this->InstallVarProfiles(false);
        }

        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $model = $this->ReadPropertyString('model');
        $options = $this->model2options($model);

        $this->SendDebug(__FUNCTION__, 'option=' . print_r($options, true), 0);

        $vpos = 1;

        $this->MaintainVariable('Power', $this->Translate('Power'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['power']);
        if ($options['power']) {
            $this->MaintainAction('Power', true);
        }

        if ($options['work_mode01234']) {
            $varprof = 'VeSync.WorkMode01234';
        } elseif ($options['work_mode0123']) {
            $varprof = 'VeSync.WorkMode0123';
        } else {
            $varprof = '';
        }
        $this->MaintainVariable('WorkMode', $this->Translate('Work mode'), VARIABLETYPE_INTEGER, $varprof, $vpos++, $options['work_mode']);
        if ($options['work_mode']) {
            $this->MaintainAction('WorkMode', true);
        }

        if ($options['speed_level1234']) {
            $varprof = 'VeSync.SpeedLevel1234';
        } elseif ($options['speed_level123']) {
            $varprof = 'VeSync.SpeedLevel123';
        } else {
            $varprof = '';
        }
        $this->MaintainVariable('SpeedLevel', $this->Translate('Speed level'), VARIABLETYPE_INTEGER, $varprof, $vpos++, $options['speed_level']);
        if ($options['speed_level']) {
            $this->MaintainAction('SpeedLevel', true);
        }

        $this->MaintainVariable('DisplayMode', $this->Translate('Display'), VARIABLETYPE_BOOLEAN, 'VeSync.OnOff', $vpos++, $options['display_mode']);
        if ($options['display_mode']) {
            $this->MaintainAction('DisplayMode', true);
        }

        $this->MaintainVariable('LightDetection', $this->Translate('Detection of ambient light'), VARIABLETYPE_BOOLEAN, 'VeSync.OnOff', $vpos++, $options['light_detection']);
        if ($options['light_detection']) {
            $this->MaintainAction('LightDetection', true);
        }

        $this->MaintainVariable('FilterLifetime', $this->Translate('Filter lifetime'), VARIABLETYPE_INTEGER, 'VeSync.Percent', $vpos++, $options['filter_lifetime']);

        $this->MaintainVariable('AirQuality', $this->Translate('Indoor air quality'), VARIABLETYPE_INTEGER, 'VeSync.AQLevel', $vpos++, $options['air_quality']);
        $this->MaintainVariable('AirQualityValue', $this->Translate('Indoor air quality value'), VARIABLETYPE_INTEGER, 'VeSync.AQValue', $vpos++, $options['air_quality_value']);
        $this->MaintainVariable('PM25', $this->Translate('Particulate matter (PM 2.5)'), VARIABLETYPE_INTEGER, 'VeSync.PM25', $vpos++, $options['pm25']);

        $this->MaintainVariable('WifiStrength', $this->Translate('Wifi signal strenght'), VARIABLETYPE_INTEGER, 'VeSync.Wifi', $vpos++, $options['rssi']);

        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $model = $this->ReadPropertyString('model');
        $this->SetSummary($model);

        $this->MaintainStatus(IS_ACTIVE);

        $this->AdjustActions();

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('VeSync Device');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Basic configuration',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'cid',
                    'width'   => '350px',
                    'caption' => 'cid',
                    'enabled' => false
                ],
                [
                    'type'    => 'RowLayout',
                    'items'   => [
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'model',
                            'width'   => '350px',
                            'caption' => 'Model',
                            'enabled' => false
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'deviceType',
                            'width'   => '350px',
                            'caption' => 'deviceType',
                            'enabled' => false
                        ],
                        [
                            'type'    => 'ValidationTextBox',
                            'name'    => 'configModule',
                            'width'   => '350px',
                            'caption' => 'configModule',
                            'enabled' => false
                        ],
                    ],
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'log_no_parent',
            'caption' => 'Generate message when the gateway is inactive',
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
            'caption' => 'Update status',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetUpdateInterval(int $sec = null)
    {
        if ($sec == '') {
            $sec = $this->ReadPropertyInteger('update_interval');
        }
        $this->MaintainTimer('UpdateStatus', $sec * 1000);
    }

    private function CallBypassV2(string $method, array $opts = null)
    {
        switch ($method) {
            case 'getPurifierStatus':
            case 'setSwitch':
            case 'setPurifierMode':
            case 'setLevel':
            case 'setDisplay':
            case 'setLightDetection':
                $data = $opts['data'];
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unknown method=' . $method, 0);
                return false;
        }

        $payload = [
            'method' => $method,
            'data'   => $data,
            'source' => 'APP',
        ];

        $cid = $this->ReadPropertyString('cid');
        $configModule = $this->ReadPropertyString('configModule');

        $sdata = [
            'DataID'           => '{DEC26699-97AD-BBF3-1764-2E443EC8E1C4}',
            'CallerID'         => $this->InstanceID,
            'Function'         => 'CallBypassV2',
            'cid'              => $cid,
            'configModule'     => $configModule,
            'payload'          => json_encode($payload),
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        return $jdata;
    }

    private function GetDeviceStatus()
    {
        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
            case 'Core300S':
                $jdata = $this->CallBypassV2('getPurifierStatus', ['data' => []]);
                break;
            default:
                $jdata = false;
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }
        return $jdata;
    }

    private function GetDeviceDetails()
    {
        $cid = $this->ReadPropertyString('cid');

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
            case 'Core300S':
                $sdata = [
                    'DataID'           => '{DEC26699-97AD-BBF3-1764-2E443EC8E1C4}',
                    'CallerID'         => $this->InstanceID,
                    'Function'         => 'GetDeviceDetails',
                    'cid'              => $cid,
                ];
                $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
                $data = $this->SendDataToParent(json_encode($sdata));
                $jdata = json_decode($data, true);
                $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
                break;
            default:
                $jdata = false;
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }

        return $jdata;
    }

    private function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return;
        }

        $now = time();
        $is_changed = false;
        $fnd = true;

        $model = $this->ReadPropertyString('model');
        $options = $this->model2options($model);
        $this->SendDebug(__FUNCTION__, 'option=' . print_r($options, true), 0);

        $jdata = $this->GetDeviceStatus();

        switch ($model) {
            case 'Vital100S':
                $keywords = [
                    'power'           => 'powerSwitch',
                    'work_mode'       => 'workMode',
                    'speed_level'     => 'manualSpeedLevel',
                    'display_mode'    => 'screenSwitch',
                    'light_detection' => 'lightDetectionSwitch',
                    'filter_lifetime' => 'filterLifePercent',
                    'air_quality'     => 'AQLevel',
                    'pm25'            => 'PM25',
                ];
                break;
            case 'Core300S':
                $keywords = [
                    'power'             => 'enabled',
                    'work_mode'         => 'mode',
                    'speed_level'       => 'level',
                    'display_mode'      => 'display',
                    'filter_lifetime'   => 'filter_life',
                    'air_quality'       => 'air_quality',
                    'air_quality_value' => 'air_quality_value',
                ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                return;
        }

        if ($options['power']) {
            $powerSwitch = (bool) $this->GetArrayElem($jdata, $keywords['power'], false, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... Power (' . $keywords['power'] . ')=' . $powerSwitch . ' => ' . $this->bool2str($powerSwitch), 0);
                $this->SaveValue('Power', $powerSwitch, $is_changed);
            }
        }

        if ($options['work_mode']) {
            $workMode = $this->GetArrayElem($jdata, $keywords['work_mode'], '', $fnd);
            if ($fnd) {
                $w = $this->DecodeWorkMode($workMode);
                $this->SendDebug(__FUNCTION__, '... WorkMode (' . $keywords['work_mode'] . ')=' . $workMode . ' => ' . $w, 0);
                $this->SaveValue('WorkMode', $w, $is_changed);
            }
        }

        if ($options['speed_level']) {
            $speedLevel = $this->GetArrayElem($jdata, $keywords['speed_level'], '', $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... SpeedLevel (' . $keywords['speed_level'] . ')=' . $speedLevel, 0);
                $this->SaveValue('SpeedLevel', $speedLevel, $is_changed);
            }
        }

        if ($options['display_mode']) {
            $screenSwitch = $this->GetArrayElem($jdata, $keywords['display_mode'], '', $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... DisplayMode (' . $keywords['display_mode'] . ')=' . $screenSwitch, 0);
                $this->SaveValue('DisplayMode', $screenSwitch, $is_changed);
            }
        }

        if ($options['light_detection']) {
            $lightDetectionSwitch = $this->GetArrayElem($jdata, $keywords['light_detection'], '', $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... SpeedLevel (' . $keywords['light_detection'] . ')=' . $lightDetectionSwitch, 0);
                $this->SaveValue('LightDetection', $lightDetectionSwitch, $is_changed);
            }
        }

        if ($options['filter_lifetime']) {
            $filterLifePercent = (int) $this->GetArrayElem($jdata, $keywords['filter_lifetime'], 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... FilterLifetime (' . $keywords['filter_lifetime'] . ')=' . $filterLifePercent, 0);
                $this->SaveValue('FilterLifetime', $filterLifePercent, $is_changed);
            }
        }

        if ($options['air_quality']) {
            $AQLevel = (int) $this->GetArrayElem($jdata, $keywords['air_quality'], 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... AirQuality (' . $keywords['air_quality'] . ')=' . $AQLevel, 0);
                $this->SaveValue('AirQuality', $AQLevel, $is_changed);
            }
        }

        if ($options['air_quality_value']) {
            $AQValue = (int) $this->GetArrayElem($jdata, $keywords['air_quality_value'], 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... AirQualityValue (' . $keywords['air_quality_value'] . ')=' . $AQValue, 0);
                $this->SaveValue('AirQualityValue', $AQValue, $is_changed);
            }
        }

        if ($options['pm25']) {
            $PM25 = (int) $this->GetArrayElem($jdata, $keywords['pm25'], 0, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... PM25 (' . $keywords['pm25'] . ')=' . $PM25, 0);
                $this->SaveValue('PM25', $PM25, $is_changed);
            }
        }

        $jdata = $this->GetDeviceDetails();

        if ($options['rssi']) {
            $rssi = (int) $this->GetArrayElem($jdata, 'deviceProp.wifiRssi', false, $fnd);
            if ($fnd) {
                $this->SendDebug(__FUNCTION__, '... wifi (wifiRssi)=' . $rssi, 0);
                $this->SaveValue('WifiStrength', $rssi, $is_changed);
            }
        }

        $this->SetValue('LastUpdate', $now);

        $this->AdjustActions();

        $this->SetUpdateInterval();
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateStatus':
                $this->UpdateStatus();
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
            case 'Power':
                $r = $this->SwitchPower((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'WorkMode':
                $r = $this->SetWorkMode((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'SpeedLevel':
                $r = $this->SetSpeedLevel((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'DisplayMode':
                $r = $this->SetDisplayMode((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'LightDetection':
                $r = $this->SetLightDetection((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function CheckAction($func, $verbose)
    {
        return true;
    }

    private function AdjustActions()
    {
        $chg = false;

        if ($chg) {
            $this->ReloadForm();
        }
    }

    private function SwitchPower(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $method = '';
        $opts = [];
        $r = false;

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
                $method = 'setSwitch';
                $opts = [
                    'data' => [
                        'powerSwitch' => (int) $mode,
                        'switchIdx'   => 0,
                    ],
                ];
                break;
            case 'Core300S':
                $method = 'setSwitch';
                $opts = [
                    'data' => [
                        'enabled' => $mode,
                        'id'      => 0,
                    ],
                ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }

        if ($method != '') {
            $jdata = $this->CallBypassV2($method, $opts);
            $r = $jdata != json_encode([]);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true) . ', r=' . $this->bool2str($r), 0);
        }

        return $r;
    }

    private function SetWorkMode(int $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $method = '';
        $opts = [];
        $r = false;

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
                $method = 'setPurifierMode';
                $opts = [
                    'data' => [
                        'workMode' => $this->EncodeWorkMode($mode),
                    ],
                ];
                break;
            case 'Core300S':
                if ($mode == self::$MODE_FAN_MANUAL) {
                    $method = 'setLevel';
                    $opts = [
                        'data' => [
                            'id'    => 0,
                            'level' => 1,
                            'type'  => 'wind',
                        ],
                    ];
                } else {
                    $method = 'setPurifierMode';
                    $opts = [
                        'data' => [
                            'mode' => $this->EncodeWorkMode($mode),
                        ],
                    ];
                }
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }

        if ($method != '') {
            $jdata = $this->CallBypassV2($method, $opts);
            $r = $jdata != json_encode([]);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true) . ', r=' . $this->bool2str($r), 0);
        }

        return $r;
    }

    private function SetSpeedLevel(int $level)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $method = '';
        $opts = [];
        $r = false;

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
                $method = 'setLevel';
                $opts = [
                    'data' => [
                        'levelIdx'         => 0,
                        'manualSpeedLevel' => $level,
                        'levelType'        => 'wind',
                    ],
                ];
                break;
            case 'Core300S':
                $method = 'setLevel';
                $opts = [
                    'data' => [
                        'id'    => 0,
                        'level' => $level,
                        'type'  => 'wind',
                    ],
                ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }

        if ($method != '') {
            $jdata = $this->CallBypassV2('setLevel', $opts);
            $r = $jdata != json_encode([]);
            if ($r) {
                $this->SetValue('WorkMode', self::$MODE_FAN_MANUAL);
            }
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true) . ', r=' . $this->bool2str($r), 0);
        }

        return $r;
    }

    private function SetDisplayMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $method = '';
        $opts = [];
        $r = false;

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
                $method = 'setDisplay';
                $opts = [
                    'data' => [
                        'screenSwitch' => (int) $mode,
                    ],
                ];
                break;
            case 'Core300S':
                $method = 'setDisplay';
                $opts = [
                    'data' => [
                        'state' => $mode,
                    ],
                ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }

        if ($method != '') {
            $jdata = $this->CallBypassV2($method, $opts);
            $r = $jdata != json_encode([]);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true) . ', r=' . $this->bool2str($r), 0);
        }

        return $r;
    }

    private function SetLightDetection(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $method = '';
        $opts = [];
        $r = false;

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
                $method = 'setLightDetection';
                $opts = [
                    'data' => [
                        'lightDetectionSwitch' => (int) $mode,
                    ],
                ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }

        if ($method != '') {
            $jdata = $this->CallBypassV2($method, $opts);
            $r = $jdata != json_encode([]);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true) . ', r=' . $this->bool2str($r), 0);
        }

        return $r;
    }
}
