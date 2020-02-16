<?php
    namespace Rosaworks\FarmingTool;

    class Character
    {
        public $ID;
        public $Name;
        public $Mounts = [];

        public function __construct($CharacterID, $CharacterName)
        {
            $this->ID = $CharacterID;
            $this->Name = $CharacterName;
        }

        public function setMounts($Mounts)
        {
            $Response = false;
            if (is_array($Mounts)) {
                $Response = true;
                foreach ($Mounts as $Mount) {
                    $this->Mounts[] = $Mount;
                }
            }
            return $Response;
        }
    }