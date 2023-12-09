<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class VeSyncConfig extends IPSModule
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

        if (IPS_GetKernelVersion() < 7.0) {
            $this->RegisterPropertyInteger('ImportCategoryID', 0);
        }

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));
        $this->RegisterAttributeString('DataCache', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{0E57FA4B-B212-E8E7-715D-4F5BA30C7817}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = [];
        if (IPS_GetKernelVersion() < 7.0) {
            $propertyNames[] = 'ImportCategoryID';
        }
        $this->MaintainReferences($propertyNames);

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

        $this->SetupDataCache(24 * 60 * 60);

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function getConfiguratorValues()
    {
        $entries = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $entries;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            return $entries;
        }

        if (IPS_GetKernelVersion() < 7.0) {
            $catID = $this->ReadPropertyInteger('ImportCategoryID');
            $location = $this->GetConfiguratorLocation($catID);
        } else {
            $location = '';
        }

        $dataCache = $this->ReadDataCache();
        if (isset($dataCache['data']['devices'])) {
            $devices = $dataCache['data']['devices'];
            $this->SendDebug(__FUNCTION__, 'devices (from cache)=' . print_r($devices, true), 0);
        } else {
            $sdata = [
                'DataID'   => '{DEC26699-97AD-BBF3-1764-2E443EC8E1C4}', // an VeSyncIO
                'CallerID' => $this->InstanceID,
                'Function' => 'GetDevices'
            ];
            $data = $this->SendDataToParent(json_encode($sdata));
            $devices = @json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);
            if (is_array($devices)) {
                $dataCache['data']['devices'] = $devices;
            }
            $this->WriteDataCache($dataCache, time());
        }

        $guid = '{BA4E3595-F713-49CD-D25D-53813601D88E}'; // VeSyncDevice
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($devices)) {
            foreach ($devices as $device) {
                $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                $deviceName = $this->GetArrayElem($device, 'deviceName', '');
                $deviceType = $this->GetArrayElem($device, 'deviceType', '');
                $cid = $this->GetArrayElem($device, 'cid', '');
                $configModule = $this->GetArrayElem($device, 'configModule', '');

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'cid') == $cid) {
                        $this->SendDebug(__FUNCTION__, 'instance found: ' . IPS_GetName($instID) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                if ($instanceID && IPS_GetInstance($instanceID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                    continue;
                }

                $model = $this->DeviceType2Model($deviceType);

                $entry = [
                    'instanceID'  => $instanceID,
                    'name'        => $deviceName,
                    'cid'         => $cid,
                    'type'        => $deviceType,
                    'model'       => $model,
                    'create'      => [
                        'moduleID'      => $guid,
                        'location'      => $location,
                        'info'          => $deviceType,
                        'configuration' => [
                            'cid'          => $cid,
                            'deviceType'   => $deviceType,
                            'configModule' => $configModule,
                            'model'        => $model,
                        ],
                    ],
                ];

                $entries[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
            }
        }
        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($entries as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            if (IPS_GetInstance($instID)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                continue;
            }

            $name = IPS_GetName($instID);
            $cid = IPS_GetProperty($instID, 'cid');
            $configModule = IPS_GetProperty($instID, 'configModule');
            $deviceType = IPS_GetProperty($instID, 'deviceType');
            $model = IPS_GetProperty($instID, 'model');

            $entry = [
                'instanceID'  => $instID,
                'name'        => $name,
                'cid'         => $cid,
                'type'        => $deviceType,
                'model'       => $model,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('VeSync Configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        if (IPS_GetKernelVersion() < 7.0) {
            $formElements[] = [
                'type'    => 'SelectCategory',
                'name'    => 'ImportCategoryID',
                'caption' => 'category for devices to be created'
            ];
        }

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'     => 'Configurator',
            'name'     => 'devices',
            'caption'  => 'Devices',
            'rowCount' => count($entries),
            'add'      => false,
            'delete'   => false,
            'columns'  => [
                [
                    'caption' => 'Name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Model',
                    'name'    => 'model',
                    'width'   => '200px'
                ],
                [
                    'caption' => 'Device type',
                    'name'    => 'type',
                    'width'   => '250px'
                ],
                [
                    'caption' => 'Device ID',
                    'name'    => 'cid',
                    'width'   => '350px'
                ],
            ],
            'values'            => $entries,
            'discoveryInterval' => 60 * 60 * 24,
        ];
        $formElements[] = $this->GetRefreshDataCacheFormAction();

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

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
