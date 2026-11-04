<?php

// Minimal IP-Symcon replacement so the module can be tested without a Symcon server.
// Only covers what the module actually uses.

class TestRegistry
{
    public static array $values = [];   // VariableID => value
    public static array $objects = [];  // ID => ['parent','ident','name','type']
    public static array $log = [];      // LogMessage
    public static int $nextID = 10000;
    public static int $runlevel = KR_READY;
    public static int $now = 1793811600;  // test clock (2026-11-04 18:00:00 +01:00, a Wednesday)
    public static $http = null;           // fake HTTP: fn (string $url, array $headers): array{status, body}
    public static array $calls = [];      // URLs requested through the fake HTTP

    public static function reset(): void
    {
        self::$values = [];
        self::$objects = [];
        self::$log = [];
        self::$nextID = 10000;
        self::$runlevel = KR_READY;
        self::$now = 1793811600;
        self::$http = null;
        self::$calls = [];
    }
}

// Values are arbitrary here; real Symcon provides them.
const KR_READY = 10103;
const IPS_KERNELSTARTED = 10001;
const VARIABLE_PRESENTATION_ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
const KL_MESSAGE = 10201;
const KL_WARNING = 10204;
const KL_ERROR = 10206;

class IPSModule
{
    public int $InstanceID;
    public array $properties = [];
    public array $attributes = [];
    public array $variables = [];     // Ident => VariableID
    public array $positions = [];     // Ident => position
    public array $presentations = []; // Ident => profile name or presentation array
    public array $actions = [];       // idents with EnableAction
    public array $timers = [];        // Name => ['script' => string, 'interval' => ms]
    public array $status = [];
    public array $visu = [];          // UpdateVisualizationValue payloads
    public array $formUpdates = [];   // [Field, Parameter, Value]
    public array $messages = [];      // [SenderID, Message]
    public int $visualizationType = 0;

    public function __construct($InstanceID)
    {
        $this->InstanceID = $InstanceID;
    }

    public function Create() {}
    public function ApplyChanges() {}
    public function RequestAction($Ident, $Value) {}
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {}
    protected function RegisterMessage($SenderID, $Message) { $this->messages[] = [$SenderID, $Message]; }

    protected function RegisterPropertyInteger($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function ReadPropertyInteger($Name) { return (int) $this->properties[$Name]; }
    protected function RegisterPropertyString($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function ReadPropertyString($Name) { return (string) $this->properties[$Name]; }
    protected function RegisterPropertyBoolean($Name, $Default) { $this->properties[$Name] = $Default; }
    protected function ReadPropertyBoolean($Name) { return (bool) $this->properties[$Name]; }
    protected function RegisterAttributeInteger($Name, $Default) { $this->attributes[$Name] ??= $Default; }
    protected function ReadAttributeInteger($Name) { return (int) $this->attributes[$Name]; }
    protected function WriteAttributeInteger($Name, $Value) { $this->attributes[$Name] = $Value; }
    protected function RegisterAttributeString($Name, $Default) { $this->attributes[$Name] ??= $Default; }
    protected function ReadAttributeString($Name) { return (string) $this->attributes[$Name]; }
    protected function WriteAttributeString($Name, $Value) { $this->attributes[$Name] = $Value; }
    protected function RegisterTimer($Name, $Interval, $Script) { $this->timers[$Name] = ['script' => $Script, 'interval' => $Interval]; }
    protected function SetTimerInterval($Name, $Interval) { $this->timers[$Name]['interval'] = $Interval; }
    protected function EnableAction($Ident) { $this->actions[] = $Ident; }

    protected function RegisterVariableString($Ident, $Name, $Profile = '', $Position = 0)
    {
        return $this->MaintainVariable($Ident, $Name, 3, $Profile, $Position, true) ? $this->variables[$Ident] : 0;
    }

    protected function RegisterVariableInteger($Ident, $Name, $Profile = '', $Position = 0)
    {
        return $this->MaintainVariable($Ident, $Name, 1, $Profile, $Position, true) ? $this->variables[$Ident] : 0;
    }

    protected function MaintainVariable($Ident, $Name, $Type, $Profile, $Position, $Keep)
    {
        $this->positions[$Ident] = $Position;
        $this->presentations[$Ident] = $Profile;
        if (!$Keep) {
            unset(TestRegistry::$objects[$this->variables[$Ident] ?? -1], $this->variables[$Ident]);
        } elseif (!isset($this->variables[$Ident])) {
            $id = TestRegistry::$nextID++;
            $this->variables[$Ident] = $id;
            TestRegistry::$values[$id] = [false, 0, 0.0, ''][$Type];
            TestRegistry::$objects[$id] = ['parent' => $this->InstanceID, 'ident' => $Ident, 'name' => $Name, 'type' => 2];
        }
        return true;
    }

    protected function GetIDForIdent($Ident)
    {
        if (!isset($this->variables[$Ident])) {
            trigger_error("Objekt mit Ident $Ident wurde nicht gefunden", E_USER_WARNING);  // as Symcon does
            return false;
        }
        return $this->variables[$Ident];
    }

    protected function GetValue($Ident) { return TestRegistry::$values[$this->variables[$Ident]]; }
    protected function SetValue($Ident, $Value) { TestRegistry::$values[$this->variables[$Ident]] = $Value; return true; }
    protected function SetStatus($Status) { $this->status[] = $Status; }
    protected function GetStatus() { return $this->currentStatus(); }
    public function currentStatus() { return end($this->status) ?: 102; }
    protected function LogMessage($Message, $Type) { TestRegistry::$log[] = $Message; }
    protected function Translate($Text) { return $Text; }
    protected function SendDebug($Message, $Data, $Format) {}
    protected function SetVisualizationType($Type) { $this->visualizationType = $Type; }
    protected function UpdateVisualizationValue($Value) { $this->visu[] = $Value; }
    protected function UpdateFormField($Field, $Parameter, $Value) { $this->formUpdates[] = [$Field, $Parameter, $Value]; }

    // test helper
    public function value($Ident) { return TestRegistry::$values[$this->variables[$Ident]]; }
}

function IPS_GetKernelRunlevel() { return TestRegistry::$runlevel; }
function IPS_GetName($ID) { return TestRegistry::$objects[$ID]['name'] ?? "Object #$ID"; }
