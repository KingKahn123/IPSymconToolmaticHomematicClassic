<?

define("IPS_BASE", 10000);
define("IPS_VARIABLEMESSAGE", IPS_BASE + 600);
define("VM_UPDATE", IPS_VARIABLEMESSAGE + 3);

class LightAutomat extends IPSModule
{
  
  public function Create()
  {
    //Never delete this line!
    parent::Create();
    
    $this->RegisterPropertyInteger("StateVariable", 0);
    $this->RegisterPropertyInteger("Duration", 10);
    $this->RegisterPropertyInteger("MotionVariable1", 0);
    $this->RegisterPropertyInteger("MotionVariable2", 0);
    $this->RegisterPropertyInteger("PermanentVariable", 0);
    $this->RegisterPropertyBoolean("ExecScript", false);
    $this->RegisterPropertyInteger("ScriptVariable", 0);
    $this->RegisterPropertyBoolean("OnlyBool", false);
    $this->RegisterPropertyBoolean("OnlyScript", false);
    $this->RegisterTimer("TriggerTimer",0,"TLA_Trigger(\$_IPS['TARGET']);");
  }

  public function ApplyChanges()
  {
    if($this->ReadPropertyInteger("StateVariable") != 0) {
      $this->UnregisterMessage($this->ReadPropertyInteger("StateVariable"), VM_UPDATE);
    }
    
    //Never delete this line!
    parent::ApplyChanges();
    
    //Create our trigger
    if(IPS_VariableExists($this->ReadPropertyInteger("StateVariable"))) {
      $this->RegisterMessage($this->ReadPropertyInteger("StateVariable"), VM_UPDATE);
    }
  }
  
  /**
   * Interne Funktion des SDK.
   * Data[0] = neuer Wert
   * Data[1] = Wert wurde geändert?
   *
   * @access public
   */
  public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
  {
    $this->SendDebug('Message:Data', $Data[0] . " : " . $Data[1], 0);

    switch ($Message)
    {
      case VM_UPDATE:
        if ($SenderID != $this->ReadPropertyInteger("StateVariable")) {
          $this->SendDebug('Message:SenderID', $SenderID . " unbekannt!", 0);
          break;
        }
        // Dauerbetrieb, tue nix!
        $pid = $this->ReadPropertyInteger("PermanentVariable");
        if ($pid != 0 && GetValue($pid)) {
          $this->SendDebug('MessageSink', "Dauerbetrieb ist angeschalten!", 0);
          break;
        }
        if ($Data[0] == true) {
          // Minutenberechnung = 1000ms * 1min(60s) * Duration
          $this->SetTimerInterval("TriggerTimer", 1000 * 60 * $this->ReadPropertyInteger("Duration"));
        }
        else {
          $this->SendDebug('MessageSink', "Licht(Aktor) wurde aus geschaltet!", 0);
          // Licht(Aktor) wurde schon manuell (aus)geschaltet
          $this->SetTimerInterval("TriggerTimer", 0);
        }
      break;
    }
  }   

  /**
  * This function will be available automatically after the module is imported with the module control.
  * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
  *
  * TLA_Trigger($id);
  *
  * @access public
  */
  public function Trigger()
  {
    $sv = $this->ReadPropertyInteger("StateVariable");
    $this->SendDebug('SV', "Variable eingelesen" , 0);
    if (GetValueBoolean($sv) == true) {
      $this->SendDebug('SV', "Variable ist true" , 0);
      if($this->ReadPropertyBoolean("OnlyScript") == false ) {
        $mid1 = $this->ReadPropertyInteger("MotionVariable1");
        $mid2 = $this->ReadPropertyInteger("MotionVariable2");
        if($mid1 != 0 && GetValue($mid1) == true || $mid2 != 0 && GetValue($mid2) == true) {
          $this->SendDebug('TLA_Trigger', "Bewegungsmelder aktiv, also nochmal!" , 0);
          return;
        }
        else {
          if($this->ReadPropertyBoolean("OnlyBool") == true) {
            SetValueBoolean($sv, false);
            $this->SendDebug('OnlyBool', "ist true" , 0);
          }
        else {
            $pid = IPS_GetParent($sv);          
            HM_WriteValueBoolean($pid, "STATE", false); //Gerät ausschalten
          }
          $this->SendDebug('TLA_Trigger', "StateVariable (#" . $sv . ") auf false geschalten!" , 0);
          // WFC_PushNotification(xxxxx , 'Licht', '...wurde ausgeschalten!', '', 0);
        }
      }    
      // Script ausführen
      if($this->ReadPropertyBoolean("ExecScript") == true) {     
        if ($this->ReadPropertyInteger("ScriptVariable") <> 0) {
          if (IPS_ScriptExists($this->ReadPropertyInteger("ScriptVariable"))) {
              $sr = IPS_RunScript($this->ReadPropertyInteger("ScriptVariable"));
              $this->SendDebug('Script Execute: Return Value', $rs, 0);
          }
        }
        else {
          $this->SendDebug("TLA_Trigger", "Script #" . $this->ReadPropertyInteger('ScriptVariable') . " existiert nicht!",0);
        }
      }
    }
    else {
      $this->SendDebug('TLA_Trigger', "STATE schon FALSE - Timer löschen!" , 0);
    }        
    $this->SetTimerInterval("TriggerTimer", 0);
  }

  /**
  * This function will be available automatically after the module is imported with the module control.
  * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
  *
  * TLA_Duration($id, $duration);
  *
  * @access public
  * @param  integer $duration Wartezeit einstellen.
  */
  public function Duration(int $duration)
  {
      IPS_SetProperty($this->InstanceID, "Duration", $duration);
      IPS_ApplyChanges($this->InstanceID);
  }
}

?>
