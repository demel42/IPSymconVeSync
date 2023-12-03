<?php

declare(strict_types=1);

trait VeSyncLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;
    public static $IS_NOLOGIN = IS_EBASE + 14;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $this->CreateVarProfile('VeSync.Wifi', VARIABLETYPE_INTEGER, ' dBm', 0, 0, 0, 0, 'Intensity', '', $reInstall);
    }

    private function DeviceType2Model($deviceType)
    {
        $map = [
            'Core200S'   => ['Core200S', 'LAP-C201S-AUSR', 'LAP-C202S-WUSR'],
            'Core300S'   => ['Core300S', 'LAP-C301S-WJP', 'LAP-C302S-WUSB'],
            'Core400S'   => ['Core400S', 'LAP-C401S-WJP', 'LAP-C401S-WAAA', 'LAP-C401S-WUSR'],
            'Core600S'   => ['Core600S', 'LAP-C601S-WEU', 'LAP-C601S-WUS', 'LAP-C601S-WUSR'],
            // 'LV-PUR131S' => ['LV-PUR131S', 'LV-RH131S'],
            'Vital100S'  => ['LAP-V102S-AASR', 'LAP-V102S-AUSR', 'LAP-V102S-WEU', 'LAP-V102S-WJP', 'LAP-V102S-WUS'],
            'Vital200S'  => ['LAP-V201-AUSR', 'LAP-V201S-AASR', 'LAP-V201S-WEU', 'LAP-V201S-WJP', 'LAP-V201S-WUS'],
        ];

        foreach ($map as $model => $types) {
            if (in_array($deviceType, $types)) {
                return $model;
            }
        }

        $this->SendDebug(__FUNCTION__, 'unknown model "' . $model . '"', 0);
        return false;
    }
}
