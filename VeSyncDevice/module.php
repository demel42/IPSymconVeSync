<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class VeSyncDevice extends IPSModule
{
    use VeSync\StubsCommonLib;
    use VeSyncLocalLib;

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

    private function model2options($model)
    {
        $options['rssi'] = false;
        $options['power'] = false;

        switch ($model) {
            case 'Vital100S':
                $options['power'] = true;
                $options['rssi'] = true;
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }
        return $options;
    }

    private function CallBypassV2(string $method, array $opts = null)
    {
        switch ($method) {
            case 'getPurifierStatus':
                $data = [];
                break;
            case 'setSwitch':
                $data = [
                    'powerSwitch'    => $opts['value'],
                    'switchIdx'      => 0,
                ];
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
                $jdata = $this->CallBypassV2('getPurifierStatus');
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

        $jdata = $this->GetDeviceStatus();

        $powerSwitch = (bool) $this->GetArrayElem($jdata, 'powerSwitch', false);
        $this->SaveValue('Power', $powerSwitch, $is_changed);

        $jdata = $this->GetDeviceDetails();

        $rssi = (int) $this->GetArrayElem($jdata, 'deviceProp.wifiRssi', false, $fnd);
        $this->SaveValue('WifiStrength', $rssi, $is_changed);

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

        $model = $this->ReadPropertyString('model');
        switch ($model) {
            case 'Vital100S':
                $opts = [
                    'value' => (int) $mode,
                ];
                $jdata = $this->CallBypassV2('setSwitch', $opts);
                $r = $jdata != json_encode([]);
                $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true) . ', r=' . $this->bool2str($r), 0);
                break;
            default:
                $r = false;
                $this->SendDebug(__FUNCTION__, 'unsupported model ' . $model, 0);
                break;
        }
        return $r;
    }
}

/*
03.12.2023, 11:41:48 |         CallBypassV2 | jdata=Array
(
    [powerSwitch] => 0
    [filterLifePercent] => 100
    [workMode] => manual
    [manualSpeedLevel] => 1
    [fanSpeedLevel] => 255
    [AQLevel] => 1
    [PM25] => 1
    [screenState] => 0
    [childLockSwitch] => 0
    [screenSwitch] => 1
    [lightDetectionSwitch] => 1
    [environmentLightState] => 0
    [autoPreference] => Array
        (
            [autoPreferenceType] => default
            [roomSize] => 0
        )

    [scheduleCount] => 0
    [timerRemain] => 0
    [efficientModeTimeRemain] => 0
    [sleepPreference] => Array
        (
            [sleepPreferenceType] => default
            [cleaningBeforeBedSwitch] => 1
            [cleaningBeforeBedSpeedLevel] => 3
            [cleaningBeforeBedMinutes] => 5
            [whiteNoiseSleepAidSwitch] => 1
            [whiteNoiseSleepAidSpeedLevel] => 1
            [whiteNoiseSleepAidMinutes] => 45
            [duringSleepSpeedLevel] => 5
            [duringSleepMinutes] => 480
            [afterWakeUpPowerSwitch] => 1
            [afterWakeUpWorkMode] => auto
            [afterWakeUpFanSpeedLevel] => 1
        )

    [errorCode] => 0
)

 */
